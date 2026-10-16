<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/gallery.php';

require_role(['system_admin']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'upload') {
        $result = gallery_upload($_FILES['image'] ?? []);
        if ($result['ok']) {
            header('Location: ' . base_url('gallery-admin.php?uploaded=1'));
            exit;
        }
        $error = $result['error'];
    }

    if ($action === 'delete') {
        $result = gallery_delete((int) ($_POST['image_id'] ?? 0));
        if ($result['ok']) {
            header('Location: ' . base_url('gallery-admin.php?removed=1'));
            exit;
        }
        $error = $result['error'];
    }
}

if (isset($_GET['uploaded'])) {
    $message = 'Image Added.';
} elseif (isset($_GET['removed'])) {
    $message = 'Image Removed.';
}

$images = gallery_list();
$pageTitle = 'Gallery';
require __DIR__ . '/includes/header-app.php';
?>
<?php if ($message !== ''): ?>
    <p class="notice"><?php echo e($message); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo e($error); ?></p>
<?php endif; ?>

<div class="panel">
    <p class="form-actions" style="margin-bottom:16px;">
        <a class="btn btn-secondary" href="<?php echo e(base_url('gallery.php')); ?>" target="_blank" rel="noopener noreferrer">View Public Gallery</a>
    </p>
    <form method="post" action="<?php echo e(base_url('gallery-admin.php')); ?>" enctype="multipart/form-data" class="gallery-upload-form">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="upload">
        <div class="form-grid">
            <div class="field">
                <label for="gallery-image">Add Image</label>
                <input id="gallery-image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" required>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">Upload Image</button>
        </div>
    </form>
</div>

<div class="panel">
    <?php if ($images === []): ?>
        <p class="panel-lead">No Images Yet.</p>
    <?php else: ?>
        <div class="gallery-admin-grid">
            <?php foreach ($images as $image): ?>
                <?php
                $url = gallery_image_url($image);
                if ($url === '') {
                    continue;
                }
                ?>
                <div class="gallery-admin-card">
                    <a href="<?php echo e($url); ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo e($url); ?>" alt="<?php echo e((string) $image['original_name']); ?>">
                    </a>
                    <div class="gallery-admin-meta">
                        <span><?php echo e($image['original_name'] !== '' ? $image['original_name'] : $image['file_name']); ?></span>
                        <form method="post" action="<?php echo e(base_url('gallery-admin.php')); ?>" onsubmit="return confirm('Remove this image?');">
                            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="image_id" value="<?php echo (int) $image['id']; ?>">
                            <button class="btn btn-sm btn-secondary" type="submit">Remove</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
require __DIR__ . '/includes/footer-app.php';
