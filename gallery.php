<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/gallery.php';

$images = gallery_list();
$galleryItems = [];
foreach ($images as $image) {
    $url = gallery_image_url($image);
    if ($url === '') {
        continue;
    }
    $galleryItems[] = [
        'url' => $url,
        'alt' => (string) ($image['original_name'] !== '' ? $image['original_name'] : 'Gallery image'),
    ];
}

$pageTitle = 'Gallery · CAS Admin Portal';
$bodyClass = 'landing landing-contact';
require __DIR__ . '/includes/header-guest.php';

$loggedIn = is_logged_in();
?>
<div class="contact-page">
    <header class="contact-top">
        <a class="contact-brand" href="<?php echo e(base_url($loggedIn ? 'dashboard.php' : 'index.php')); ?>">
            <img src="<?php echo e(base_url('assets/img/minetec-logo.png')); ?>" alt="Minetec Smart Mining">
        </a>
        <nav class="contact-nav">
            <a href="<?php echo e(base_url('index.php')); ?>">Home</a>
            <a href="<?php echo e(base_url('contact.php')); ?>">Contact Us</a>
            <?php if ($loggedIn): ?>
                <a class="btn btn-sm" href="<?php echo e(base_url('dashboard.php')); ?>">Portal</a>
            <?php else: ?>
                <a class="btn btn-sm" href="<?php echo e(base_url('login.php')); ?>">Sign In</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="contact-main">
        <div class="contact-intro">
            <h1>Gallery</h1>
            <p>Minetec Smart Mining in action.</p>
        </div>

        <?php if ($galleryItems === []): ?>
            <div class="empty-state gallery-empty">
                <h2>No Images Yet</h2>
                <p>Check back soon.</p>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($galleryItems as $i => $item): ?>
                    <button
                        type="button"
                        class="gallery-item"
                        data-gallery-index="<?php echo (int) $i; ?>"
                        data-src="<?php echo e($item['url']); ?>"
                        data-alt="<?php echo e($item['alt']); ?>"
                    >
                        <img src="<?php echo e($item['url']); ?>" alt="<?php echo e($item['alt']); ?>" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="gallery-viewer" id="gallery-viewer" hidden>
                <button type="button" class="gallery-viewer-close" data-gallery-close aria-label="Close">×</button>
                <button type="button" class="gallery-viewer-nav gallery-viewer-prev" id="gallery-viewer-prev" aria-label="Previous Image">‹</button>
                <div class="gallery-viewer-stage">
                    <img id="gallery-viewer-image" src="" alt="">
                    <p class="gallery-viewer-counter" id="gallery-viewer-counter"></p>
                </div>
                <button type="button" class="gallery-viewer-nav gallery-viewer-next" id="gallery-viewer-next" aria-label="Next Image">›</button>
            </div>
            <script src="<?php echo e(base_url('assets/js/gallery-viewer.js')); ?>?v=1"></script>
        <?php endif; ?>
    </main>
</div>
<?php
require __DIR__ . '/includes/footer-guest.php';
