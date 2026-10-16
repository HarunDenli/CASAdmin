<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/fleet.php';
require_once __DIR__ . '/includes/xlsx.php';

$allowedRoles = ['system_admin', 'client_admin', 'client_user'];
require_role($allowedRoles);

$actor = current_user();
$scope = fleet_viewer_site_id();
$error = '';

function export_column_defs(): array
{
    return [
        'name' => 'Name',
        'vehicle_id' => 'Vehicle ID',
        'site_name' => 'Site',
        'vehicle_type' => 'Vehicle Type',
        'device_model' => 'Device Model',
        'status' => 'Status',
        'connected' => 'Link',
        'last_log' => 'Last Log',
        'device_uid' => 'Device UID',
        'v_computer' => 'VComputer',
        'v_daemon' => 'VDaemon',
    ];
}

function export_selected_columns(array $requested, bool $explicit): array
{
    $defs = export_column_defs();
    $selected = [];

    foreach ($requested as $key) {
        $key = (string) $key;
        if (isset($defs[$key])) {
            $selected[] = $key;
        }
    }

    if ($explicit) {
        return $selected;
    }

    return $selected !== [] ? $selected : array_keys($defs);
}

function export_cell(array $machine, string $key): string
{
    return match ($key) {
        'connected' => fleet_format_connected($machine['connected']),
        'last_log' => fleet_format_datetime($machine['last_log'] ?? null),
        'site_name' => (string) ($machine['site_name'] ?: ''),
        default => (string) ($machine[$key] ?? ''),
    };
}

function export_table(array $machines, array $columns): array
{
    $defs = export_column_defs();
    $headers = [];

    foreach ($columns as $key) {
        $headers[] = $defs[$key];
    }

    $rows = [];

    foreach ($machines as $machine) {
        $row = [];
        foreach ($columns as $key) {
            $row[] = export_cell($machine, $key);
        }
        $rows[] = $row;
    }

    return [$headers, $rows];
}

function export_filename(array $actor, ?int $siteId, string $ext): string
{
    $site = 'all-sites';

    if ($actor['role'] !== 'system_admin') {
        $site = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $actor['site_name'])) ?: 'site';
    } elseif ($siteId) {
        foreach (fleet_sites() as $row) {
            if ((int) $row['id'] === $siteId) {
                $site = preg_replace('/[^a-z0-9]+/i', '-', strtolower($row['name'])) ?: 'site';
                break;
            }
        }
    }

    return 'cas-fleet-' . $site . '-' . date('Y-m-d') . '.' . $ext;
}

function export_resolve_site(array $actor, ?int $scope, string $requestedSite): ?int
{
    if ($actor['role'] !== 'system_admin') {
        return $scope === 0 ? 0 : $scope;
    }

    if ($requestedSite === '' || $requestedSite === 'all') {
        return null;
    }

    return (int) $requestedSite;
}

function export_filter_link(array $machines, string $link): array
{
    if ($link === 'online') {
        return array_values(array_filter($machines, fn ($m) => (int) $m['connected'] === 1));
    }

    if ($link === 'offline') {
        return array_values(array_filter($machines, fn ($m) => (int) $m['connected'] !== 1));
    }

    return $machines;
}

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$columnDefs = export_column_defs();
$explicitCols = isset($input['pick']);
$columns = export_selected_columns(isset($input['cols']) && is_array($input['cols']) ? $input['cols'] : [], $explicitCols);
$link = (string) ($input['link'] ?? 'all');
$format = (string) ($input['format'] ?? 'csv');
$requestedSite = (string) ($input['site_id'] ?? 'all');
$siteId = export_resolve_site($actor, $scope, $requestedSite);
$machines = ($siteId === 0) ? [] : export_filter_link(fleet_machines($siteId), $link);
[$headers, $rows] = export_table($machines, $columns);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($columns === []) {
        http_response_code(400);
        $error = 'Select at least one column to export.';
    } else {
        if ($format === 'xlsx' && class_exists('ZipArchive')) {
            $path = tempnam(sys_get_temp_dir(), 'casx') . '.xlsx';
            xlsx_write_sheet($path, $headers, $rows);
            $name = export_filename($actor, $siteId, 'xlsx');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Content-Length: ' . (string) filesize($path));
            readfile($path);
            @unlink($path);
            exit;
        }

        $name = export_filename($actor, $siteId, 'csv');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}

$sites = $actor['role'] === 'system_admin' ? fleet_sites() : [];
$pageTitle = 'Export';
require __DIR__ . '/includes/header-app.php';
?>
<?php if ($actor['role'] !== 'system_admin' && empty($actor['site_id'])): ?>
    <div class="empty-state">
        <h2>No Site Assigned</h2>
        <p>Ask your administrator to assign a site to this account.</p>
    </div>
