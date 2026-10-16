<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/contact.php';

$message = '';
$error = '';
$errorField = '';
$posted = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'subject' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $posted = [
        'name' => (string) ($_POST['name'] ?? ''),
        'email' => (string) ($_POST['email'] ?? ''),
        'phone' => (string) ($_POST['phone'] ?? ''),
        'subject' => (string) ($_POST['subject'] ?? ''),
        'message' => (string) ($_POST['message'] ?? ''),
    ];
    $result = contact_submit($posted);
    if ($result['ok']) {
        header('Location: ' . base_url('contact.php?sent=1'));
        exit;
    }
    $error = $result['error'];
    $errorField = (string) ($result['field'] ?? '');
}

if (isset($_GET['sent'])) {
    $message = 'Thank You. Your Message Has Been Received.';
}

$pageTitle = 'Contact Us · CAS Admin Portal';
$bodyClass = 'landing landing-contact';
require __DIR__ . '/includes/header-guest.php';

$loggedIn = is_logged_in();
$mapSrc = contact_map_embed_url();
?>
<div class="contact-page">
    <header class="contact-top">
        <a class="contact-brand" href="<?php echo e(base_url($loggedIn ? 'dashboard.php' : 'index.php')); ?>">
            <img src="<?php echo e(base_url('assets/img/minetec-logo.png')); ?>" alt="Minetec Smart Mining">
        </a>
        <nav class="contact-nav">
            <a href="<?php echo e(base_url('index.php')); ?>">Home</a>
            <a href="<?php echo e(base_url('gallery.php')); ?>">Gallery</a>
            <?php if ($loggedIn): ?>
                <a class="btn btn-sm" href="<?php echo e(base_url('dashboard.php')); ?>">Portal</a>
            <?php else: ?>
                <a class="btn btn-sm" href="<?php echo e(base_url('login.php')); ?>">Sign In</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="contact-main">
        <div class="contact-intro">
            <h1>Contact Us</h1>
            <p>Get in touch with Minetec Smart Mining.</p>
        </div>

        <?php if ($message !== ''): ?>
            <p class="notice"><?php echo e($message); ?></p>
        <?php endif; ?>
        <?php if ($error !== '' && $errorField === ''): ?>
            <p class="error"><?php echo e($error); ?></p>
        <?php endif; ?>

        <div class="contact-grid">
            <section class="contact-details" aria-label="Contact details">
                <div class="contact-card">
                    <h2>Address</h2>
                    <p><?php echo e(CONTACT_ADDRESS); ?></p>
                </div>
                <div class="contact-card">
                    <h2>Email</h2>
                    <p><a href="mailto:<?php echo e(CONTACT_EMAIL); ?>"><?php echo e(CONTACT_EMAIL); ?></a></p>
                </div>
                <div class="contact-card">
                    <h2>Call Us</h2>
                    <p><a href="tel:<?php echo e(CONTACT_PHONE_TEL); ?>"><?php echo e(CONTACT_PHONE); ?></a></p>
                </div>
            </section>

            <section class="contact-map" aria-label="Headquarters map">
                <iframe
                    title="Minetec Headquarters Map"
                    src="<?php echo e($mapSrc); ?>"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                ></iframe>
            </section>
        </div>

        <section class="contact-form-panel" aria-label="Send a message">
            <h2>Send A Message</h2>
            <form
                method="post"
                action="<?php echo e(base_url('contact.php')); ?>"
                class="contact-form"
                id="contact-form"
                <?php if ($error !== '' && $errorField !== ''): ?>
                    data-error-field="<?php echo e($errorField); ?>"
                    data-error-message="<?php echo e($error); ?>"
                <?php endif; ?>
            >
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <div class="form-grid">
                    <div class="field">
                        <label for="contact-name">Name</label>
                        <input id="contact-name" name="name" type="text" maxlength="120" required value="<?php echo e($posted['name']); ?>">
                    </div>
                    <div class="field">
                        <label for="contact-email">Email</label>
                        <input id="contact-email" name="email" type="email" maxlength="190" required value="<?php echo e($posted['email']); ?>">
                    </div>
                    <div class="field">
                        <label for="contact-phone">Phone</label>
                        <input
                            id="contact-phone"
                            name="phone"
                            type="tel"
                            inputmode="tel"
                            maxlength="40"
                            required
                            placeholder="e.g. 011 684 1120"
                            autocomplete="tel"
                            value="<?php echo e($posted['phone']); ?>"
                        >
                    </div>
                    <div class="field">
                        <label for="contact-subject">Subject</label>
                        <input id="contact-subject" name="subject" type="text" maxlength="190" value="<?php echo e($posted['subject']); ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="contact-message">Message</label>
                    <textarea id="contact-message" name="message" rows="6" maxlength="5000" required><?php echo e($posted['message']); ?></textarea>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit">Send Message</button>
                </div>
            </form>
        </section>
    </main>

</div>
<script src="<?php echo e(base_url('assets/js/contact-form.js')); ?>?v=1"></script>
<?php
require __DIR__ . '/includes/footer-guest.php';
