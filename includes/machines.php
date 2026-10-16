<?php

function machines_field_options(string $column): array
{
    $allowed = ['device_model', 'status', 'v_computer', 'v_daemon'];
    if (! in_array($column, $allowed, true)) {
        return [];
    }

    $result = db()->query("
        SELECT DISTINCT TRIM(`{$column}`) AS value
        FROM machines
        WHERE `{$column}` IS NOT NULL AND TRIM(`{$column}`) <> ''
        ORDER BY value ASC
    ");

    if (! $result) {
        return [];
    }

    $values = [];
    while ($row = $result->fetch_assoc()) {
        $value = (string) ($row['value'] ?? '');
        if ($value !== '') {
            $values[] = $value;
        }
    }

    return $values;
}

function machines_get(int $id): ?array
{
    $statement = db()->prepare('
        SELECT m.*, s.name AS site_name
        FROM machines m
        LEFT JOIN sites s ON s.id = m.site_id
        WHERE m.id = ?
        LIMIT 1
    ');
    $statement->bind_param('i', $id);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return $row ?: null;
}

function machines_get_by_vehicle_id(string $vehicleId): ?array
{
    $vehicleId = trim($vehicleId);
    if ($vehicleId === '') {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM machines WHERE vehicle_id = ? LIMIT 1');
    $statement->bind_param('s', $vehicleId);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return $row ?: null;
}

function machines_visible_at_site(array $machine, ?int $siteId): bool
{
    if ($siteId === null) {
        return true;
    }

    if ($siteId === 0) {
        return false;
    }

    return (int) ($machine['site_id'] ?? 0) === (int) $siteId;
}

function machines_modal_payload(array $machine): array
{
    return [
        'id' => (int) ($machine['id'] ?? 0),
        'vehicle_id' => (string) ($machine['vehicle_id'] ?? ''),
        'name' => (string) ($machine['name'] ?? ''),
        'device_model' => (string) ($machine['device_model'] ?? ''),
        'vehicle_type' => (string) ($machine['vehicle_type'] ?? ''),
        'site_id' => $machine['site_id'] !== null ? (int) $machine['site_id'] : null,
        'site_name' => (string) ($machine['site_name'] ?? ''),
        'device_uid' => (string) ($machine['device_uid'] ?? ''),
        'status' => (string) ($machine['status'] ?? ''),
        'v_computer' => (string) ($machine['v_computer'] ?? ''),
        'v_daemon' => (string) ($machine['v_daemon'] ?? ''),
        'source' => (string) ($machine['source'] ?? 'export'),
        'connected' => (int) ($machine['connected'] ?? 0),
        'last_log_fmt' => fleet_format_datetime($machine['last_log'] ?? null),
    ];
}

function machines_normalize_input(array $input): array
{
    return [
        'vehicle_id' => trim((string) ($input['vehicle_id'] ?? '')),
        'name' => trim((string) ($input['name'] ?? '')),
        'device_model' => trim((string) ($input['device_model'] ?? '')),
        'vehicle_type' => trim((string) ($input['vehicle_type'] ?? '')),
        'site_id' => isset($input['site_id']) && $input['site_id'] !== '' ? (int) $input['site_id'] : null,
        'device_uid' => trim((string) ($input['device_uid'] ?? '')),
        'status' => trim((string) ($input['status'] ?? '')),
        'v_computer' => trim((string) ($input['v_computer'] ?? '')),
        'v_daemon' => trim((string) ($input['v_daemon'] ?? '')),
    ];
}

function machines_validate(array $data, ?int $ignoreId = null): array
{
    if ($data['vehicle_id'] === '') {
        return ['ok' => false, 'error' => 'Enter A Vehicle ID.'];
    }

    if (strlen($data['vehicle_id']) > 64) {
        return ['ok' => false, 'error' => 'Vehicle ID Is Too Long.'];
    }

    if ($data['name'] === '') {
        return ['ok' => false, 'error' => 'Enter A Machine Name.'];
    }

    if (strlen($data['name']) > 190) {
        return ['ok' => false, 'error' => 'Machine Name Is Too Long.'];
    }

    if ($data['site_id'] === null || $data['site_id'] <= 0) {
        return ['ok' => false, 'error' => 'Choose A Site.'];
    }

    $siteCheck = db()->prepare('SELECT id FROM sites WHERE id = ? LIMIT 1');
    $siteId = (int) $data['site_id'];
    $siteCheck->bind_param('i', $siteId);
    $siteCheck->execute();
    $siteOk = (bool) $siteCheck->get_result()->fetch_assoc();
    $siteCheck->close();

    if (! $siteOk) {
        return ['ok' => false, 'error' => 'That Site Was Not Found.'];
    }

    $existing = machines_get_by_vehicle_id($data['vehicle_id']);
    if ($existing !== null && (int) $existing['id'] !== (int) ($ignoreId ?? 0)) {
        return ['ok' => false, 'error' => 'That Vehicle ID Is Already In Use.'];
    }

    return ['ok' => true, 'error' => ''];
}

function machines_create(array $input, ?int $forcedSiteId = null): array
{
    $data = machines_normalize_input($input);

    if ($forcedSiteId !== null) {
        $data['site_id'] = $forcedSiteId > 0 ? $forcedSiteId : null;
    }

    $check = machines_validate($data);
    if (! $check['ok']) {
        return $check;
    }

    $source = 'manual';
    $connected = 0;
    $lastLog = null;
    $siteId = (int) $data['site_id'];

    $statement = db()->prepare('
        INSERT INTO machines (
            vehicle_id, name, device_model, connected, last_log, vehicle_type,
            site_id, device_uid, status, v_computer, v_daemon, source
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $statement->bind_param(
        'sssississsss',
        $data['vehicle_id'],
        $data['name'],
        $data['device_model'],
        $connected,
        $lastLog,
        $data['vehicle_type'],
        $siteId,
        $data['device_uid'],
        $data['status'],
        $data['v_computer'],
        $data['v_daemon'],
        $source
    );

    if (! $statement->execute()) {
        $error = $statement->error;
        $statement->close();

        return ['ok' => false, 'error' => 'Could Not Create Machine: ' . $error];
    }

    $id = (int) $statement->insert_id;
    $statement->close();

    return ['ok' => true, 'id' => $id, 'error' => ''];
}

function machines_update(array $machine, array $input, ?int $forcedSiteId = null): array
{
    if (($machine['source'] ?? '') !== 'manual') {
        return ['ok' => false, 'error' => 'Imported Machines Cannot Be Edited.'];
    }

    $data = machines_normalize_input($input);

    if ($forcedSiteId !== null) {
        $data['site_id'] = $forcedSiteId > 0 ? $forcedSiteId : null;
    }

    $id = (int) $machine['id'];
    $check = machines_validate($data, $id);
    if (! $check['ok']) {
        return $check;
    }

    $siteId = (int) $data['site_id'];
    $statement = db()->prepare('
        UPDATE machines
        SET vehicle_id = ?, name = ?, device_model = ?, vehicle_type = ?,
            site_id = ?, device_uid = ?, status = ?, v_computer = ?, v_daemon = ?
        WHERE id = ? AND source = \'manual\'
    ');
    $statement->bind_param(
        'ssssissssi',
        $data['vehicle_id'],
        $data['name'],
        $data['device_model'],
        $data['vehicle_type'],
        $siteId,
        $data['device_uid'],
        $data['status'],
        $data['v_computer'],
        $data['v_daemon'],
        $id
    );

    if (! $statement->execute()) {
        $error = $statement->error;
        $statement->close();

        return ['ok' => false, 'error' => 'Could Not Save Machine: ' . $error];
    }

    $statement->close();

    return ['ok' => true, 'id' => $id, 'error' => ''];
}

function machines_delete(array $machine): array
{
    if (($machine['source'] ?? '') !== 'manual') {
        return ['ok' => false, 'error' => 'Imported Machines Cannot Be Deleted.'];
    }

    $id = (int) $machine['id'];
    $statement = db()->prepare('DELETE FROM machines WHERE id = ? AND source = \'manual\'');
    $statement->bind_param('i', $id);

    if (! $statement->execute()) {
        $error = $statement->error;
        $statement->close();

        return ['ok' => false, 'error' => 'Could Not Remove Machine: ' . $error];
    }

    $statement->close();

    return ['ok' => true, 'error' => ''];
}
