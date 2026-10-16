<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/fleet.php';
require_once __DIR__ . '/includes/machine-types.php';

$allowedRoles = ['system_admin', 'client_admin'];
require_role($allowedRoles);

$actor = current_user();
$scope = fleet_viewer_site_id();
$canManageCatalog = $actor['role'] === 'system_admin';

$message = '';
$error = '';
$edit = null;
$action = '';
$openModal = '';

if ($actor['role'] === 'client_admin' && empty($actor['site_id'])) {
    $pageTitle = 'Machine Types';
    require __DIR__ . '/includes/header-app.php';
    echo '<div class="empty-state"><h2>No Site Assigned</h2><p>Ask your administrator to assign a site to this account.</p></div>';
    require __DIR__ . '/includes/footer-app.php';
    exit;
}

if (isset($_GET['id'])) {
    $edit = machine_types_get((int) $_GET['id']);
    if ($edit === null || ! machine_types_visible_at_site($edit, $scope)) {
        $edit = null;
        $error = 'That machine type was not found, or it is not used at your site.';
    } else {
        $openModal = 'edit';
    }
}

if ($canManageCatalog && isset($_GET['new'])) {
    $openModal = 'create';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        if (! $canManageCatalog) {
            $error = 'Only a system administrator can add machine types.';
            $openModal = 'create';
        } else {
            $result = machine_types_create($_POST, $_FILES);
            if ($result['ok']) {
                fleet_type_row_clear_cache();
                if (! empty($result['warning'])) {
                    header('Location: ' . base_url('machine-types.php?id=' . (int) $result['id'] . '&warn=1'));
                    exit;
                }
                header('Location: ' . base_url('machine-types.php?created=1'));
                exit;
            }
            $error = $result['error'];
            $openModal = 'create';
        }
    }

    if ($action === 'update') {
        $edit = machine_types_get((int) ($_POST['type_id'] ?? 0));
        if ($edit === null || ! machine_types_visible_at_site($edit, $scope)) {
            $edit = null;
            $error = 'That machine type was not found, or it is not used at your site.';
        } else {
            $payload = $_POST;
            if (! $canManageCatalog) {
                $payload['name'] = $edit['name'];
            }

            $result = machine_types_update($edit, $payload, $_FILES);
            if ($result['ok']) {
                fleet_type_row_clear_cache();
                header('Location: ' . base_url('machine-types.php?saved=1'));
                exit;
            }
            $error = $result['error'];
            $edit = machine_types_get((int) $edit['id']);
            $openModal = 'edit';
        }
    }

    if ($action === 'delete') {
        if (! $canManageCatalog) {
            $error = 'Only a system administrator can remove machine types.';
        } else {
            $target = machine_types_get((int) ($_POST['type_id'] ?? 0));
            if ($target) {
                $result = machine_types_delete($target);
                if ($result['ok']) {
                    header('Location: ' . base_url('machine-types.php?removed=1'));
                    exit;
                }
                $error = $result['error'];
                $edit = $target;
                $openModal = 'edit';
            }
        }
    }
}

if (isset($_GET['created'])) {
    $message = 'Machine Type Created.';
} elseif (isset($_GET['saved'])) {
    $message = 'Machine Type Saved.';
} elseif (isset($_GET['removed'])) {
    $message = 'Machine Type Removed.';
} elseif (isset($_GET['warn'])) {
    $message = 'Type Created. Try the image upload again if needed.';
}

