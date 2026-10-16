<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/fleet.php';

$allowedRoles = ['system_admin', 'client_admin'];
require_role($allowedRoles);

$scope = fleet_viewer_site_id();
$fleetLookup = fleet_name_lookup($scope);

$pageTitle = 'Log Replay';
$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">';
require __DIR__ . '/includes/header-app.php';
?>
<div class="panel">
    <p class="panel-lead">Choose a log file from this computer.</p>
    <div class="filter-row">
        <label for="log-file">Log File</label>
        <input id="log-file" type="file">
    </div>
    <p id="replay-status" class="panel-lead" style="margin:12px 0 0;">No File Loaded.</p>
</div>

<div class="replay-layout">
    <div class="replay-stage">
        <div id="replay-map" class="replay-map"></div>
        <div class="replay-controls">
            <label for="replay-basemap">Base</label>
            <select id="replay-basemap">
                <option value="satellite" selected>Satellite</option>
                <option value="map">Map</option>
            </select>
            <button id="replay-play" class="btn btn-sm" type="button" disabled>Play</button>
            <button id="replay-pause" class="btn btn-sm btn-secondary" type="button" disabled>Pause</button>
            <label for="replay-speed">Speed</label>
            <select id="replay-speed" disabled>
                <option value="1">1x</option>
                <option value="5" selected>5x</option>
                <option value="20">20x</option>
            </select>
            <input id="replay-scrub" type="range" min="0" max="0" value="0" disabled>
        </div>
    </div>
    <div class="panel">
        <dl class="meta-list">
            <div><dt>Machine</dt><dd id="stat-machine">—</dd></div>
            <div><dt>Time</dt><dd id="stat-time">—</dd></div>
            <div><dt>Speed</dt><dd id="stat-speed">—</dd></div>
            <div><dt>Heading</dt><dd id="stat-heading">—</dd></div>
            <div><dt>Nearby</dt><dd id="stat-near">—</dd></div>
            <div><dt>Samples</dt><dd id="stat-samples">—</dd></div>
        </dl>
        <p class="panel-lead" style="margin-top:12px;">Nearby Machines</p>
        <p class="panel-hint">Pause to view machine details.</p>
        <ul id="near-list" class="near-list"></ul>
    </div>
</div>
<?php
require __DIR__ . '/includes/machine-modal.php';
$pageScripts = '<script>window.CAS_FLEET = ' . json_encode($fleetLookup, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';</script>'
    . '<script src="' . e(base_url('assets/js/fleet-modal.js')) . '?v=5"></script>'
    . '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>'
    . '<script src="' . e(base_url('assets/js/log-replay.js')) . '?v=15"></script>';
require __DIR__ . '/includes/footer-app.php';
