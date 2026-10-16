<?php

require_once __DIR__ . '/xlsx.php';

function ingest_parse_bool(string $value): int
{
    $normal = strtolower(trim($value));

    return in_array($normal, ['1', 'true', 'yes', 'y', 'connected', 'online'], true) ? 1 : 0;
}

function ingest_parse_datetime(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    if (is_numeric($value)) {
        $serial = (float) $value;

        if ($serial > 20000 && $serial < 80000) {
            $unix = (int) round(($serial - 25569) * 86400);

            return gmdate('Y-m-d H:i:s', $unix);
        }
    }

    $value = str_replace('T', ' ', $value);
    $stamp = strtotime($value);

    if ($stamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $stamp);
}

function ingest_site_id(string $name): ?int
{
    $name = trim($name);

    if ($name === '') {
        return null;
    }

    $db = db();
    $find = $db->prepare('SELECT id FROM sites WHERE name = ? LIMIT 1');
    $find->bind_param('s', $name);
    $find->execute();
    $existing = $find->get_result()->fetch_assoc();
    $find->close();

    if ($existing) {
        return (int) $existing['id'];
    }

    $insert = $db->prepare('INSERT INTO sites (name) VALUES (?)');
    $insert->bind_param('s', $name);
    $insert->execute();
    $id = (int) $insert->insert_id;
    $insert->close();

    return $id;
}