$types = machine_types_list($scope);
$defaultPhoto = base_url('assets/img/fleet/default.svg');
$defaultIcon = base_url('assets/img/fleet/icons/default.svg');
$clientLead = 'You can change the photo and replay icon.';
$openTypeJson = ($openModal === 'edit' && $edit)
    ? json_encode(machine_types_modal_payload($edit, $scope), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
    : '';
$pageTitle = 'Machine Types';
$pageScripts = '<script src="' . e(base_url('assets/js/machine-types-modal.js')) . '?v=7"></script>';
require __DIR__ . '/includes/header-app.php';
?>
<?php if ($message !== ''): ?>
    <p class="notice"><?php echo e($message); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo e($error); ?></p>
<?php endif; ?>

<div class="panel">
    <?php if ($actor['role'] === 'client_admin'): ?>
        <p class="panel-lead">Machine types at <?php echo e($actor['site_name'] ?: 'your site'); ?>.</p>
    <?php endif; ?>
    <?php if ($canManageCatalog): ?>
        <p class="form-actions" style="margin-bottom:16px;">
            <button class="btn" type="button" data-open-type-create>Add Machine Type</button>
        </p>
    <?php endif; ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th data-nosort="1">Photo</th>
                    <th data-nosort="1">Icon</th>
                    <th>Name</th>
                    <th>Source</th>
                    <th>Machines</th>
                    <th data-nosort="1"></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($types === []): ?>
                    <tr><td colspan="6">No Machine Types Yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($types as $type): ?>
                    <?php
                    $payload = machine_types_modal_payload($type, $scope);
                    $payloadJson = json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                    ?>
                    <tr>
                        <td>
                            <img class="type-table-thumb" src="<?php echo e($payload['photo_url']); ?>" alt="">
                        </td>
                        <td>
                            <img class="type-table-icon" src="<?php echo e($payload['icon_url']); ?>" alt="">
                        </td>
                        <td><?php echo e($type['name']); ?></td>
                        <td><?php echo ($type['source'] ?? '') === 'manual' ? 'Manual' : 'Imported'; ?></td>
                        <td><?php echo (int) ($payload['machine_count'] ?? 0); ?></td>
                        <td class="type-actions-cell">
                            <div class="type-row-actions">
                                <button
                                    class="btn btn-sm"
                                    type="button"
                                    data-open-type-edit
                                    data-type="<?php echo e($payloadJson !== false ? $payloadJson : '{}'); ?>"
                                >Edit</button>
                                <?php $canRemove = $canManageCatalog && ($type['source'] ?? '') === 'manual'; ?>
                                <form class="inline-form type-remove-form" method="post" action="<?php echo e(base_url('machine-types.php')); ?>" <?php echo $canRemove ? 'onsubmit="return confirm(\'Remove this manual machine type?\');"' : 'onsubmit="return false;"'; ?>>
                                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="type_id" value="<?php echo (int) $type['id']; ?>">
                                    <button
                                        class="btn btn-sm btn-secondary"
                                        type="submit"
                                        <?php echo $canRemove ? '' : 'disabled'; ?>
                                        title="<?php echo $canRemove ? 'Remove Manual Type' : 'Imported Types Cannot Be Removed'; ?>"
                                    >Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div
    class="modal-backdrop"
    id="machine-type-modal"
    hidden
    data-can-manage="<?php echo $canManageCatalog ? '1' : '0'; ?>"
    data-default-photo="<?php echo e($defaultPhoto); ?>"
    data-default-icon="<?php echo e($defaultIcon); ?>"
    data-client-lead="<?php echo e($clientLead); ?>"
    <?php if ($openModal !== ''): ?>data-open="<?php echo e($openModal); ?>"<?php endif; ?>
    <?php if ($openTypeJson): ?>data-open-type="<?php echo e($openTypeJson); ?>"<?php endif; ?>
    <?php if ($openModal === 'create' && $action === 'create'): ?>data-open-name="<?php echo e((string) ($_POST['name'] ?? '')); ?>"<?php endif; ?>
>
    <div class="modal-dialog modal-dialog-wide" role="dialog" aria-modal="true" aria-labelledby="machine-type-modal-title" tabindex="-1">
        <div class="modal-header">
            <h2 class="modal-title" id="machine-type-modal-title">Machine Type</h2>
            <button type="button" class="modal-close" data-modal-close aria-label="Close">×</button>
        </div>
        <form id="machine-type-form" method="post" action="<?php echo e(base_url('machine-types.php')); ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" id="machine-type-action" value="update">
            <input type="hidden" name="type_id" id="machine-type-id" value="">

            <div class="modal-body">
                <p class="panel-lead" id="machine-type-modal-lead"></p>

                <div class="form-grid">
                    <div class="field">
                        <label for="machine-type-name">Type Name</label>
                        <input id="machine-type-name" name="name" type="text" maxlength="100" value="">
                    </div>
                    <div class="field">
                        <label for="machine-type-source">Source</label>
                        <input id="machine-type-source" type="text" value="" disabled>
                    </div>
                </div>

                <div class="type-media-grid">
                    <div class="type-media-card">
                        <h3 class="type-subhead">Photo</h3>
                        <div class="type-media-preview">
                            <img id="machine-type-photo-preview" src="<?php echo e($defaultPhoto); ?>" alt="Type Photo">
                        </div>
                        <div class="field">
                            <label for="machine-type-photo">Upload Photo</label>
                            <input id="machine-type-photo" name="photo" type="file" accept=".jpg,.jpeg,.png,.webp,.svg,image/*">
                        </div>
                        <label class="check" id="machine-type-clear-photo-wrap" hidden>
                            <input type="checkbox" name="clear_photo" id="machine-type-clear-photo" value="1">
                            Remove Uploaded Photo (Fall Back To Folder File / Default)
                        </label>
                    </div>
                    <div class="type-media-card">
                        <h3 class="type-subhead">Replay Icon</h3>
                        <div class="type-media-preview type-media-preview-icon">
                            <img id="machine-type-icon-preview" src="<?php echo e($defaultIcon); ?>" alt="Replay Icon">
                        </div>
                        <div class="field">
                            <label for="machine-type-icon">Upload Icon</label>
                            <input id="machine-type-icon" name="icon" type="file" accept=".jpg,.jpeg,.png,.webp,.svg,image/*">
                        </div>
                        <label class="check" id="machine-type-clear-icon-wrap" hidden>
                            <input type="checkbox" name="clear_icon" id="machine-type-clear-icon" value="1">
                            Remove Uploaded Icon (Fall Back To Folder File / Default)
                        </label>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn" type="submit" id="machine-type-submit">Save Type</button>
                <button class="btn btn-secondary" type="button" data-modal-close>Cancel</button>
            </div>
        </form>

        <div id="machine-type-delete-wrap" class="modal-delete" hidden>
            <form method="post" action="<?php echo e(base_url('machine-types.php')); ?>" onsubmit="return confirm('Remove this manual machine type?');">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="type_id" id="machine-type-delete-id" value="">
                <button class="linkish" type="submit">Remove Manual Type</button>
            </form>
        </div>
    </div>
</div>
<?php
require __DIR__ . '/includes/footer-app.php';
