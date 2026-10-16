<?php

function fleet_viewer_site_id(): ?int
{
    $user = current_user();

    if ($user === null || $user['role'] === 'system_admin') {
        return null;
    }

    return $user['site_id'] !== null ? (int) $user['site_id'] : 0;
}

function fleet_last_ingest(): ?array
{
    $result = db()->query('SELECT file_name, row_count, site_count, ingested_at FROM ingest_batches ORDER BY id DESC LIMIT 1');
    $row = $result ? $result->fetch_assoc() : null;

    return $row ?: null;
}

function fleet_summary(?int $siteId = null): array
{
    $db = db();

    if ($siteId === 0) {
        return [
            'machines' => 0,
            'connected' => 0,
            'offline' => 0,
            'sites' => 0,
            'active' => 0,
            'last' => fleet_last_ingest(),
        ];
    }

    $where = $siteId === null ? '' : (' WHERE site_id = ' . (int) $siteId);
    $andConnected = $siteId === null ? ' WHERE connected = 1' : (' WHERE site_id = ' . (int) $siteId . ' AND connected = 1');
    $andActive = $siteId === null
        ? " WHERE status = 'Active'"
        : (' WHERE site_id = ' . (int) $siteId . " AND status = 'Active'");

    $machines = (int) $db->query('SELECT COUNT(*) AS n FROM machines' . $where)->fetch_assoc()['n'];
    $connected = (int) $db->query('SELECT COUNT(*) AS n FROM machines' . $andConnected)->fetch_assoc()['n'];
    $sites = $siteId === null
        ? (int) $db->query('SELECT COUNT(*) AS n FROM sites')->fetch_assoc()['n']
        : 1;
    $active = (int) $db->query('SELECT COUNT(*) AS n FROM machines' . $andActive)->fetch_assoc()['n'];

    return [
        'machines' => $machines,
        'connected' => $connected,
        'offline' => $machines - $connected,
        'sites' => $sites,
        'active' => $active,
        'last' => fleet_last_ingest(),
    ];
}

