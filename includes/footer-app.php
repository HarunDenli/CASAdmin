        </main>
        <?php require __DIR__ . '/site-footer.php'; ?>
    </div>
</div>
<script src="<?php echo e(base_url('assets/js/header-clock.js')); ?>?v=2"></script>
<script src="<?php echo e(base_url('assets/js/nav-toggle.js')); ?>?v=1"></script>
<script src="<?php echo e(base_url('assets/js/tables.js')); ?>?v=2"></script>
<?php if (! empty($pageScripts)) {
    echo $pageScripts;
} ?>
</body>
</html>
