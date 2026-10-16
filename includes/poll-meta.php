<?php
$schedule = ingest_poll_schedule();
$lastPoll = $schedule['last'];
$task = $schedule['task'];
?>
        <div><dt>Poll Interval</dt><dd>Every <?php echo (int) $schedule['interval']; ?> minutes</dd></div>
        <div><dt>Last Poll</dt><dd>
            <?php if ($lastPoll): ?>
                <?php echo e($lastPoll['ran_at']); ?> · <?php echo e(ingest_poll_status_label((string) $lastPoll['status'])); ?>
                <br><?php echo e($lastPoll['message']); ?>
            <?php else: ?>
                Not run yet
            <?php endif; ?>
        </dd></div>
        <div><dt>Next Poll</dt><dd><?php echo e($schedule['next_run']); ?></dd></div>
        <?php if ($task['found']): ?>
            <div><dt>Windows Task</dt><dd><?php echo e(DRIVE_POLL_TASK); ?> · <?php echo e($task['status'] !== '' ? $task['status'] : 'Registered'); ?></dd></div>
        <?php endif; ?>
