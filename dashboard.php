<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/fleet.php';
require_once __DIR__ . '/includes/ingest.php';

$allowedRoles = ['system_admin', 'client_admin', 'client_user'];
require_role($allowedRoles);

function dash_pie(string $aria, array $segments, string $size = ''): void
{
    if ($segments === []) {
        return;
    }

    $class = 'link-pie' . ($size !== '' ? ' ' . $size : '');
    ?>
    <div class="pie-wrap">
        <div
            class="<?php echo e($class); ?>"
            style="background: <?php echo e(fleet_conic_css($segments)); ?>;"
            role="img"
            aria-label="<?php echo e($aria); ?>"
        ></div>
        <ul class="pie-legend">
            <?php foreach ($segments as $segment): ?>
                <li>
                    <span class="swatch" style="background: <?php echo e($segment['color']); ?>;"></span>
                    <?php echo e($segment['label']); ?>
                    <strong><?php echo (int) $segment['count']; ?></strong>
                    <span><?php echo e((string) $segment['pct']); ?>%</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

function dash_pie_aria(array $segments): string
{
    $parts = [];

    foreach ($segments as $segment) {
        $parts[] = $segment['label'] . ' ' . $segment['count'];
    }

    return implode(', ', $parts);
}

function dash_type_link_bars(array $rows): void
{
    if ($rows === []) {
        return;
    }
    ?>
    <div class="bar-chart">
        <div class="bar-legend-row">
            <span class="swatch swatch-on"></span> Online
            <span class="swatch swatch-off"></span> Offline
        </div>
        <?php foreach ($rows as $row): ?>
            <?php
            $total = max(1, (int) $row['machines']);
            $onlinePct = round(100 * (int) $row['online'] / $total, 1);
            $offlinePct = round(100 - $onlinePct, 1);
            $payloadJson = json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
            ?>
            <div class="bar-row">
                <button
                    type="button"
                    class="bar-label bar-type-link"
                    title="<?php echo e($row['label']); ?> — view type details"
                    data-type="<?php echo e($payloadJson !== false ? $payloadJson : '{}'); ?>"
                ><?php echo e($row['label']); ?></button>
                <div class="bar-track" role="img" aria-label="<?php echo e($row['label']); ?>: <?php echo (int) $row['online']; ?> online, <?php echo (int) $row['offline']; ?> offline">
                    <span class="bar-seg bar-on" style="width: <?php echo e((string) $onlinePct); ?>%;"></span>
                    <span class="bar-seg bar-off" style="width: <?php echo e((string) $offlinePct); ?>%;"></span>
                </div>
                <div class="bar-nums"><?php echo (int) $row['online']; ?> / <?php echo (int) $row['offline']; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

$actor = current_user();
$scope = fleet_viewer_site_id();
$summary = fleet_summary($scope);
$linkSegments = fleet_pie_segments([
    ['label' => 'Online', 'count' => (int) $summary['connected'], 'color' => '#1b6b3a'],
    ['label' => 'Offline', 'count' => (int) $summary['offline'], 'color' => '#c5d4dc'],
]);
$freshSegments = fleet_pie_segments(fleet_freshness_stats($scope));
$typeSegments = fleet_pie_segments(fleet_type_stats($scope));
$modelSegments = fleet_pie_segments(fleet_group_stats('device_model', $scope));
$vComputerSegments = fleet_pie_segments(fleet_group_stats('v_computer', $scope));
$vDaemonSegments = fleet_pie_segments(fleet_group_stats('v_daemon', $scope));
$typeLinkStats = fleet_type_overviews($scope);
$siteStats = $actor['role'] === 'system_admin' ? fleet_site_link_stats() : [];
$schedule = $actor['role'] === 'system_admin' ? ingest_poll_schedule() : null;
$lastPoll = $schedule['last'] ?? null;
$pageTitle = 'Dashboard';
$pageScripts = '<script src="' . e(base_url('assets/js/type-modal.js')) . '?v=2"></script>';
require __DIR__ . '/includes/header-app.php';
?>
<?php if ($actor['role'] !== 'system_admin' && empty($actor['site_id'])): ?>
    <div class="empty-state">
        <h2>No Site Assigned</h2>
        <p>Ask your administrator to assign a site to this account.</p>
    </div>
<?php elseif ($summary['machines'] === 0): ?>
    <div class="empty-state">
        <h2>No Fleet Data Yet</h2>
        <p>Fleet data will appear here once it is available.</p>
    </div>
<?php else: ?>
    <div class="stat-grid">
        <div class="stat-card">
            <span>Machines</span>
            <strong><?php echo (int) $summary['machines']; ?></strong>
        </div>
        <div class="stat-card">
            <span>Online</span>
            <strong><?php echo (int) $summary['connected']; ?></strong>
        </div>
        <div class="stat-card">
            <span>Offline</span>
            <strong><?php echo (int) $summary['offline']; ?></strong>
        </div>
        <div class="stat-card">
            <span>Sites</span>
            <strong><?php echo (int) $summary['sites']; ?></strong>
        </div>
    </div>
    <div class="dash-analysis">
        <div class="panel">
            <p class="panel-lead"><?php echo $actor['role'] === 'system_admin' ? 'Link Status · All Sites' : 'Link Status'; ?></p>
            <?php dash_pie(dash_pie_aria($linkSegments), $linkSegments); ?>
        </div>
        <div class="panel">
            <dl class="meta-list">
                <?php if ($actor['site_name']): ?>
                    <div><dt>Your Site</dt><dd><?php echo e($actor['site_name']); ?></dd></div>
                <?php endif; ?>
                <?php if ($summary['last']): ?>
                    <div><dt>Last Import</dt><dd><?php echo e($summary['last']['file_name']); ?> · <?php echo e($summary['last']['ingested_at']); ?></dd></div>
                <?php endif; ?>
                <?php if ($schedule): ?>
                    <div><dt>Last Poll</dt><dd>
                        <?php if ($lastPoll): ?>
                            <?php echo e($lastPoll['ran_at']); ?> · <?php echo e(ingest_poll_status_label((string) $lastPoll['status'])); ?>
                        <?php else: ?>
                            Not run yet
                        <?php endif; ?>
                    </dd></div>
                    <div><dt>Next Poll</dt><dd><?php echo e($schedule['next_run']); ?></dd></div>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <?php if ($siteStats !== []): ?>
        <div class="panel">
            <p class="panel-lead">Link Status By Site</p>
            <div class="site-pie-grid">
                <?php foreach ($siteStats as $site): ?>
                    <?php
                    $siteSegments = fleet_pie_segments([
                        ['label' => 'Online', 'count' => (int) $site['online'], 'color' => '#1b6b3a'],
                        ['label' => 'Offline', 'count' => (int) $site['offline'], 'color' => '#c5d4dc'],
                    ]);
                    ?>
                    <div class="site-pie-card">
                        <h3><?php echo e($site['name']); ?></h3>
                        <p class="site-pie-count"><?php echo (int) $site['machines']; ?> machines</p>
                        <?php
                        dash_pie(
                            $site['name'] . ': ' . (int) $site['online'] . ' online, ' . (int) $site['offline'] . ' offline',
                            $siteSegments,
                            'link-pie-sm'
                        );
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="dash-chart-grid">
        <?php if ($freshSegments !== []): ?>
            <div class="panel">
                <p class="panel-lead">Time Since Last Log</p>
                <?php dash_pie(dash_pie_aria($freshSegments), $freshSegments); ?>
            </div>
        <?php endif; ?>

        <?php if ($typeSegments !== []): ?>
            <div class="panel">
                <p class="panel-lead">Vehicle Type</p>
                <?php dash_pie(dash_pie_aria($typeSegments), $typeSegments); ?>
            </div>
        <?php endif; ?>

        <?php if ($modelSegments !== []): ?>
            <div class="panel">
                <p class="panel-lead">Device Model</p>
                <?php dash_pie(dash_pie_aria($modelSegments), $modelSegments); ?>
            </div>
        <?php endif; ?>

        <?php if ($vComputerSegments !== []): ?>
            <div class="panel">
                <p class="panel-lead">Firmware Version</p>
                <?php dash_pie(dash_pie_aria($vComputerSegments), $vComputerSegments); ?>
            </div>
        <?php endif; ?>

        <?php if ($vDaemonSegments !== []): ?>
            <div class="panel">
                <p class="panel-lead">Daemon Version</p>
                <?php dash_pie(dash_pie_aria($vDaemonSegments), $vDaemonSegments); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($typeLinkStats !== []): ?>
        <div class="panel">
            <p class="panel-lead">Online Vs Offline By Vehicle Type</p>
            <p class="panel-hint">Click a type for details.</p>
            <?php dash_type_link_bars($typeLinkStats); ?>
        </div>

        <div class="modal-backdrop" id="type-modal" hidden>
            <div class="modal-dialog modal-dialog-wide" role="dialog" aria-modal="true" aria-labelledby="type-modal-title" tabindex="-1">
                <div class="modal-header">
                    <h2 class="modal-title" id="type-modal-title">Vehicle Type</h2>
                    <button type="button" class="modal-close" data-modal-close aria-label="Close">×</button>
                </div>
                <div class="type-detail">
                    <div class="machine-photo">
                        <img id="type-modal-image" src="" alt="">
                        <p class="machine-photo-caption">
                            <span id="type-modal-caption">Type</span>
                            <span>Type Image</span>
                        </p>
                    </div>
                    <div class="type-facts">
                        <div class="type-stat-grid">
                            <div class="type-stat"><span>Machines</span><strong id="type-modal-machines">0</strong></div>
                            <div class="type-stat"><span>Online</span><strong id="type-modal-online">0</strong></div>
                            <div class="type-stat"><span>Offline</span><strong id="type-modal-offline">0</strong></div>
                        </div>
                        <div class="type-split">
                            <div>
                                <h3 class="type-subhead">Sites</h3>
                                <ul class="type-list" id="type-modal-sites"></ul>
                            </div>
                            <div>
                                <h3 class="type-subhead">Device Models</h3>
                                <ul class="type-list" id="type-modal-models"></ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="type-units">
                    <h3 class="type-subhead">Machines Of This Type</h3>
                    <div class="table-wrap type-units-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Vehicle</th>
                                    <th>Site</th>
                                    <th>Link</th>
                                </tr>
                            </thead>
                            <tbody id="type-modal-units"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php
require __DIR__ . '/includes/footer-app.php';
