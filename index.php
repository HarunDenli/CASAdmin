<?php

require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

$localVideo = __DIR__ . '/assets/video/minetec-cas.mp4';
$useLocalVideo = is_readable($localVideo);
$youtubeId = defined('LANDING_YOUTUBE_ID') ? (string) LANDING_YOUTUBE_ID : 'iJaFdZYS9Co';

$pageTitle = 'CAS Admin Portal · Minetec Smart Mining';
$bodyClass = 'landing landing-home';
require __DIR__ . '/includes/header-guest.php';
?>
<div class="home-stage">
    <div class="home-media" aria-hidden="true">
        <?php if ($useLocalVideo): ?>
            <video class="home-player" autoplay muted playsinline loop preload="auto">
                <source src="<?php echo e(base_url('assets/video/minetec-cas.mp4')); ?>" type="video/mp4">
            </video>
        <?php else: ?>
            <div class="home-embed">
                <iframe
                    src="https://www.youtube-nocookie.com/embed/<?php echo e($youtubeId); ?>?autoplay=1&mute=1&loop=1&playlist=<?php echo e($youtubeId); ?>&controls=0&showinfo=0&modestbranding=1&rel=0&playsinline=1"
                    title="Minetec Collision Avoidance System"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    tabindex="-1"
                ></iframe>
            </div>
        <?php endif; ?>
    </div>

    <div class="home-veil"></div>

    <div class="home-copy">
        <img class="home-logo" src="<?php echo e(base_url('assets/img/minetec-logo.png')); ?>" alt="Minetec Smart Mining">
        <h1>CAS Admin Portal</h1>
        <p class="home-lead">Fleet Status And Site Visibility.</p>
        <a class="btn home-cta" href="<?php echo e(base_url('login.php')); ?>">Sign In</a>
    </div>

</div>
<?php
require __DIR__ . '/includes/footer-guest.php';
