<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/drive.php';
require_once __DIR__ . '/includes/ingest.php';
require_once __DIR__ . '/includes/fleet.php';

$allowedRoles = ['system_admin'];
require_role($allowedRoles);

$poll = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $poll = ingest_poll_drive();
}

$lastImport = fleet_last_ingest();
$pageTitle = 'Settings';
require __DIR__ . '/includes/header-app.php';
?>
<div class="panel">
    <p class="panel-lead">Fleet files are checked every <?php echo (int) DRIVE_POLL_MINUTES; ?> minutes.</p>

    <dl class="meta-list">
        <?php require __DIR__ . '/includes/poll-meta.php'; ?>
        <div><dt>Poll Script</dt><dd><code><?php echo e(str_replace('\\', '/', realpath(__DIR__ . '/poll-ingest.php') ?: (__DIR__ . '/poll-ingest.php'))); ?></code></dd></div>
        <?php if ($lastImport): ?>
            <div><dt>Last Import</dt><dd><?php echo e($lastImport['file_name']); ?> · <?php echo (int) $lastImport['row_count']; ?> machines · <?php echo e($lastImport['ingested_at']); ?></dd></div>
        <?php endif; ?>
    </dl>

    <form method="post" action="<?php echo e(base_url('settings.php')); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <button class="btn" type="submit">Run Poll Now</button>
    </form>
</div>

<?php if (is_array($poll)): ?>
    <?php if ($poll['ok']): ?>
        <p class="notice"><?php echo e($poll['message']); ?></p>
    <?php else: ?>
        <p class="error"><?php echo e($poll['message']); ?></p>
    <?php endif; ?>
<?php endif; ?>
<?php
require __DIR__ . '/includes/footer-app.php';