function ingest_excel_from_drive(string $fileId, string $fileName, string $modified, ?int $userId = null): array
{
    if (! drive_is_spreadsheet($fileName, '')) {
        return ['ok' => false, 'error' => 'Only .xlsx files can be imported.', 'row_count' => 0, 'site_count' => 0];
    }

    $download = drive_download_file($fileId);

    if (! $download['ok']) {
        return ['ok' => false, 'error' => $download['error'], 'row_count' => 0, 'site_count' => 0];
    }

    $path = $download['path'];

    try {
        $rows = xlsx_read_rows($path);
    } catch (Throwable $e) {
        @unlink($path);

        return ['ok' => false, 'error' => $e->getMessage(), 'row_count' => 0, 'site_count' => 0];
    }

    @unlink($path);

    $map = [
        'vehicleid' => 'vehicle_id',
        'name' => 'name',
        'devicemodel' => 'device_model',
        'connected' => 'connected',
        'lastlog' => 'last_log',
        'vehicletype' => 'vehicle_type',
        'siteid' => 'site_name',
        'deviceuid' => 'device_uid',
        'status' => 'status',
        'vcomputerv' => 'v_computer',
        'vdaemonv' => 'v_daemon',
    ];

    $db = db();
    $statement = $db->prepare('
        INSERT INTO machines (
            vehicle_id, name, device_model, connected, last_log, vehicle_type,
            site_id, device_uid, status, v_computer, v_daemon, source
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'export\')
        ON DUPLICATE KEY UPDATE
            name = IF(source = \'manual\', name, VALUES(name)),
            device_model = IF(source = \'manual\', device_model, VALUES(device_model)),
            connected = IF(source = \'manual\', connected, VALUES(connected)),
            last_log = IF(source = \'manual\', last_log, VALUES(last_log)),
            vehicle_type = IF(source = \'manual\', vehicle_type, VALUES(vehicle_type)),
            site_id = IF(source = \'manual\', site_id, VALUES(site_id)),
            device_uid = IF(source = \'manual\', device_uid, VALUES(device_uid)),
            status = IF(source = \'manual\', status, VALUES(status)),
            v_computer = IF(source = \'manual\', v_computer, VALUES(v_computer)),
            v_daemon = IF(source = \'manual\', v_daemon, VALUES(v_daemon)),
            source = IF(source = \'manual\', \'manual\', \'export\')
    ');

    $imported = 0;
    $sites = [];

    foreach ($rows as $row) {
        $record = [];

        foreach ($map as $from => $to) {
            $record[$to] = $row[$from] ?? '';
        }

        $vehicleId = trim($record['vehicle_id']);

        if ($vehicleId === '') {
            $vehicleId = trim((string) ($row['id'] ?? ''));
        }

        if ($vehicleId === '') {
            continue;
        }

        $siteId = ingest_site_id($record['site_name']);

        if ($siteId !== null) {
            $sites[$siteId] = true;
        }

        $name = $record['name'];
        $model = $record['device_model'];
        $connected = ingest_parse_bool($record['connected']);
        $lastLog = ingest_parse_datetime($record['last_log']);
        $type = $record['vehicle_type'];
        $uid = $record['device_uid'];
        $status = $record['status'];
        $vComputer = $record['v_computer'];
        $vDaemon = $record['v_daemon'];

        $statement->bind_param(
            'sssississss',
            $vehicleId,
            $name,
            $model,
            $connected,
            $lastLog,
            $type,
            $siteId,
            $uid,
            $status,
            $vComputer,
            $vDaemon
        );
        $statement->execute();

        if ($statement->error !== '') {
            $error = $statement->error;
            $statement->close();

            return ['ok' => false, 'error' => 'Could not save machines: ' . $error, 'row_count' => 0, 'site_count' => 0];
        }

        $imported++;
    }

    $statement->close();
    $siteCount = count($sites);

    $batch = $db->prepare('
        INSERT INTO ingest_batches (drive_file_id, file_name, file_modified, row_count, site_count, ingested_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $batch->bind_param('sssiii', $fileId, $fileName, $modified, $imported, $siteCount, $userId);
    $batch->execute();
    $batch->close();

    require_once __DIR__ . '/fleet.php';
    require_once __DIR__ . '/machine-types.php';
    machine_types_sync_from_fleet();

    return [
        'ok' => true,
        'error' => '',
        'row_count' => $imported,
        'site_count' => $siteCount,
        'file_name' => $fileName,
    ];
}

function ingest_poll_token(): string
{
    $path = POLL_TOKEN_FILE;

    if (is_readable($path)) {
        $token = trim((string) file_get_contents($path));

        if ($token !== '') {
            return $token;
        }
    }

    $token = bin2hex(random_bytes(16));
    @file_put_contents($path, $token);

    return $token;
}

function ingest_record_poll(string $status, string $message, string $fileName = ''): void
{
    $statement = db()->prepare('INSERT INTO ingest_poll_runs (status, message, file_name) VALUES (?, ?, ?)');
    $statement->bind_param('sss', $status, $message, $fileName);
    $statement->execute();
    $statement->close();
}

function ingest_last_poll(): ?array
{
    $result = db()->query('SELECT status, message, file_name, ran_at FROM ingest_poll_runs ORDER BY id DESC LIMIT 1');
    $row = $result ? $result->fetch_assoc() : null;

    return $row ?: null;
}

function ingest_poll_status_label(string $status): string
{
    return match ($status) {
        'imported' => 'Imported',
        'skipped' => 'No Change',
        'busy' => 'Already Running',
        'error' => 'Failed',
        default => $status,
    };
}

function ingest_windows_task_info(): array
{
    $info = [
        'found' => false,
        'status' => '',
        'next_run' => '',
        'last_run' => '',
    ];

    if (PHP_OS_FAMILY !== 'Windows') {
        return $info;
    }

    $command = 'schtasks /Query /TN "' . DRIVE_POLL_TASK . '" /FO LIST /V 2>&1';
    $lines = [];
    @exec($command, $lines);

    foreach ($lines as $line) {
        if (! str_contains($line, ':')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode(':', $line, 2));
        $value = trim($value);

        if ($key === 'Status') {
            $info['found'] = true;
            $info['status'] = $value;
        } elseif ($key === 'Next Run Time') {
            $info['next_run'] = $value;
        } elseif ($key === 'Last Run Time') {
            $info['last_run'] = $value;
        }
    }

    return $info;
}

function ingest_estimated_next_poll(?string $lastRanAt): string
{
    $minutes = max(1, (int) DRIVE_POLL_MINUTES);
    $base = $lastRanAt ? strtotime($lastRanAt) : false;

    if ($base === false) {
        $base = time();
    }

    $next = $base + ($minutes * 60);

    while ($next <= time()) {
        $next += $minutes * 60;
    }

    return date('Y-m-d H:i:s', $next);
}

function ingest_poll_schedule(): array
{
    $last = ingest_last_poll();
    $task = ingest_windows_task_info();
    $next = $task['next_run'];

    if ($next === '' || str_contains(strtolower($next), 'n/a')) {
        $next = ingest_estimated_next_poll($last['ran_at'] ?? null);
    }

    return [
        'last' => $last,
        'task' => $task,
        'next_run' => $next,
        'interval' => (int) DRIVE_POLL_MINUTES,
    ];
}

function ingest_pick_spreadsheet(array $files): ?array
{
    foreach ($files as $file) {
        if (drive_is_spreadsheet((string) ($file['name'] ?? ''), (string) ($file['mimeType'] ?? ''))) {
            return $file;
        }
    }

    return null;
}

function ingest_already_imported(array $file): bool
{
    $fileId = (string) ($file['id'] ?? '');
    $modified = (string) ($file['modifiedTime'] ?? '');

    if ($fileId === '' || $modified === '') {
        return false;
    }

    $statement = db()->prepare('
        SELECT id FROM ingest_batches
        WHERE drive_file_id = ? AND file_modified = ?
        ORDER BY id DESC
        LIMIT 1
    ');
    $statement->bind_param('ss', $fileId, $modified);
    $statement->execute();
    $existing = $statement->get_result()->fetch_assoc();
    $statement->close();

    return (bool) $existing;
}

function ingest_poll_drive(): array
{
    $lock = fopen(POLL_LOCK_FILE, 'c');

    if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) {
            fclose($lock);
        }

        $result = ['ok' => true, 'status' => 'busy', 'message' => 'A poll is already running.', 'file_name' => ''];
        ingest_record_poll('busy', $result['message']);

        return $result;
    }

    try {
        $list = drive_list_files();

        if (! $list['ok']) {
            $result = ['ok' => false, 'status' => 'error', 'message' => $list['error'], 'file_name' => ''];
            ingest_record_poll('error', $result['message']);

            return $result;
        }

        $file = ingest_pick_spreadsheet($list['files'] ?? []);

        if ($file === null) {
            $result = ['ok' => true, 'status' => 'skipped', 'message' => 'No fleet file was found in the folder.', 'file_name' => ''];
            ingest_record_poll('skipped', $result['message']);

            return $result;
        }

        $fileName = (string) ($file['name'] ?? '');

        if (ingest_already_imported($file)) {
            $result = ['ok' => true, 'status' => 'skipped', 'message' => 'File is unchanged. No import needed.', 'file_name' => $fileName];
            ingest_record_poll('skipped', $result['message'], $fileName);

            return $result;
        }

        $import = ingest_excel_from_drive(
            (string) ($file['id'] ?? ''),
            $fileName,
            (string) ($file['modifiedTime'] ?? ''),
            null,
        );

        if (! $import['ok']) {
            $result = ['ok' => false, 'status' => 'error', 'message' => $import['error'], 'file_name' => $fileName];
            ingest_record_poll('error', $result['message'], $fileName);

            return $result;
        }

        $message = 'Imported ' . (int) $import['row_count'] . ' machines from ' . $fileName . '.';
        $result = ['ok' => true, 'status' => 'imported', 'message' => $message, 'file_name' => $fileName];
        ingest_record_poll('imported', $message, $fileName);

        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
