<?php

function machine_types_upload_dir(): string
{
    return dirname(__DIR__) . '/assets/img/fleet/uploads';
}

function machine_types_ensure_upload_dir(): bool
{
    $dir = machine_types_upload_dir();

    if (is_dir($dir)) {
        return is_writable($dir);
    }

    return @mkdir($dir, 0755, true) && is_writable($dir);
}

function machine_types_sync_from_fleet(): int
{
    $db = db();
    $result = $db->query("
        SELECT DISTINCT TRIM(vehicle_type) AS name
        FROM machines
        WHERE vehicle_type IS NOT NULL AND TRIM(vehicle_type) <> ''
        ORDER BY name
    ");
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $added = 0;

    $statement = $db->prepare('
        INSERT IGNORE INTO machine_types (name, slug, source)
        VALUES (?, ?, \'export\')
    ');

    foreach ($rows as $row) {
        $name = trim((string) $row['name']);
        if ($name === '') {
            continue;
        }

        $slug = fleet_type_image_slug($name);
        $statement->bind_param('ss', $name, $slug);
        $statement->execute();

        if ($statement->affected_rows > 0) {
            $added++;
        }
    }

    $statement->close();

    return $added;
}

function machine_types_list(?int $siteId = null): array
{
    if ($siteId === 0) {
        return [];
    }

    machine_types_sync_from_fleet();

    $sql = '
        SELECT t.*,
               (
                   SELECT COUNT(*)
                   FROM machines m
                   WHERE m.vehicle_type = t.name
    ';

    if ($siteId !== null) {
        $sql .= ' AND m.site_id = ' . (int) $siteId;
    }

    $sql .= '
               ) AS machine_count
        FROM machine_types t
    ';

    if ($siteId !== null) {
        $sql .= '
            WHERE EXISTS (
                SELECT 1
                FROM machines m
                WHERE m.vehicle_type = t.name
                  AND m.site_id = ' . (int) $siteId . '
            )
        ';
    }

    $sql .= ' ORDER BY t.source ASC, t.name ASC';
    $result = db()->query($sql);

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function machine_types_visible_at_site(array $type, ?int $siteId): bool
{
    if ($siteId === null) {
        return true;
    }

    if ($siteId === 0) {
        return false;
    }

    $name = (string) ($type['name'] ?? '');
    if ($name === '') {
        return false;
    }

    $statement = db()->prepare('
        SELECT COUNT(*) AS n
        FROM machines
        WHERE vehicle_type = ? AND site_id = ?
    ');
    $statement->bind_param('si', $name, $siteId);
    $statement->execute();
    $count = (int) ($statement->get_result()->fetch_assoc()['n'] ?? 0);
    $statement->close();

    return $count > 0;
}

function machine_types_get(int $id): ?array
{
    $statement = db()->prepare('SELECT * FROM machine_types WHERE id = ? LIMIT 1');
    $statement->bind_param('i', $id);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return $row ?: null;
}

function machine_types_get_by_name(string $name): ?array
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM machine_types WHERE name = ? LIMIT 1');
    $statement->bind_param('s', $name);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return $row ?: null;
}

function machine_types_unique_slug(string $slug, ?int $ignoreId = null): string
{
    $base = $slug !== '' ? $slug : 'type';
    $candidate = $base;
    $n = 2;

    while (true) {
        $sql = 'SELECT id FROM machine_types WHERE slug = ?';
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ' . (int) $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $statement = db()->prepare($sql);
        $statement->bind_param('s', $candidate);
        $statement->execute();
        $exists = $statement->get_result()->fetch_assoc();
        $statement->close();

        if (!$exists) {
            return $candidate;
        }

        $candidate = $base . '-' . $n;
        $n++;
    }
}

function machine_types_photo_url(?array $type): string
{
    return fleet_type_image_url((string) ($type['name'] ?? ''));
}

function machine_types_icon_url(?array $type): string
{
    return fleet_type_icon_url((string) ($type['name'] ?? ''));
}

function machine_types_count_for_scope(string $name, ?int $siteId): int
{
    if ($siteId === 0) {
        return 0;
    }

    $sql = 'SELECT COUNT(*) AS n FROM machines WHERE vehicle_type = ?';
    if ($siteId !== null) {
        $sql .= ' AND site_id = ' . (int) $siteId;
    }

    $statement = db()->prepare($sql);
    $statement->bind_param('s', $name);
    $statement->execute();
    $count = (int) ($statement->get_result()->fetch_assoc()['n'] ?? 0);
    $statement->close();

    return $count;
}

function machine_types_modal_payload(array $type, ?int $siteId = null): array
{
    $name = (string) $type['name'];

    return [
        'id' => (int) $type['id'],
        'name' => $name,
        'source' => (string) $type['source'],
        'photo_url' => machine_types_photo_url($type),
        'icon_url' => machine_types_icon_url($type),
        'has_photo' => trim((string) ($type['photo_file'] ?? '')) !== '',
        'has_icon' => trim((string) ($type['icon_file'] ?? '')) !== '',
        'machine_count' => isset($type['machine_count'])
            ? (int) $type['machine_count']
            : machine_types_count_for_scope($name, $siteId),
    ];
}

function machine_types_create(array $input, array $files): array
{
    $name = trim((string) ($input['name'] ?? ''));

    if ($name === '') {
        return ['ok' => false, 'error' => 'Enter a machine type name.'];
    }

    if (mb_strlen($name) > 100) {
        return ['ok' => false, 'error' => 'Type name is too long.'];
    }

    if (machine_types_get_by_name($name) !== null) {
        return ['ok' => false, 'error' => 'That machine type already exists.'];
    }

    $slug = machine_types_unique_slug(fleet_type_image_slug($name));
    $source = 'manual';
    $photo = '';
    $icon = '';

    $statement = db()->prepare('
        INSERT INTO machine_types (name, slug, source, photo_file, icon_file)
        VALUES (?, ?, ?, ?, ?)
    ');
    $statement->bind_param('sssss', $name, $slug, $source, $photo, $icon);
    $statement->execute();

    if ($statement->error !== '') {
        $error = $statement->error;
        $statement->close();

        return ['ok' => false, 'error' => 'Could not create type: ' . $error];
    }

    $id = (int) $statement->insert_id;
    $statement->close();

    $type = machine_types_get($id);
    $upload = machine_types_apply_uploads($type, $files, false, false);

    if (!$upload['ok']) {
        return ['ok' => true, 'id' => $id, 'warning' => $upload['error']];
    }

    return ['ok' => true, 'id' => $id];
}

function machine_types_update(array $type, array $input, array $files): array
{
    $id = (int) $type['id'];
    $isManual = ($type['source'] ?? '') === 'manual';
    $name = $isManual ? trim((string) ($input['name'] ?? '')) : (string) $type['name'];

    if ($isManual) {
        if ($name === '') {
            return ['ok' => false, 'error' => 'Enter a machine type name.'];
        }

        if (mb_strlen($name) > 100) {
            return ['ok' => false, 'error' => 'Type name is too long.'];
        }

        $existing = machine_types_get_by_name($name);
        if ($existing !== null && (int) $existing['id'] !== $id) {
            return ['ok' => false, 'error' => 'That machine type name is already in use.'];
        }
    }

    $clearPhoto = ! empty($input['clear_photo']);
    $clearIcon = ! empty($input['clear_icon']);
    $upload = machine_types_apply_uploads($type, $files, $clearPhoto, $clearIcon);

    if (!$upload['ok']) {
        return $upload;
    }

    $type = machine_types_get($id) ?? $type;
    $photo = (string) ($type['photo_file'] ?? '');
    $icon = (string) ($type['icon_file'] ?? '');
    $slug = $isManual ? machine_types_unique_slug(fleet_type_image_slug($name), $id) : (string) $type['slug'];

    $statement = db()->prepare('
        UPDATE machine_types
        SET name = ?, slug = ?, photo_file = ?, icon_file = ?
        WHERE id = ?
    ');
    $statement->bind_param('ssssi', $name, $slug, $photo, $icon, $id);
    $statement->execute();

    if ($statement->error !== '') {
        $error = $statement->error;
        $statement->close();

        return ['ok' => false, 'error' => 'Could not save type: ' . $error];
    }

    $statement->close();

    return ['ok' => true, 'id' => $id];
}

function machine_types_delete(array $type): array
{
    if (($type['source'] ?? '') !== 'manual') {
        return ['ok' => false, 'error' => 'Imported types cannot be deleted.'];
    }

    $name = (string) $type['name'];
    $countStatement = db()->prepare('SELECT COUNT(*) AS n FROM machines WHERE vehicle_type = ?');
    $countStatement->bind_param('s', $name);
    $countStatement->execute();
    $count = (int) ($countStatement->get_result()->fetch_assoc()['n'] ?? 0);
    $countStatement->close();

    if ($count > 0) {
        return ['ok' => false, 'error' => 'This type is still used by ' . $count . ' machine(s). Reassign or remove those first.'];
    }

    machine_types_delete_file((string) ($type['photo_file'] ?? ''));
    machine_types_delete_file((string) ($type['icon_file'] ?? ''));

    $id = (int) $type['id'];
    $statement = db()->prepare('DELETE FROM machine_types WHERE id = ? AND source = \'manual\'');
    $statement->bind_param('i', $id);
    $statement->execute();
    $statement->close();

    return ['ok' => true];
}

function machine_types_delete_file(string $relative): void
{
    $relative = trim($relative);
    if ($relative === '') {
        return;
    }

    $path = machine_types_upload_dir() . '/' . basename($relative);
    if (is_file($path)) {
        @unlink($path);
    }
}

function machine_types_apply_uploads(?array $type, array $files, bool $clearPhoto, bool $clearIcon): array
{
    if ($type === null) {
        return ['ok' => false, 'error' => 'Type not found.'];
    }

    if (! machine_types_ensure_upload_dir()) {
        return ['ok' => false, 'error' => 'Upload folder is not writable (assets/img/fleet/uploads).'];
    }

    $id = (int) $type['id'];
    $photo = (string) ($type['photo_file'] ?? '');
    $icon = (string) ($type['icon_file'] ?? '');

    if ($clearPhoto) {
        machine_types_delete_file($photo);
        $photo = '';
    }

    if ($clearIcon) {
        machine_types_delete_file($icon);
        $icon = '';
    }

    $photoUpload = machine_types_store_upload($files['photo'] ?? null, $id, 'photo', 5 * 1024 * 1024);
    if ($photoUpload['error'] !== '') {
        return ['ok' => false, 'error' => $photoUpload['error']];
    }
    if ($photoUpload['file'] !== '') {
        machine_types_delete_file($photo);
        $photo = $photoUpload['file'];
    }

    $iconUpload = machine_types_store_upload($files['icon'] ?? null, $id, 'icon', 2 * 1024 * 1024);
    if ($iconUpload['error'] !== '') {
        return ['ok' => false, 'error' => $iconUpload['error']];
    }
    if ($iconUpload['file'] !== '') {
        machine_types_delete_file($icon);
        $icon = $iconUpload['file'];
    }

    $statement = db()->prepare('UPDATE machine_types SET photo_file = ?, icon_file = ? WHERE id = ?');
    $statement->bind_param('ssi', $photo, $icon, $id);
    $statement->execute();
    $statement->close();

    return ['ok' => true];
}

function machine_types_store_upload(mixed $file, int $id, string $kind, int $maxBytes): array
{
    if (! is_array($file) || ! isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['file' => '', 'error' => ''];
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        return ['file' => '', 'error' => 'Could not upload the ' . $kind . ' file.'];
    }

    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxBytes) {
        return ['file' => '', 'error' => ucfirst($kind) . ' file is empty or too large.'];
    }

    $original = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

    if (! in_array($ext, $allowed, true)) {
        return ['file' => '', 'error' => ucfirst($kind) . ' must be jpg, png, webp, or svg.'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || ! is_uploaded_file($tmp)) {
        return ['file' => '', 'error' => 'Invalid ' . $kind . ' upload.'];
    }

    if ($ext !== 'svg') {
        $info = @getimagesize($tmp);
        if ($info === false) {
            return ['file' => '', 'error' => ucfirst($kind) . ' is not a valid image.'];
        }
    }

    $filename = $id . '-' . $kind . '.' . $ext;
    $dest = machine_types_upload_dir() . '/' . $filename;

    if (! @move_uploaded_file($tmp, $dest)) {
        return ['file' => '', 'error' => 'Could not save the ' . $kind . ' file.'];
    }

    return ['file' => $filename, 'error' => ''];
}