function fleet_sites(?int $siteId = null): array
{
    $sql = '
        SELECT s.id, s.name, COUNT(m.id) AS machine_count
        FROM sites s
        LEFT JOIN machines m ON m.site_id = s.id
    ';

    if ($siteId !== null) {
        $sql .= ' WHERE s.id = ' . (int) $siteId;
    }

    $sql .= ' GROUP BY s.id, s.name ORDER BY s.name';
    $result = db()->query($sql);

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fleet_machines(?int $siteId = null): array
{
    $sql = '
        SELECT m.id, m.vehicle_id, m.name, m.device_model, m.connected, m.last_log,
               m.vehicle_type, m.device_uid, m.status, m.v_computer, m.v_daemon,
               m.source, m.site_id, s.name AS site_name
        FROM machines m
        LEFT JOIN sites s ON s.id = m.site_id
    ';

    if ($siteId !== null) {
        $sql .= ' WHERE m.site_id = ' . $siteId;
    }

    $sql .= ' ORDER BY s.name, m.name';
    $result = db()->query($sql);

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fleet_type_image_slug(string $vehicleType): string
{
    $slug = strtolower(trim($vehicleType));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'default';
}

function fleet_type_image_url_files(string $vehicleType): string
{
    $dir = dirname(__DIR__) . '/assets/img/fleet';
    $slug = fleet_type_image_slug($vehicleType);
    $extensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

    foreach ([$slug, 'default'] as $name) {
        foreach ($extensions as $ext) {
            $path = $dir . '/' . $name . '.' . $ext;

            if (is_readable($path)) {
                return base_url('assets/img/fleet/' . $name . '.' . $ext);
            }
        }
    }

    return base_url('assets/img/fleet/default.svg');
}

function fleet_type_row_clear_cache(): void
{
    fleet_type_row('', true);
}

function fleet_type_row(string $vehicleType, bool $clear = false): ?array
{
    static $cache = [];

    if ($clear) {
        $cache = [];

        return null;
    }

    $name = trim($vehicleType);
    if ($name === '') {
        return null;
    }

    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }

    $statement = db()->prepare('SELECT * FROM machine_types WHERE name = ? LIMIT 1');
    if ($statement === false) {
        $cache[$name] = null;

        return null;
    }

    $statement->bind_param('s', $name);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();
    $cache[$name] = $row ?: null;

    return $cache[$name];
}

function fleet_type_image_url(string $vehicleType): string
{
    $type = fleet_type_row($vehicleType);
    $photo = trim((string) ($type['photo_file'] ?? ''));

    if ($photo !== '') {
        $path = dirname(__DIR__) . '/assets/img/fleet/uploads/' . basename($photo);
        if (is_readable($path)) {
            return base_url('assets/img/fleet/uploads/' . basename($photo));
        }
    }

    return fleet_type_image_url_files($vehicleType);
}

function fleet_type_icon_url(string $vehicleType): string
{
    $type = fleet_type_row($vehicleType);
    $icon = trim((string) ($type['icon_file'] ?? ''));

    if ($icon !== '') {
        $path = dirname(__DIR__) . '/assets/img/fleet/uploads/' . basename($icon);
        if (is_readable($path)) {
            return base_url('assets/img/fleet/uploads/' . basename($icon));
        }
    }

    $slug = fleet_type_image_slug($vehicleType);
    $dir = dirname(__DIR__) . '/assets/img/fleet/icons';
    $extensions = ['png', 'webp', 'jpg', 'jpeg', 'svg'];

    foreach ([$slug, 'default'] as $name) {
        foreach ($extensions as $ext) {
            $path = $dir . '/' . $name . '.' . $ext;
            if (is_readable($path)) {
                return base_url('assets/img/fleet/icons/' . $name . '.' . $ext);
            }
        }
    }

    return base_url('assets/img/fleet/icons/default.svg');
}

function fleet_format_connected(int|string $value): string
{
    return ((int) $value === 1) ? 'Online' : 'Offline';
}

function fleet_format_datetime(?string $value): string
{
    if ($value === null || $value === '' || str_starts_with($value, '0000-00-00')) {
        return '—';
    }

    return substr($value, 0, 19);
}

function fleet_format_ago(?string $value): string
{
    if (fleet_format_datetime($value) === '—') {
        return '';
    }

    try {
        $then = new DateTime(substr($value, 0, 19));
        $now = new DateTime('now');
    } catch (Exception) {
        return '';
    }

    $seconds = $now->getTimestamp() - $then->getTimestamp();

    if ($seconds < 0) {
        $seconds = 0;
    }

    if ($seconds < 60) {
        return 'Just Now';
    }

    if ($seconds < 3600) {
        $minutes = intdiv($seconds, 60);

        return $minutes === 1 ? '1 min ago' : $minutes . ' min ago';
    }

    if ($seconds < 86400) {
        $hours = intdiv($seconds, 3600);

        return $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
    }

    $days = intdiv($seconds, 86400);

    if ($days < 30) {
        return $days === 1 ? '1 day ago' : $days . ' days ago';
    }

    $months = intdiv($days, 30);

    if ($months < 12) {
        return $months === 1 ? '1 month ago' : $months . ' months ago';
    }

    $years = intdiv($days, 365);

    return $years === 1 ? '1 year ago' : $years . ' years ago';
}

function fleet_last_log_age_class(?string $value): string
{
    if (fleet_format_datetime($value) === '—') {
        return '';
    }

    try {
        $then = new DateTime(substr($value, 0, 19));
        $now = new DateTime('now');
    } catch (Exception) {
        return '';
    }

    $seconds = $now->getTimestamp() - $then->getTimestamp();

    if ($seconds < 86400) {
        return 'ago-ok';
    }

    if ($seconds < 86400 * 3) {
        return 'ago-warn';
    }

    return 'ago-old';
}

function fleet_site_link_stats(?int $siteId = null): array
{
    $sql = '
        SELECT s.id, s.name,
               COUNT(m.id) AS machines,
               COALESCE(SUM(m.connected = 1), 0) AS online
        FROM sites s
        LEFT JOIN machines m ON m.site_id = s.id
    ';

    if ($siteId !== null) {
        $sql .= ' WHERE s.id = ' . (int) $siteId;
    }

    $sql .= ' GROUP BY s.id, s.name ORDER BY s.name';
    $result = db()->query($sql);
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stats = [];

    foreach ($rows as $row) {
        $machines = (int) $row['machines'];

        if ($machines === 0) {
            continue;
        }

        $online = (int) $row['online'];
        $stats[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'machines' => $machines,
            'online' => $online,
            'offline' => $machines - $online,
        ];
    }

    return $stats;
}

function fleet_type_stats(?int $siteId = null): array
{
    return fleet_group_stats('vehicle_type', $siteId);
}

function fleet_group_stats(string $column, ?int $siteId = null): array
{
    $allowed = ['vehicle_type', 'device_model', 'v_computer', 'v_daemon', 'status'];

    if (! in_array($column, $allowed, true)) {
        return [];
    }

    if ($siteId === 0) {
        return [];
    }

    $sql = "
        SELECT CASE
                 WHEN `{$column}` IS NULL OR TRIM(`{$column}`) = '' THEN 'Unknown'
                 ELSE `{$column}`
               END AS label,
               COUNT(*) AS n
        FROM machines
    ";

    if ($siteId !== null) {
        $sql .= ' WHERE site_id = ' . (int) $siteId;
    }

    $sql .= " GROUP BY CASE
                 WHEN `{$column}` IS NULL OR TRIM(`{$column}`) = '' THEN 'Unknown'
                 ELSE `{$column}`
               END
               ORDER BY n DESC, label";
    $result = db()->query($sql);
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stats = [];

    foreach ($rows as $row) {
        $stats[] = [
            'label' => (string) $row['label'],
            'count' => (int) $row['n'],
        ];
    }

    return $stats;
}

function fleet_freshness_stats(?int $siteId = null): array
{
    if ($siteId === 0) {
        return [];
    }

    $fresh = 0;
    $warn = 0;
    $old = 0;
    $unknown = 0;

    foreach (fleet_machines($siteId) as $machine) {
        $class = fleet_last_log_age_class($machine['last_log'] ?? null);

        if ($class === 'ago-ok') {
            $fresh++;
        } elseif ($class === 'ago-warn') {
            $warn++;
        } elseif ($class === 'ago-old') {
            $old++;
        } else {
            $unknown++;
        }
    }

    $stats = [
        ['label' => 'Last 24 Hours', 'count' => $fresh, 'color' => '#1b6b3a'],
        ['label' => '1–3 days', 'count' => $warn, 'color' => '#c47b16'],
        ['label' => 'Older Than 3 Days', 'count' => $old, 'color' => '#c0392b'],
    ];

    if ($unknown > 0) {
        $stats[] = ['label' => 'No Last Log', 'count' => $unknown, 'color' => '#9aaeb8'];
    }

    return array_values(array_filter($stats, fn ($row) => (int) $row['count'] > 0));
}

function fleet_type_overviews(?int $siteId = null): array
{
    if ($siteId === 0) {
        return [];
    }

    $types = [];

    foreach (fleet_machines($siteId) as $machine) {
        $label = trim((string) $machine['vehicle_type']);
        if ($label === '') {
            $label = 'Unknown';
        }

        if (! isset($types[$label])) {
            $types[$label] = [
                'label' => $label,
                'machines' => 0,
                'online' => 0,
                'offline' => 0,
                'image_url' => fleet_type_image_url($label === 'Unknown' ? '' : $label),
                'sites' => [],
                'models' => [],
                'units' => [],
            ];
        }

        $online = (int) $machine['connected'] === 1;
        $types[$label]['machines']++;
        if ($online) {
            $types[$label]['online']++;
        } else {
            $types[$label]['offline']++;
        }

        $siteName = trim((string) ($machine['site_name'] ?? ''));
        if ($siteName === '') {
            $siteName = '—';
        }

        if (! isset($types[$label]['sites'][$siteName])) {
            $types[$label]['sites'][$siteName] = [
                'name' => $siteName,
                'machines' => 0,
                'online' => 0,
                'offline' => 0,
            ];
        }

        $types[$label]['sites'][$siteName]['machines']++;
        if ($online) {
            $types[$label]['sites'][$siteName]['online']++;
        } else {
            $types[$label]['sites'][$siteName]['offline']++;
        }

        $model = trim((string) $machine['device_model']);
        if ($model === '') {
            $model = 'Unknown';
        }
        $types[$label]['models'][$model] = ($types[$label]['models'][$model] ?? 0) + 1;

        $types[$label]['units'][] = [
            'name' => (string) $machine['name'],
            'vehicle_id' => (string) $machine['vehicle_id'],
            'site_name' => $siteName,
            'connected' => (int) $machine['connected'],
            'connected_label' => fleet_format_connected($machine['connected']),
            'status' => (string) $machine['status'],
        ];
    }

    $rows = array_values($types);

    foreach ($rows as &$row) {
        $sites = array_values($row['sites']);
        usort($sites, static function (array $a, array $b): int {
            return $b['machines'] <=> $a['machines'] ?: strcmp($a['name'], $b['name']);
        });
        $row['sites'] = $sites;

        $models = [];
        foreach ($row['models'] as $name => $count) {
            $models[] = ['label' => (string) $name, 'count' => (int) $count];
        }
        usort($models, static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']);
        });
        $row['models'] = $models;

        usort($row['units'], static function (array $a, array $b): int {
            return strcmp($a['name'], $b['name']) ?: strcmp($a['vehicle_id'], $b['vehicle_id']);
        });
    }
    unset($row);

    usort($rows, static function (array $a, array $b): int {
        return $b['machines'] <=> $a['machines'] ?: strcmp($a['label'], $b['label']);
    });

    return $rows;
}

