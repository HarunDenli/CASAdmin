<?php

function gallery_upload_dir(): string
{
    return dirname(__DIR__) . '/assets/img/gallery';
}

function gallery_ensure_upload_dir(): bool
{
    $dir = gallery_upload_dir();
    if (is_dir($dir)) {
        return is_writable($dir);
    }

    return @mkdir($dir, 0755, true) && is_writable($dir);
}

function gallery_list(): array
{
    $result = db()->query('
        SELECT id, file_name, original_name, created_at
        FROM gallery_images
        ORDER BY created_at DESC, id DESC
    ');

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function gallery_get(int $id): ?array
{
    $statement = db()->prepare('
        SELECT id, file_name, original_name, created_at
        FROM gallery_images
        WHERE id = ?
        LIMIT 1
    ');
    $statement->bind_param('i', $id);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return $row ?: null;
}

function gallery_image_url(array $image): string
{
    $file = basename((string) ($image['file_name'] ?? ''));
    if ($file === '') {
        return '';
    }

    return base_url('assets/img/gallery/' . $file);
}

function gallery_delete_file(string $fileName): void
{
    $fileName = basename($fileName);
    if ($fileName === '' || $fileName === '.' || $fileName === '..') {
        return;
    }

    $path = gallery_upload_dir() . '/' . $fileName;
    if (is_file($path)) {
        @unlink($path);
    }
}

function gallery_upload(array $file): array
{
    if (! gallery_ensure_upload_dir()) {
        return ['ok' => false, 'error' => 'Gallery Folder Is Not Writable.'];
    }

    if (! isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Choose An Image To Upload.'];
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Could Not Upload That Image.'];
    }

    $maxBytes = 8 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'Image Is Empty Or Too Large.'];
    }

    $original = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (! in_array($ext, $allowed, true)) {
        return ['ok' => false, 'error' => 'Image Must Be Jpg, Png, Webp, Or Gif.'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || ! is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Invalid Image Upload.'];
    }

    $info = @getimagesize($tmp);
    if ($info === false) {
        return ['ok' => false, 'error' => 'That File Is Not A Valid Image.'];
    }

    $originalName = substr($original, 0, 190);
    $placeholder = '';
    $statement = db()->prepare('
        INSERT INTO gallery_images (file_name, original_name)
        VALUES (?, ?)
    ');
    $statement->bind_param('ss', $placeholder, $originalName);

    if (! $statement->execute()) {
        $statement->close();

        return ['ok' => false, 'error' => 'Could Not Save Image Details.'];
    }

    $id = (int) $statement->insert_id;
    $statement->close();

    $fileName = 'gallery-' . $id . '.' . $ext;
    $dest = gallery_upload_dir() . '/' . $fileName;

    if (! @move_uploaded_file($tmp, $dest)) {
        $cleanup = db()->prepare('DELETE FROM gallery_images WHERE id = ?');
        $cleanup->bind_param('i', $id);
        $cleanup->execute();
        $cleanup->close();

        return ['ok' => false, 'error' => 'Could Not Save The Image File.'];
    }

    $update = db()->prepare('UPDATE gallery_images SET file_name = ? WHERE id = ?');
    $update->bind_param('si', $fileName, $id);
    $update->execute();
    $update->close();

    return ['ok' => true, 'id' => $id, 'error' => ''];
}

function gallery_delete(int $id): array
{
    $image = gallery_get($id);
    if ($image === null) {
        return ['ok' => false, 'error' => 'That Image Was Not Found.'];
    }

    $statement = db()->prepare('DELETE FROM gallery_images WHERE id = ?');
    $statement->bind_param('i', $id);

    if (! $statement->execute()) {
        $statement->close();

        return ['ok' => false, 'error' => 'Could Not Remove That Image.'];
    }

    $statement->close();
    gallery_delete_file((string) $image['file_name']);

    return ['ok' => true, 'error' => ''];
}
