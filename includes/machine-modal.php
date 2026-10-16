<?php
$machineModalOpenVehicle = isset($machineModalOpenVehicle) ? (string) $machineModalOpenVehicle : '';
?>
<div
    class="modal-backdrop"
    id="machine-modal"
    hidden
    <?php if ($machineModalOpenVehicle !== ''): ?>data-open-vehicle="<?php echo e($machineModalOpenVehicle); ?>"<?php endif; ?>
>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="machine-modal-title" tabindex="-1">
        <div class="modal-header">
            <h2 class="modal-title" id="machine-modal-title">Machine</h2>
            <button type="button" class="modal-close" data-modal-close aria-label="Close">×</button>
        </div>
        <div class="machine-detail">
            <div class="machine-photo">
                <img id="machine-modal-image" src="" alt="">
                <p class="machine-photo-caption">
                    <span id="machine-modal-type">Type</span>
                    <span>Type Image</span>
                </p>
            </div>
            <div class="machine-facts">
                <dl class="meta-list machine-meta">
                    <div><dt>Vehicle ID</dt><dd id="machine-modal-vehicle-id">—</dd></div>
                    <div><dt>Site</dt><dd id="machine-modal-site">—</dd></div>
                    <div><dt>Vehicle Type</dt><dd id="machine-modal-vehicle-type">—</dd></div>
                    <div><dt>Device Model</dt><dd id="machine-modal-model">—</dd></div>
                    <div><dt>Status</dt><dd id="machine-modal-status">—</dd></div>
                    <div><dt>Link</dt><dd id="machine-modal-link">—</dd></div>
                    <div><dt>Last Log</dt><dd id="machine-modal-last-log">—</dd></div>
                    <div><dt>Device UID</dt><dd id="machine-modal-uid">—</dd></div>
                    <div><dt>Firmware (VComputer)</dt><dd id="machine-modal-vcomputer">—</dd></div>
                    <div><dt>Daemon (VDaemon)</dt><dd id="machine-modal-vdaemon">—</dd></div>
                </dl>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
        </div>
    </div>
</div>
