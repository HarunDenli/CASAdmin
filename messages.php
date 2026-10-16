<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/contact.php';

require_role(['system_admin']);

$message = '';
$error = '';
$view = null;

if (isset($_GET['id'])) {
    $view = contact_message_get((int) $_GET['id']);
    if ($view === null) {
        $error = 'That Message Was Not Found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        $result = contact_message_delete((int) ($_POST['message_id'] ?? 0));
        if ($result['ok']) {
            header('Location: ' . base_url('messages.php?removed=1'));
            exit;
        }
        $error = $result['error'];
        $view = contact_message_get((int) ($_POST['message_id'] ?? 0));
    }
}

if (isset($_GET['removed'])) {
    $message = 'Message Removed.';
}

$messages = contact_messages_list();
$pageTitle = 'Messages';
require __DIR__ . '/includes/header-app.php';
?>
<?php if ($message !== ''): ?>
    <p class="notice"><?php echo e($message); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo e($error); ?></p>
<?php endif; ?>

<?php if ($view): ?>
    <div class="panel">
        <p class="panel-lead"><?php echo e($view['subject'] !== '' ? $view['subject'] : 'Message'); ?></p>
        <dl class="meta-list">
            <div><dt>From</dt><dd><?php echo e($view['name']); ?></dd></div>
            <div><dt>Email</dt><dd><a href="mailto:<?php echo e($view['email']); ?>"><?php echo e($view['email']); ?></a></dd></div>
            <div><dt>Phone</dt><dd><?php echo e($view['phone'] !== '' ? $view['phone'] : '—'); ?></dd></div>
            <div><dt>Received</dt><dd><?php echo e($view['created_at']); ?></dd></div>
        </dl>
        <div class="message-body">
            <?php echo nl2br(e($view['message'])); ?>
        </div>
        <div class="form-actions" style="margin-top:18px;">
            <a class="btn btn-secondary" href="<?php echo e(base_url('messages.php')); ?>">Back</a>
            <form class="inline-form" method="post" action="<?php echo e(base_url('messages.php')); ?>" onsubmit="return confirm('Remove this message?');">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="message_id" value="<?php echo (int) $view['id']; ?>">
                <button class="btn btn-secondary" type="submit">Remove</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Received</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th data-nosort="1"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($messages === []): ?>
                        <tr><td colspan="5">No Messages Yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($messages as $row): ?>
                        <tr>
                            <td><?php echo e($row['created_at']); ?></td>
                            <td><?php echo e($row['name']); ?></td>
                            <td><?php echo e($row['email']); ?></td>
                            <td><?php echo e($row['subject'] !== '' ? $row['subject'] : '—'); ?></td>
                            <td class="type-actions-cell">
                                <div class="type-row-actions">
                                    <a class="btn btn-sm" href="<?php echo e(base_url('messages.php?id=' . (int) $row['id'])); ?>">Open</a>
                                    <form class="inline-form type-remove-form" method="post" action="<?php echo e(base_url('messages.php')); ?>" onsubmit="return confirm('Remove this message?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="message_id" value="<?php echo (int) $row['id']; ?>">
                                        <button class="btn btn-sm btn-secondary" type="submit">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php
require __DIR__ . '/includes/footer-app.php';
