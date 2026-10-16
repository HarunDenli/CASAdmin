<?php
$user = current_user();
$menu = menu_for_role($user['role']);
$currentFile = basename($_SERVER['SCRIPT_NAME']);
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> · CAS Admin Portal</title>
    <?php require __DIR__ . '/fonts.php'; ?>
    <link rel="stylesheet" href="<?php echo e(base_url('assets/css/style.css')); ?>?v=59">
    <?php if (! empty($extraHead)) {
        echo $extraHead;
    } ?>
</head>
<body>
<div class="app">
    <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
    <aside class="sidebar" id="app-sidebar">
        <a class="sidebar-brand" href="<?php echo e(base_url('dashboard.php')); ?>">
            <img class="sidebar-logo" src="<?php echo e(base_url('assets/img/minetec-logo.png')); ?>" alt="Minetec Smart Mining">
        </a>
        <p class="nav-label">Menu</p>
        <nav class="nav">
            <?php foreach ($menu as $item): ?>
                <?php if (! empty($item['children'])): ?>
                    <?php $sectionOpen = menu_section_is_active($item, $currentFile); ?>
                    <details class="nav-section<?php echo $sectionOpen ? ' is-active' : ''; ?>"<?php echo $sectionOpen ? ' open' : ''; ?>>
                        <summary class="nav-section-toggle">
                            <?php
                            $sectionIcon = menu_icon_svg((string) ($item['icon'] ?? ''));
                            if ($sectionIcon !== '') {
                                echo $sectionIcon;
                            }
                            ?>
                            <span class="nav-text"><?php echo e($item['label']); ?></span>
                        </summary>
                        <div class="nav-section-items">
                            <?php foreach ($item['children'] as $child): ?>
                                <?php
                                $childIcon = menu_icon_svg((string) ($child['icon'] ?? ''));
                                $childActive = ($child['file'] ?? '') === $currentFile;
                                ?>
                                <a href="<?php echo e(base_url($child['file'])); ?>" class="<?php echo $childActive ? 'active' : ''; ?>">
                                    <?php if ($childIcon !== '') {
                                        echo $childIcon;
                                    } ?>
                                    <span class="nav-text"><?php echo e($child['label']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php else: ?>
                    <?php
                    $iconSvg = menu_icon_svg((string) ($item['icon'] ?? ''));
                    $isActive = ($item['file'] ?? '') === $currentFile;
                    ?>
                    <a href="<?php echo e(base_url($item['file'])); ?>" class="<?php echo $isActive ? 'active' : ''; ?>">
                        <?php if ($iconSvg !== '') {
                            echo $iconSvg;
                        } ?>
                        <span class="nav-text"><?php echo e($item['label']); ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-user">
            <strong><?php echo e($user['name']); ?></strong>
            <span><?php echo e(role_label($user['role'])); ?></span>
            <form method="post" action="<?php echo e(base_url('logout.php')); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <button class="signout" type="submit">Sign Out</button>
            </form>
        </div>
    </aside>
    <div class="main">
        <header class="main-header">
            <div class="header-title-row">
                <button type="button" class="nav-toggle" id="nav-toggle" aria-controls="app-sidebar" aria-expanded="false" aria-label="Open menu">
                    <span class="nav-toggle-bar" aria-hidden="true"></span>
                    <span class="nav-toggle-bar" aria-hidden="true"></span>
                    <span class="nav-toggle-bar" aria-hidden="true"></span>
                </button>
                <h1><?php echo e($pageTitle); ?></h1>
            </div>
            <div class="header-meta">
                <time class="header-clock" id="header-clock" datetime=""></time>
                <?php if (in_array($user['role'], ['client_admin', 'client_user'], true)): ?>
                    <span class="site-pill">Site · <?php echo $user['site_name'] ? e($user['site_name']) : 'Not Assigned'; ?></span>
                <?php endif; ?>
                <span class="role-pill"><?php echo e(role_label($user['role'])); ?></span>
            </div>
        </header>
        <main class="content">
