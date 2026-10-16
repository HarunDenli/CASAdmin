<?php
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <?php require __DIR__ . '/fonts.php'; ?>
    <link rel="stylesheet" href="<?php echo e(base_url('assets/css/style.css')); ?>?v=50">
</head>
<body class="<?php echo e($bodyClass ?? 'landing'); ?>">
