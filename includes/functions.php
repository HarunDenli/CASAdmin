<?php

function base_url(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        $root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $doc = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
        $base = $doc !== '' ? str_replace($doc, '', $root) : '';
        $base = rtrim($base, '/');
    }

    $path = ltrim($path, '/');

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return ($base === '' ? '' : $base) . '/' . $path;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (! is_logged_in()) {
        return null;
    }

    static $cached = null;

    if (is_array($cached) && $cached['id'] === (int) $_SESSION['user_id']) {
        return $cached;
    }

    $id = (int) $_SESSION['user_id'];
    $statement = db()->prepare('
        SELECT u.id, u.name, u.email, u.role, u.site_id, u.active, s.name AS site_name
        FROM users u
        LEFT JOIN sites s ON s.id = u.site_id
        WHERE u.id = ?
        LIMIT 1
    ');
    $statement->bind_param('i', $id);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (! $row || (int) $row['active'] !== 1) {
        $_SESSION = [];
        session_destroy();

        return null;
    }

    $_SESSION['user_name'] = $row['name'];
    $_SESSION['user_email'] = $row['email'];
    $_SESSION['user_role'] = $row['role'];

    $cached = [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'email' => (string) $row['email'],
        'role' => (string) $row['role'],
        'site_id' => $row['site_id'] !== null ? (int) $row['site_id'] : null,
        'site_name' => $row['site_name'] !== null ? (string) $row['site_name'] : null,
        'active' => 1,
    ];

    return $cached;
}

function role_label(string $role): string
{
    return match ($role) {
        'system_admin' => 'System Administrator',
        'client_admin' => 'Client Administrator',
        'client_user' => 'Client User',
        default => $role,
    };
}

function menu_for_role(string $role): array
{
    $dashboard = ['label' => 'Dashboard', 'file' => 'dashboard.php', 'icon' => 'dashboard.svg'];
    $fleet = ['label' => 'Fleet', 'file' => 'fleet.php', 'icon' => 'excavator.svg'];
    $replay = ['label' => 'Log Replay', 'file' => 'log-replay.php', 'icon' => 'replay.svg'];
    $export = ['label' => 'Export', 'file' => 'export.php', 'icon' => 'export.svg'];
    $sites = ['label' => 'Sites', 'file' => 'sites.php', 'icon' => 'sites.svg'];
    $types = ['label' => 'Machine Types', 'file' => 'machine-types.php', 'icon' => 'dumper-truck.svg'];
    $ingest = ['label' => 'Data Ingest', 'file' => 'ingest.php', 'icon' => 'ingest.svg'];
    $messages = ['label' => 'Messages', 'file' => 'messages.php', 'icon' => 'messages.svg'];
    $gallery = ['label' => 'Gallery', 'file' => 'gallery-admin.php', 'icon' => 'gallery.svg'];
    $users = ['label' => 'Users', 'file' => 'users.php', 'icon' => 'users.svg'];

    return match ($role) {
        'system_admin' => [
            $dashboard,
            $fleet,
            $replay,
            $export,
            $sites,
            [
                'label' => 'Admin',
                'icon' => 'admin.svg',
                'children' => [$types, $ingest, $messages, $gallery, $users],
            ],
        ],
        'client_admin' => [
            $dashboard,
            $fleet,
            $replay,
            $export,
            [
                'label' => 'Client Admin',
                'icon' => 'client-admin.svg',
                'children' => [$types, $users],
            ],
        ],
        default => [$dashboard, $fleet, $export],
    };
}

function menu_icon_svg(string $iconFile): string
{
    $iconFile = basename($iconFile);
    if ($iconFile === '') {
        return '';
    }

    $iconPath = __DIR__ . '/../assets/img/nav/' . $iconFile;
    if (! is_file($iconPath)) {
        return '';
    }

    $iconSvg = (string) file_get_contents($iconPath);
    if ($iconSvg === '') {
        return '';
    }

    return preg_replace('/<svg\b/', '<svg class="nav-icon" aria-hidden="true"', $iconSvg, 1) ?? $iconSvg;
}

function menu_section_is_active(array $item, string $currentFile): bool
{
    foreach ($item['children'] ?? [] as $child) {
        if (($child['file'] ?? '') === $currentFile) {
            return true;
        }
    }

    return false;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

function require_role(array $roles): void
{
    require_login();
    $user = current_user();

    if ($user === null) {
        header('Location: ' . base_url('login.php'));
        exit;
    }

    if (! in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $pageTitle = 'Access Denied';
        require __DIR__ . '/header-app.php';
        echo '<div class="empty-state">';
        echo '<h2>Access Denied</h2>';
        echo '<p>You do not have permission to view this page.</p>';
        echo '<p style="margin-top:16px;"><a class="btn" href="' . e(base_url('dashboard.php')) . '">Back To Dashboard</a></p>';
        echo '</div>';
        require __DIR__ . '/footer-app.php';
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf_token'];
}

function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals(csrf_token(), $token)) {
        $ajax = isset($_POST['ajax']) && (string) $_POST['ajax'] === '1';
        $requested = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($ajax || $requested === 'xmlhttprequest') {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Invalid form submission. Refresh the page and try again.']);
            exit;
        }

        exit('Invalid form submission. Please go back and try again.');
    }
}
