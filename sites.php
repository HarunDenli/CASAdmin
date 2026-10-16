<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/fleet.php';

$allowedRoles = ['system_admin'];
require_role($allowedRoles);

$sites = fleet_sites();
$pageTitle = 'Sites';
require __DIR__ . '/includes/header-app.php';
?>
<?php if ($sites === []): ?>
    <div class="empty-state">
        <h2>No Sites Yet</h2>
        <p>Sites will appear here once fleet data is available.</p>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Machines</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $site): ?>
                        <tr>
                            <td><?php echo e($site['name']); ?></td>
                            <td><?php echo (int) $site['machine_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php
require __DIR__ . '/includes/footer-app.php';