<?php else: ?>
<div class="panel">
    <?php if ($actor['role'] !== 'system_admin'): ?>
        <p class="panel-lead">Export for <?php echo e($actor['site_name']); ?>.</p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="error"><?php echo e($error); ?></p>
    <?php endif; ?>

    <?php $filtersOpen = isset($_GET['pick']); ?>
    <div class="export-actions">
        <form method="post" action="<?php echo e(base_url('export.php')); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="site_id" value="<?php echo e($requestedSite); ?>">
            <input type="hidden" name="link" value="<?php echo e($link); ?>">
            <input type="hidden" name="format" value="<?php echo e($format); ?>">
            <input type="hidden" name="pick" value="1">
            <?php foreach ($columns as $key): ?>
                <input type="hidden" name="cols[]" value="<?php echo e($key); ?>">
            <?php endforeach; ?>
            <button class="btn" type="submit" <?php echo $columns === [] ? 'disabled' : ''; ?>>Download Export</button>
        </form>
        <button class="btn btn-secondary" type="button" id="export-filter-toggle" aria-expanded="<?php echo $filtersOpen ? 'true' : 'false'; ?>" aria-controls="export-filters">
            <?php echo $filtersOpen ? 'Hide Filters' : 'Filters'; ?>
        </button>
    </div>

    <div class="export-filters" id="export-filters" <?php echo $filtersOpen ? '' : 'hidden'; ?>>
        <form method="get" action="<?php echo e(base_url('export.php')); ?>" id="export-preview">
            <input type="hidden" name="pick" value="1">
            <div class="form-grid">
                <?php if ($actor['role'] === 'system_admin'): ?>
                    <div class="field">
                        <label for="site_id">Site</label>
                        <select id="site_id" name="site_id" onchange="this.form.submit()">
                            <option value="all" <?php echo $requestedSite === 'all' ? 'selected' : ''; ?>>All Sites</option>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?php echo (int) $site['id']; ?>" <?php echo $requestedSite === (string) $site['id'] ? 'selected' : ''; ?>><?php echo e($site['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="field">
                    <label for="link">Link Status</label>
                    <select id="link" name="link" onchange="this.form.submit()">
                        <option value="all" <?php echo $link === 'all' ? 'selected' : ''; ?>>Online And Offline</option>
                        <option value="online" <?php echo $link === 'online' ? 'selected' : ''; ?>>Online Only</option>
                        <option value="offline" <?php echo $link === 'offline' ? 'selected' : ''; ?>>Offline Only</option>
                    </select>
                </div>
                <div class="field">
                    <label for="format">File Type</label>
                    <select id="format" name="format" onchange="this.form.submit()">
                        <option value="csv" <?php echo $format === 'csv' ? 'selected' : ''; ?>>CSV (Excel, Google Sheets)</option>
                        <?php if (class_exists('ZipArchive')): ?>
                            <option value="xlsx" <?php echo $format === 'xlsx' ? 'selected' : ''; ?>>Excel Workbook (.xlsx)</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <p class="panel-lead">Columns</p>
            <div class="check-grid">
                <?php foreach ($columnDefs as $key => $label): ?>
                    <label class="check">
                        <input type="checkbox" name="cols[]" value="<?php echo e($key); ?>" <?php echo in_array($key, $columns, true) ? 'checked' : ''; ?> onchange="this.form.submit()">
                        <?php echo e($label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-secondary" type="submit">Update Preview</button>
        </form>
    </div>
</div>

<div class="panel">
    <p class="panel-lead"><?php echo count($rows); ?> row(s) · <?php echo count($columns); ?> column(s).</p>
    <div class="table-wrap preview-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?php echo e($header); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($columns === []): ?>
                    <tr><td>Select At Least One Column.</td></tr>
                <?php elseif ($rows === []): ?>
                    <tr><td colspan="<?php echo max(1, count($headers)); ?>">No Machines Match These Filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?php echo e($cell); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php
$pageScripts = <<<'JS'
<script>
(function () {
    var button = document.getElementById('export-filter-toggle');
    var panel = document.getElementById('export-filters');
    if (!button || !panel) {
        return;
    }
    button.addEventListener('click', function () {
        var open = panel.hasAttribute('hidden');
        if (open) {
            panel.removeAttribute('hidden');
        } else {
            panel.setAttribute('hidden', '');
        }
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.textContent = open ? 'Hide Filters' : 'Filters';
    });
})();
</script>
JS;
require __DIR__ . '/includes/footer-app.php';
