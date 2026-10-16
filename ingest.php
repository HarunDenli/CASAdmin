<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/drive.php';
require_once __DIR__ . '/includes/ingest.php';
require_once __DIR__ . '/includes/fleet.php';

$allowedRoles = ['system_admin'];
require_role($allowedRoles);

$list = null;
$ingest = null;
$poll = null;
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'list');

    if ($action === 'ingest') {
        $ingest = ingest_excel_from_drive(
            (string) ($_POST['file_id'] ?? ''),
            (string) ($_POST['file_name'] ?? ''),
            (string) ($_POST['file_modified'] ?? ''),
            (int) $user['id'],
        );
    }

    if ($action === 'poll') {
        $poll = ingest_poll_drive();
    }

    $list = drive_list_files();
}

$last = fleet_last_ingest();
$pageTitle = 'Data Ingest';
require __DIR__ . '/includes/header-app.php';
?>
<div class="panel">
    <p class="panel-lead">Fleet files import automatically every <?php echo (int) DRIVE_POLL_MINUTES; ?> minutes when they change.</p>

    <dl class="meta-list">
        <div><dt>Service Account</dt><dd><?php echo e(DRIVE_SERVICE_EMAIL); ?></dd></div>
        <div><dt>Key File</dt><dd><?php echo drive_key_ready() ? 'Found' : 'Missing'; ?></dd></div>
        <div><dt>Folder</dt><dd><code><?php echo e(DRIVE_FOLDER_ID); ?></code></dd></div>
        <?php require __DIR__ . '/includes/poll-meta.php'; ?>
        <?php if ($last): ?>
            <div><dt>Last Import</dt><dd><?php echo e($last['file_name']); ?> · <?php echo (int) $last['row_count']; ?> machines · <?php echo e($last['ingested_at']); ?></dd></div>
        <?php endif; ?>
    </dl>

    <div class="filter-row" style="margin-bottom:16px;">
        <form method="post" action="<?php echo e(base_url('ingest.php')); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="list">
            <button class="btn" type="submit">Check Drive Now</button>
        </form>
        <form method="post" action="<?php echo e(base_url('ingest.php')); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="poll">
            <button class="btn" type="submit">Run Poll Now</button>
        </form>
    </div>
</div>

<?php if (is_array($ingest)): ?>
    <?php if ($ingest['ok']): ?>
        <p class="notice">Imported <?php echo (int) $ingest['row_count']; ?> machines across <?php echo (int) $ingest['site_count']; ?> sites.</p>
    <?php else: ?>
        <p class="error"><?php echo e($ingest['error']); ?></p>
    <?php endif; ?>
<?php endif; ?>

<?php if (is_array($poll)): ?>
    <?php if ($poll['ok']): ?>
        <p class="notice"><?php echo e($poll['message']); ?></p>
    <?php else: ?>
        <p class="error"><?php echo e($poll['message']); ?></p>
    <?php endif; ?>
<?php endif; ?>

<?php if (is_array($list)): ?>
    <?php if (! $list['ok']): ?>
        <p class="error"><?php echo e($list['error']); ?></p>
    <?php elseif ($list['files'] === []): ?>
        <div class="panel">
            <p>No files found in this folder yet.</p>
        </div>
    <?php else: ?>
        <div class="panel">
            <p class="panel-lead"><?php echo count($list['files']); ?> file(s) in <?php echo e($list['folder_name'] !== '' ? $list['folder_name'] : 'the folder'); ?>.</p>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Modified</th>
                            <th data-nosort="1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list['files'] as $file): ?>
                            <tr>
                                <td><?php echo e($file['name'] ?? ''); ?></td>
                                <td><?php echo e($file['mimeType'] ?? ''); ?></td>
                                <td data-sort="<?php echo e((string) ($file['size'] ?? '')); ?>"><?php echo e(drive_format_size($file['size'] ?? null)); ?></td>
                                <td><?php echo e(isset($file['modifiedTime']) ? str_replace('T', ' ', substr($file['modifiedTime'], 0, 19)) : '—'); ?></td>
                                <td>
                                    <?php if (drive_is_spreadsheet((string) ($file['name'] ?? ''), (string) ($file['mimeType'] ?? ''))): ?>
                                        <form method="post" action="<?php echo e(base_url('ingest.php')); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="ingest">
                                            <input type="hidden" name="file_id" value="<?php echo e($file['id'] ?? ''); ?>">
                                            <input type="hidden" name="file_name" value="<?php echo e($file['name'] ?? ''); ?>">
                                            <input type="hidden" name="file_modified" value="<?php echo e($file['modifiedTime'] ?? ''); ?>">
                                            <button class="btn btn-sm" type="submit">Import Into Portal</button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php
require __DIR__ . '/includes/footer-app.php';