function fleet_pie_palette(): array
{
    return ['#0082cf', '#1b6b3a', '#c47b16', '#6b4c9a', '#c0392b', '#2a8f9d', '#4a6270', '#d4a017', '#3d7ea6', '#7a8f3d'];
}

function fleet_pie_segments(array $items): array
{
    $colors = fleet_pie_palette();
    $total = 0;

    foreach ($items as $item) {
        $total += (int) $item['count'];
    }

    $segments = [];
    $cursor = 0.0;
    $last = count($items) - 1;

    foreach ($items as $i => $item) {
        $count = (int) $item['count'];
        $pct = $total > 0 ? round(100 * $count / $total, 1) : 0.0;
        $end = $i === $last ? 100.0 : min(100.0, round($cursor + $pct, 1));

        if ($i === $last) {
            $pct = round(100 - $cursor, 1);
        }

        $segments[] = [
            'label' => (string) $item['label'],
            'count' => $count,
            'pct' => $total > 0 ? round(100 * $count / $total, 1) : 0.0,
            'color' => isset($item['color']) ? (string) $item['color'] : $colors[$i % count($colors)],
            'start' => $cursor,
            'end' => $end,
        ];
        $cursor = $end;
    }

    return $segments;
}

function fleet_conic_css(array $segments): string
{
    if ($segments === []) {
        return 'conic-gradient(#c5d4dc 0 100%)';
    }

    $stops = [];

    foreach ($segments as $segment) {
        $stops[] = $segment['color'] . ' ' . $segment['start'] . '% ' . $segment['end'] . '%';
    }

    return 'conic-gradient(' . implode(', ', $stops) . ')';
}

function fleet_name_lookup(?int $siteId = null): array
{
    if ($siteId === 0) {
        return [];
    }

    $lookup = [];

    foreach (fleet_machines($siteId) as $machine) {
        $ago = fleet_format_ago($machine['last_log'] ?? null);
        $vehicleId = (string) $machine['vehicle_id'];
        $lookup[$vehicleId] = [
            'vehicle_id' => $vehicleId,
            'name' => (string) $machine['name'],
            'uid' => (string) $machine['device_uid'],
            'type' => (string) $machine['vehicle_type'],
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
            'icon_url' => fleet_type_icon_url((string) $machine['vehicle_type']),
        ];
    }

    return $lookup;
}
