<?php

require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $statement = db()->prepare('SELECT id, name, email, password, role, site_id, active FROM users WHERE email = ? LIMIT 1');
    $statement->bind_param('s', $email);
    $statement->execute();
    $result = $statement->get_result();
    $user = $result->fetch_assoc();
    $statement->close();

    if ($user && (int) $user['active'] === 1 && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];

        if (!empty($_POST['remember'])) {
            setcookie(session_name(), session_id(), [
                'expires' => time() + (60 * 60 * 24 * 14),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        header('Location: ' . base_url('dashboard.php'));
        exit;
    }

    $error = 'Those details do not match a CAS Admin account.';
}

$pageTitle = 'Sign In · CAS Admin Portal';
require __DIR__ . '/includes/header-guest.php';
?>
<div class="landing-shell">
    <div class="landing-form-side">
        <div class="landing-card">
            <img class="landing-logo" src="<?php echo e(base_url('assets/img/minetec-logo.png')); ?>" alt="Minetec Smart Mining">
            <h1>CAS Admin Portal</h1>
            <p class="landing-sub">Sign In</p>

            <?php if ($error !== ''): ?>
                <p class="error"><?php echo e($error); ?></p>
            <?php endif; ?>

            <?php if (isset($_GET['installed'])): ?>
                <p class="landing-sub">Ready To Sign In.</p>
            <?php endif; ?>

            <form method="post" action="<?php echo e(base_url('login.php')); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?php echo e($email); ?>" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <label class="check">
                    <input type="checkbox" name="remember" value="1">
                    Stay signed in on this computer
                </label>
                <button class="btn" type="submit">Sign In</button>
            </form>
            <p class="landing-back">
                <a href="<?php echo e(base_url('index.php')); ?>">Home</a>
                ·
                <a href="<?php echo e(base_url('contact.php')); ?>">Contact Us</a>
                ·
                <a href="<?php echo e(base_url('gallery.php')); ?>">Gallery</a>
            </p>
        </div>
    </div>
    <div class="landing-photo" role="img" aria-label="CAS proximity rings around haul trucks on a mine haul road"></div>
</div>
<?php
require __DIR__ . '/includes/footer-guest.php';
