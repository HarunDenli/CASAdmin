<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/fleet.php';
require_once __DIR__ . '/includes/machine-types.php';
require_once __DIR__ . '/includes/machines.php';

$allowedRoles = ['system_admin', 'client_admin', 'client_user'];
require_role($allowedRoles);

$actor = current_user();
$scope = fleet_viewer_site_id();
$sites = $actor['role'] === 'system_admin' ? fleet_sites() : [];
$siteId = $scope;
$openVehicleId = trim((string) ($_GET['v'] ?? ''));
$canManageMachines = in_array($actor['role'], ['system_admin', 'client_admin'], true);
$canPickSite = $actor['role'] === 'system_admin';
$forcedSiteId = $canPickSite ? null : (int) ($actor['site_id'] ?? 0);

$message = '';
$error = '';
$edit = null;
$action = '';
$openModal = '';

if ($actor['role'] === 'system_admin') {
    $siteId = isset($_GET['site']) && $_GET['site'] !== '' ? (int) $_GET['site'] : null;
}

function fleet_admin_redirect(string $query = ''): string
{
    $url = base_url('fleet.php');
    $params = [];
    $siteFilter = $_GET['site'] ?? $_POST['site'] ?? '';
    if ($siteFilter !== '' && $siteFilter !== null) {
        $params['site'] = (string) $siteFilter;
    }
    if ($query !== '') {
        parse_str($query, $extra);
        $params = array_merge($params, $extra);
    }
    if ($params !== []) {
        $url .= '?' . http_build_query($params);
    }

    return $url;
}

