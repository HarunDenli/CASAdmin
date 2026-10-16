<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/accounts.php';
require_once __DIR__ . '/includes/fleet.php';

$allowedRoles = ['system_admin', 'client_admin'];
require_role($allowedRoles);

$actor = current_user();
$sites = fleet_sites();
$roles = accounts_creatable_roles($actor);
$message = '';
$error = '';
$edit = null;
$action = '';

if ($actor['role'] === 'client_admin' && empty($actor['site_id'])) {
    $pageTitle = 'Users';
    require __DIR__ . '/includes/header-app.php';
    echo '<div class="empty-state"><h2>No Site Assigned</h2><p>Ask your administrator to assign a site to this account.</p></div>';
    require __DIR__ . '/includes/footer-app.php';
    exit;
}

if (isset($_GET['id'])) {
    $edit = accounts_get((int) $_GET['id']);

    if ($edit === null || ! accounts_can_edit($actor, $edit)) {
        $edit = null;
        $error = 'That account was not found, or you cannot change it.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $result = accounts_create($actor, $_POST);
        if ($result['ok']) {
            header('Location: ' . base_url('users.php?created=1'));
            exit;
        }
        $error = $result['error'];
    }

    if ($action === 'update' && $edit) {
        $result = accounts_update($actor, $edit, $_POST);
        if ($result['ok']) {
            header('Location: ' . base_url('users.php?saved=1'));
            exit;
        }
        $error = $result['error'];
        $edit = accounts_get((int) $edit['id']);
    }

    if ($action === 'delete') {
        $target = accounts_get((int) ($_POST['user_id'] ?? 0));
        if ($target) {
            $result = accounts_delete($actor, $target);
            if ($result['ok']) {
                header('Location: ' . base_url('users.php?removed=1'));
                exit;
            }
            $error = $result['error'];
        }
    }
}

if (isset($_GET['created'])) {
    $message = 'Account Created.';
} elseif (isset($_GET['saved'])) {
    $message = 'Account Saved.';
} elseif (isset($_GET['removed'])) {
    $message = 'Account Removed.';
}

if ($action === 'create' && $error !== '') {
    $_GET['new'] = '1';
}

$creating = isset($_GET['new']) && $edit === null;
$showForm = $edit !== null || $creating;
$people = accounts_list($actor);
$pageTitle = 'Users';
require __DIR__ . '/includes/header-app.php';
?>
<?php if ($message !== ''): ?>
    <p class="notice"><?php echo e($message); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo e($error); ?></p>
<?php endif; ?>

<?php if ($showForm): ?>
<div class="panel">
    <?php if ($edit): ?>
        <p class="panel-lead">Edit <?php echo e($edit['name']); ?>. Leave password blank to keep the current one.</p>
    <?php elseif ($actor['role'] === 'client_admin'): ?>
        <p class="panel-lead">New user for <?php echo e($actor['site_name']); ?>.</p>
    <?php endif; ?>

    <form method="post" action="<?php echo e(base_url($edit ? 'users.php?id=' . (int) $edit['id'] : 'users.php?new=1')); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'create'; ?>">

        <div class="form-grid">
            <div class="field">
                <label for="name">Name</label>
                <input id="name" name="name" type="text" required value="<?php echo e($edit['name'] ?? ($_POST['name'] ?? '')); ?>">
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required value="<?php echo e($edit['email'] ?? ($_POST['email'] ?? '')); ?>">
            </div>
            <?php if (count($roles) > 1): ?>
                <div class="field">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo e($role); ?>" <?php echo ($edit['role'] ?? ($_POST['role'] ?? '')) === $role ? 'selected' : ''; ?>>
                                <?php echo e(role_label($role)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="role" value="client_user">
                <div class="field">
                    <label>Role</label>
                    <input type="text" value="<?php echo e(role_label('client_user')); ?>" disabled>
                </div>
            <?php endif; ?>
            <?php if ($actor['role'] === 'system_admin'): ?>
                <div class="field">
                    <label for="site_id">Site</label>
                    <select id="site_id" name="site_id" required>
                        <option value="">Choose A Site</option>
                        <?php foreach ($sites as $site): ?>
                            <option value="<?php echo (int) $site['id']; ?>" <?php echo (int) ($edit['site_id'] ?? ($_POST['site_id'] ?? 0)) === (int) $site['id'] ? 'selected' : ''; ?>>
                                <?php echo e($site['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="field">
                <label for="password"><?php echo $edit ? 'New Password' : 'Password'; ?></label>
                <input id="password" name="password" type="password" <?php echo $edit ? '' : 'required'; ?> minlength="8" autocomplete="new-password">
            </div>
            <div class="field">
                <label for="password_confirm">Confirm Password</label>
                <input id="password_confirm" name="password_confirm" type="password" <?php echo $edit ? '' : 'required'; ?> minlength="8" autocomplete="new-password">
            </div>
        </div>
        <label class="check">
            <input type="checkbox" name="active" value="1" <?php echo ! $edit || (int) $edit['active'] === 1 ? 'checked' : ''; ?>>
            Account Is Active
        </label>
        <div class="form-actions">
            <button class="btn" type="submit"><?php echo $edit ? 'Save Account' : 'Create Account'; ?></button>
            <a class="btn btn-secondary" href="<?php echo e(base_url('users.php')); ?>">Cancel</a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="panel">
    <?php if ($actor['role'] === 'client_admin'): ?>
        <p class="panel-lead">Users at <?php echo e($actor['site_name']); ?>.</p>
    <?php endif; ?>
    <p class="form-actions" style="margin-bottom:16px;">
        <a class="btn" href="<?php echo e(base_url('users.php?new=1')); ?>">Create Account</a>
    </p>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Site</th>
                    <th>Status</th>
                    <th data-nosort="1"></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($people === []): ?>
                    <tr><td colspan="6">No Users To Show Yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($people as $person): ?>
                    <tr>
                        <td><?php echo e($person['name']); ?></td>
                        <td><?php echo e($person['email']); ?></td>
                        <td><?php echo e(role_label($person['role'])); ?></td>
                        <td><?php echo e($person['site_name'] ?: '—'); ?></td>
                        <td><?php echo (int) $person['active'] === 1 ? 'Active' : 'Inactive'; ?></td>
                        <td>
                            <?php if (accounts_can_edit($actor, $person)): ?>
                                <a class="btn btn-sm" href="<?php echo e(base_url('users.php?id=' . (int) $person['id'])); ?>">Edit</a>
                                <?php if ((int) $person['id'] !== (int) $actor['id']): ?>
                                    <form class="inline-form" method="post" action="<?php echo e(base_url('users.php')); ?>" onsubmit="return confirm('Remove this account?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $person['id']; ?>">
                                        <button class="linkish" type="submit">Remove</button>
                                    </form>
                                <?php endif; ?>
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
<?php
require __DIR__ . '/includes/footer-app.php';
