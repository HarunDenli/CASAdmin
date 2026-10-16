<?php

function accounts_creatable_roles(array $actor): array
{
    if ($actor['role'] === 'system_admin') {
        return ['client_admin', 'client_user'];
    }

    if ($actor['role'] === 'client_admin' && ! empty($actor['site_id'])) {
        return ['client_user'];
    }

    return [];
}

function accounts_can_view(array $actor, array $target): bool
{
    if ($actor['role'] === 'system_admin') {
        return true;
    }

    if ($actor['role'] !== 'client_admin' || empty($actor['site_id'])) {
        return false;
    }

    return ($target['role'] ?? '') === 'client_user'
        && (int) ($target['site_id'] ?? 0) === (int) $actor['site_id'];
}

function accounts_can_edit(array $actor, array $target): bool
{
    if (($target['role'] ?? '') === 'system_admin') {
        return false;
    }

    return accounts_can_view($actor, $target);
}

function accounts_list(array $actor): array
{
    $sql = '
        SELECT u.id, u.name, u.email, u.role, u.site_id, u.active, u.created_at, s.name AS site_name
        FROM users u
        LEFT JOIN sites s ON s.id = u.site_id
    ';

    if ($actor['role'] === 'client_admin') {
        $siteId = (int) ($actor['site_id'] ?? 0);
        $sql .= ' WHERE u.role = \'client_user\' AND u.site_id = ' . $siteId;
    }

    $sql .= ' ORDER BY u.role, s.name, u.name';
    $result = db()->query($sql);

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function accounts_get(int $id): ?array
{
    $statement = db()->prepare('
        SELECT u.id, u.name, u.email, u.role, u.site_id, u.active, u.created_at, s.name AS site_name
        FROM users u
        LEFT JOIN sites s ON s.id = u.site_id
        WHERE u.id = ?
        LIMIT 1
    ');
    $statement->bind_param('i', $id);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return $row ?: null;
}

function accounts_email_taken(string $email, ?int $ignoreId = null): bool
{
    if ($ignoreId === null) {
        $statement = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $statement->bind_param('s', $email);
    } else {
        $statement = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $statement->bind_param('si', $email, $ignoreId);
    }

    $statement->execute();
    $exists = (bool) $statement->get_result()->fetch_assoc();
    $statement->close();

    return $exists;
}

function accounts_normalize(array $actor, array $input, bool $requirePassword): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $role = (string) ($input['role'] ?? '');
    $password = (string) ($input['password'] ?? '');
    $confirm = (string) ($input['password_confirm'] ?? '');
    $active = isset($input['active']) ? 1 : 0;
    $allowedRoles = accounts_creatable_roles($actor);

    if ($name === '') {
        return ['ok' => false, 'error' => 'Enter a name.'];
    }

    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid email address.'];
    }

    if (! in_array($role, $allowedRoles, true)) {
        return ['ok' => false, 'error' => 'That role cannot be assigned from this account.'];
    }

    $siteId = null;

    if ($actor['role'] === 'client_admin') {
        $siteId = (int) $actor['site_id'];
        $role = 'client_user';
    } else {
        $siteId = (int) ($input['site_id'] ?? 0);

        if ($siteId < 1) {
            return ['ok' => false, 'error' => 'Choose a site for this account.'];
        }
    }

    if ($requirePassword || $password !== '' || $confirm !== '') {
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
        }

        if ($password !== $confirm) {
            return ['ok' => false, 'error' => 'The two passwords do not match.'];
        }
    }

    return [
        'ok' => true,
        'error' => '',
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'site_id' => $siteId,
        'password' => $password,
        'active' => $active,
    ];
}

function accounts_create(array $actor, array $input): array
{
    $data = accounts_normalize($actor, $input, true);

    if (! $data['ok']) {
        return $data;
    }

    if (accounts_email_taken($data['email'])) {
        return ['ok' => false, 'error' => 'That email is already in use.'];
    }

    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $statement = db()->prepare('INSERT INTO users (name, email, password, role, site_id, active) VALUES (?, ?, ?, ?, ?, ?)');
    $statement->bind_param(
        'ssssii',
        $data['name'],
        $data['email'],
        $hash,
        $data['role'],
        $data['site_id'],
        $data['active']
    );
    $statement->execute();
    $error = $statement->error;
    $statement->close();

    if ($error !== '') {
        return ['ok' => false, 'error' => 'Could not create the account.'];
    }

    return ['ok' => true, 'error' => ''];
}

function accounts_update(array $actor, array $target, array $input): array
{
    if (! accounts_can_edit($actor, $target)) {
        return ['ok' => false, 'error' => 'You cannot change this account.'];
    }

    $input['role'] = $actor['role'] === 'client_admin' ? 'client_user' : (string) ($input['role'] ?? $target['role']);
    $data = accounts_normalize($actor, $input, false);

    if (! $data['ok']) {
        return $data;
    }

    if (accounts_email_taken($data['email'], (int) $target['id'])) {
        return ['ok' => false, 'error' => 'That email is already in use.'];
    }

    $active = $data['active'];

    if ($data['password'] !== '') {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $statement = db()->prepare('UPDATE users SET name = ?, email = ?, password = ?, role = ?, site_id = ?, active = ? WHERE id = ?');
        $id = (int) $target['id'];
        $statement->bind_param('ssssiii', $data['name'], $data['email'], $hash, $data['role'], $data['site_id'], $active, $id);
    } else {
        $statement = db()->prepare('UPDATE users SET name = ?, email = ?, role = ?, site_id = ?, active = ? WHERE id = ?');
        $id = (int) $target['id'];
        $statement->bind_param('sssiii', $data['name'], $data['email'], $data['role'], $data['site_id'], $active, $id);
    }

    $statement->execute();
    $error = $statement->error;
    $statement->close();

    if ($error !== '') {
        return ['ok' => false, 'error' => 'Could not update the account.'];
    }

    return ['ok' => true, 'error' => ''];
}

function accounts_delete(array $actor, array $target): array
{
    if (! accounts_can_edit($actor, $target)) {
        return ['ok' => false, 'error' => 'You cannot remove this account.'];
    }

    if ((int) $target['id'] === (int) $actor['id']) {
        return ['ok' => false, 'error' => 'You cannot remove your own account.'];
    }

    $id = (int) $target['id'];
    $statement = db()->prepare('DELETE FROM users WHERE id = ? AND role <> \'system_admin\' LIMIT 1');
    $statement->bind_param('i', $id);
    $statement->execute();
    $statement->close();

    return ['ok' => true, 'error' => ''];
}