if ($canManageMachines && ! ($actor['role'] === 'client_admin' && empty($actor['site_id']))) {
    if (isset($_GET['id'])) {
        $edit = machines_get((int) $_GET['id']);
        if ($edit === null || ! machines_visible_at_site($edit, $scope)) {
            $edit = null;
            $error = 'That Machine Was Not Found, Or It Is Not At Your Site.';
        } else {
            $openModal = 'edit';
        }
    }

    if (isset($_GET['new'])) {
        $openModal = 'create';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'create') {
            $result = machines_create($_POST, $forcedSiteId);
            if ($result['ok']) {
                header('Location: ' . fleet_admin_redirect('created=1'));
                exit;
            }
            $error = $result['error'];
            $openModal = 'create';
        }

        if ($action === 'update') {
            $edit = machines_get((int) ($_POST['machine_id'] ?? 0));
            if ($edit === null || ! machines_visible_at_site($edit, $scope)) {
                $edit = null;
                $error = 'That Machine Was Not Found, Or It Is Not At Your Site.';
            } else {
                $result = machines_update($edit, $_POST, $forcedSiteId);
                if ($result['ok']) {
                    header('Location: ' . fleet_admin_redirect('saved=1'));
                    exit;
                }
                $error = $result['error'];
                $edit = machines_get((int) $edit['id']);
                $openModal = 'edit';
            }
        }

        if ($action === 'delete') {
            $target = machines_get((int) ($_POST['machine_id'] ?? 0));
            if ($target === null || ! machines_visible_at_site($target, $scope)) {
                $error = 'That Machine Was Not Found, Or It Is Not At Your Site.';
            } else {
                $result = machines_delete($target);
                if ($result['ok']) {
                    header('Location: ' . fleet_admin_redirect('removed=1'));
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
    $message = 'Machine Created.';
} elseif (isset($_GET['saved'])) {
    $message = 'Machine Saved.';
} elseif (isset($_GET['removed'])) {
    $message = 'Machine Removed.';
}

$machines = $scope === 0 ? [] : fleet_machines($siteId);
$adminSites = $canPickSite ? fleet_sites() : ($canManageMachines ? fleet_sites($forcedSiteId) : []);
$types = $canManageMachines ? machine_types_list(null) : [];
$modelOptions = $canManageMachines ? machines_field_options('device_model') : [];
$statusOptions = $canManageMachines ? machines_field_options('status') : [];
$vComputerOptions = $canManageMachines ? machines_field_options('v_computer') : [];
$vDaemonOptions = $canManageMachines ? machines_field_options('v_daemon') : [];
$openMachineJson = ($openModal === 'edit' && $edit)
    ? json_encode(machines_modal_payload($edit), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
    : '';
$posted = [
    'vehicle_id' => (string) ($_POST['vehicle_id'] ?? ''),
    'name' => (string) ($_POST['name'] ?? ''),
    'device_model' => (string) ($_POST['device_model'] ?? ''),
    'vehicle_type' => (string) ($_POST['vehicle_type'] ?? ''),
    'site_id' => (string) ($_POST['site_id'] ?? ''),
    'device_uid' => (string) ($_POST['device_uid'] ?? ''),
    'status' => (string) ($_POST['status'] ?? ''),
    'v_computer' => (string) ($_POST['v_computer'] ?? ''),
    'v_daemon' => (string) ($_POST['v_daemon'] ?? ''),
];

$colSpan = $canManageMachines ? 13 : 11;

$pageTitle = 'Fleet';
$pageScripts = '<script src="' . e(base_url('assets/js/fleet-modal.js')) . '?v=5"></script>';
if ($canManageMachines) {
    $pageScripts .= '<script src="' . e(base_url('assets/js/machines-modal.js')) . '?v=4"></script>';
}
require __DIR__ . '/includes/header-app.php';
?>
<?php if ($message !== ''): ?>
    <p class="notice"><?php echo e($message); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo e($error); ?></p>
<?php endif; ?>

<?php if ($actor['role'] !== 'system_admin' && empty($actor['site_id'])): ?>
    <div class="empty-state">
        <h2>No Site Assigned</h2>
        <p>Ask your administrator to assign a site to this account.</p>
    </div>
<?php elseif ($machines === [] && $siteId === null && ! $canManageMachines): ?>
    <div class="empty-state">
        <h2>No Machines Yet</h2>
        <p>Fleet data will appear here once it is available.</p>
    </div>
<?php else: ?>
    <div class="panel">
        <?php if ($actor['role'] !== 'system_admin'): ?>
            <p class="panel-lead">Machines at <?php echo e($actor['site_name'] ?: 'your site'); ?>.</p>
        <?php endif; ?>
        <?php if ($actor['role'] === 'system_admin'): ?>
            <form class="filter-row" method="get" action="<?php echo e(base_url('fleet.php')); ?>">
                <label for="site">Site</label>
                <select id="site" name="site" onchange="this.form.submit()">
                    <option value="">All Sites</option>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?php echo (int) $site['id']; ?>" <?php echo $siteId === (int) $site['id'] ? 'selected' : ''; ?>>
                            <?php echo e($site['name']); ?> (<?php echo (int) $site['machine_count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <?php if ($canManageMachines): ?>
            <p class="form-actions" style="margin:16px 0 0;">
                <button class="btn" type="button" data-open-machine-create>Add Machine</button>
            </p>
        <?php endif; ?>
    </div>
    <div class="panel">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Vehicle</th>
                        <th>Site</th>
                        <th>Type</th>
                        <th>Model</th>
                        <th>Status</th>
                        <?php if ($canManageMachines): ?>
                            <th>Source</th>
                        <?php endif; ?>
                        <th>Link</th>
                        <th>Last Log</th>
                        <th>Device UID</th>
                        <th>VComputer</th>
                        <th>VDaemon</th>
                        <?php if ($canManageMachines): ?>
                            <th data-nosort="1"></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($machines === []): ?>
                        <tr><td colspan="<?php echo (int) $colSpan; ?>">No Machines Yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($machines as $machine): ?>
                        <?php
                        $ago = fleet_format_ago($machine['last_log'] ?? null);
                        $isManual = ($machine['source'] ?? '') === 'manual';
                        $viewPayload = [
                            'vehicle_id' => (string) $machine['vehicle_id'],
                            'name' => (string) $machine['name'],
                            'site_name' => (string) ($machine['site_name'] ?? ''),
                            'vehicle_type' => (string) $machine['vehicle_type'],
                            'device_model' => (string) $machine['device_model'],
                            'status' => (string) $machine['status'],
                            'connected' => (int) $machine['connected'],
                            'connected_label' => fleet_format_connected($machine['connected']),
                            'ago' => $ago,
                            'ago_class' => $ago !== '' ? fleet_last_log_age_class($machine['last_log'] ?? null) : '',
                            'last_log_fmt' => fleet_format_datetime($machine['last_log'] ?? null),
                            'device_uid' => (string) $machine['device_uid'],
                            'v_computer' => (string) $machine['v_computer'],
                            'v_daemon' => (string) $machine['v_daemon'],
                            'image_url' => fleet_type_image_url((string) $machine['vehicle_type']),
                        ];
                        $viewJson = json_encode($viewPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                        $adminPayload = $canManageMachines ? machines_modal_payload($machine) : null;
                        $adminJson = $adminPayload
                            ? json_encode($adminPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
                            : '';
                        ?>
                        <tr class="table-row-link" tabindex="0" data-machine="<?php echo e($viewJson !== false ? $viewJson : '{}'); ?>">
                            <td><?php echo e($machine['name']); ?></td>
                            <td><?php echo e($machine['vehicle_id']); ?></td>
                            <td><?php echo e($machine['site_name'] ?: '—'); ?></td>
                            <td><?php echo e($machine['vehicle_type']); ?></td>
                            <td><?php echo e($machine['device_model']); ?></td>
                            <td><?php echo e($machine['status']); ?></td>
                            <?php if ($canManageMachines): ?>
                                <td><?php echo $isManual ? 'Manual' : 'Imported'; ?></td>
                            <?php endif; ?>
                            <td data-sort="<?php echo (int) $machine['connected']; ?>">
                                <span class="pill <?php echo ((int) $machine['connected'] === 1) ? 'pill-on' : 'pill-off'; ?>">
                                    <?php echo e(fleet_format_connected($machine['connected'])); ?></span>
                            </td>
                            <td data-sort="<?php echo e((string) ($machine['last_log'] ?? '')); ?>">
                                <?php if ($ago === ''): ?>
                                    —
                                <?php else: ?>
                                    <span class="ago <?php echo e(fleet_last_log_age_class($machine['last_log'] ?? null)); ?>"><?php echo e($ago); ?></span>
                                    <span class="ago-time"><?php echo e(fleet_format_datetime($machine['last_log'])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($machine['device_uid']); ?></td>
                            <td><?php echo e($machine['v_computer']); ?></td>
                            <td><?php echo e($machine['v_daemon']); ?></td>
                            <?php if ($canManageMachines): ?>
                                <td class="type-actions-cell" data-nosort="1">
                                    <div class="type-row-actions" onclick="event.stopPropagation()">
                                        <button
                                            class="btn btn-sm"
                                            type="button"
                                            data-open-machine-edit
                                            data-machine="<?php echo e($adminJson !== false ? $adminJson : '{}'); ?>"
                                        >Edit</button>
                                        <form class="inline-form type-remove-form" method="post" action="<?php echo e(base_url('fleet.php')); ?><?php echo isset($_GET['site']) && $_GET['site'] !== '' ? '?site=' . e((string) $_GET['site']) : ''; ?>" <?php echo $isManual ? 'onsubmit="return confirm(\'Remove this manual machine?\');"' : 'onsubmit="return false;"'; ?>>
                                            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="machine_id" value="<?php echo (int) $machine['id']; ?>">
                                            <?php if (isset($_GET['site']) && $_GET['site'] !== ''): ?>
                                                <input type="hidden" name="site" value="<?php echo e((string) $_GET['site']); ?>">
                                            <?php endif; ?>
                                            <button
                                                class="btn btn-sm btn-secondary"
                                                type="submit"
                                                <?php echo $isManual ? '' : 'disabled'; ?>
                                                title="<?php echo $isManual ? 'Remove Manual Machine' : 'Imported Machines Cannot Be Removed'; ?>"
                                            >Remove</button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    $machineModalOpenVehicle = $openVehicleId;
    require __DIR__ . '/includes/machine-modal.php';
    ?>

    <?php if ($canManageMachines): ?>
<div
    class="modal-backdrop"
    id="machine-admin-modal"
    hidden
    data-can-pick-site="<?php echo $canPickSite ? '1' : '0'; ?>"
    data-forced-site-id="<?php echo $canPickSite ? '' : (int) $forcedSiteId; ?>"
    data-forced-site-name="<?php echo e($canPickSite ? '' : (string) ($actor['site_name'] ?? '')); ?>"
    <?php if ($openModal !== ''): ?>data-open="<?php echo e($openModal); ?>"<?php endif; ?>
    <?php if ($openMachineJson): ?>data-open-machine="<?php echo e($openMachineJson); ?>"<?php endif; ?>
    <?php if ($openModal === 'create' && $action === 'create'): ?>data-open-posted="<?php echo e(json_encode($posted, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?: '{}'); ?>"<?php endif; ?>
>
    <div class="modal-dialog modal-dialog-wide" role="dialog" aria-modal="true" aria-labelledby="machine-admin-modal-title" tabindex="-1">
        <div class="modal-header">
            <h2 class="modal-title" id="machine-admin-modal-title">Machine</h2>
            <button type="button" class="modal-close" data-modal-close aria-label="Close">×</button>
        </div>
        <form id="machine-admin-form" method="post" action="<?php echo e(base_url('fleet.php')); ?><?php echo isset($_GET['site']) && $_GET['site'] !== '' ? '?site=' . e((string) $_GET['site']) : ''; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" id="machine-admin-action" value="update">
            <input type="hidden" name="machine_id" id="machine-admin-id" value="">
            <?php if (isset($_GET['site']) && $_GET['site'] !== ''): ?>
                <input type="hidden" name="site" value="<?php echo e((string) $_GET['site']); ?>">
            <?php endif; ?>

            <div class="modal-body">
                <p class="panel-lead" id="machine-admin-modal-lead"></p>

                <div class="form-grid">
                    <div class="field">
                        <label for="machine-admin-vehicle-id">Vehicle ID</label>
                        <input id="machine-admin-vehicle-id" name="vehicle_id" type="text" maxlength="64" value="">
                    </div>
                    <div class="field">
                        <label for="machine-admin-name">Name</label>
                        <input id="machine-admin-name" name="name" type="text" maxlength="190" value="">
                    </div>
                    <div class="field">
                        <label for="machine-admin-site">Site</label>
                        <?php if ($canPickSite): ?>
                            <select id="machine-admin-site" name="site_id">
                                <option value="">Choose A Site</option>
                                <?php foreach ($adminSites as $site): ?>
                                    <option value="<?php echo (int) $site['id']; ?>"><?php echo e($site['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="hidden" name="site_id" id="machine-admin-site-hidden" value="<?php echo (int) $forcedSiteId; ?>">
                            <input id="machine-admin-site" type="text" value="<?php echo e((string) ($actor['site_name'] ?? '')); ?>" disabled>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="machine-admin-type">Vehicle Type</label>
                        <select id="machine-admin-type" name="vehicle_type">
                            <option value="">—</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?php echo e($type['name']); ?>"><?php echo e($type['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="machine-admin-model">Device Model</label>
                        <select id="machine-admin-model" name="device_model">
                            <option value="">—</option>
                            <?php foreach ($modelOptions as $option): ?>
                                <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="machine-admin-status">Status</label>
                        <select id="machine-admin-status" name="status">
                            <option value="">—</option>
                            <?php foreach ($statusOptions as $option): ?>
                                <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="machine-admin-uid">Device UID</label>
                        <input id="machine-admin-uid" name="device_uid" type="text" maxlength="190" value="">
                    </div>
                    <div class="field">
                        <label for="machine-admin-vcomputer">Firmware (VComputer)</label>
                        <select id="machine-admin-vcomputer" name="v_computer">
                            <option value="">—</option>
                            <?php foreach ($vComputerOptions as $option): ?>
                                <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="machine-admin-vdaemon">Daemon (VDaemon)</label>
                        <select id="machine-admin-vdaemon" name="v_daemon">
                            <option value="">—</option>
                            <?php foreach ($vDaemonOptions as $option): ?>
                                <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="machine-admin-source">Source</label>
                        <input id="machine-admin-source" type="text" value="" disabled>
                    </div>
                    <div class="field">
                        <label for="machine-admin-link">Link</label>
                        <input id="machine-admin-link" type="text" value="" disabled>
                    </div>
                    <div class="field">
                        <label for="machine-admin-last-log">Last Log</label>
                        <input id="machine-admin-last-log" type="text" value="" disabled>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn" type="submit" id="machine-admin-submit">Save Machine</button>
                <button class="btn btn-secondary" type="button" data-modal-close>Cancel</button>
            </div>
        </form>

        <div id="machine-admin-delete-wrap" class="modal-delete" hidden>
            <form method="post" action="<?php echo e(base_url('fleet.php')); ?><?php echo isset($_GET['site']) && $_GET['site'] !== '' ? '?site=' . e((string) $_GET['site']) : ''; ?>" onsubmit="return confirm('Remove this manual machine?');">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="machine_id" id="machine-admin-delete-id" value="">
                <?php if (isset($_GET['site']) && $_GET['site'] !== ''): ?>
                    <input type="hidden" name="site" value="<?php echo e((string) $_GET['site']); ?>">
                <?php endif; ?>
                <button class="linkish" type="submit">Remove Manual Machine</button>
            </form>
        </div>
    </div>
</div>
    <?php endif; ?>
<?php endif; ?>
<?php
require __DIR__ . '/includes/footer-app.php';
