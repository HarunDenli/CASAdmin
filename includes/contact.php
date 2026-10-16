<?php

if (! defined('CONTACT_ADDRESS')) {
    define('CONTACT_ADDRESS', '68 Grayston Dr, Sandown, Sandton, 2031, Johannesburg');
}
if (! defined('CONTACT_EMAIL')) {
    define('CONTACT_EMAIL', 'info@minetec.global');
}
if (! defined('CONTACT_PHONE')) {
    define('CONTACT_PHONE', '+27 11 684 1120');
}
if (! defined('CONTACT_PHONE_TEL')) {
    define('CONTACT_PHONE_TEL', '+27116841120');
}
if (! defined('CONTACT_MAP_QUERY')) {
    define('CONTACT_MAP_QUERY', '68 Grayston Drive, Sandown, Sandton, 2031, South Africa');
}

function contact_map_embed_url(): string
{
    $query = defined('CONTACT_MAP_QUERY') ? (string) CONTACT_MAP_QUERY : (string) CONTACT_ADDRESS;

    return 'https://maps.google.com/maps?q=' . rawurlencode($query) . '&z=15&output=embed';
}

/**
 * Accepts common South African formats, e.g. 011 684 1120, 0821234567, +27 11 684 1120.
 * Returns a normalised +27… string, '' when empty, or null when invalid.
 */
function contact_normalize_sa_phone(string $phone): ?string
{
    $phone = trim($phone);
    if ($phone === '') {
        return '';
    }

    if (strlen($phone) > 40) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return null;
    }

    if (str_starts_with($digits, '0027')) {
        $digits = substr($digits, 2);
    }

    if (preg_match('/^0[1-9]\d{8}$/', $digits) === 1) {
        $digits = '27' . substr($digits, 1);
    }

    if (preg_match('/^27[1-9]\d{8}$/', $digits) !== 1) {
        return null;
    }

    return '+' . $digits;
}

function contact_submit(array $input): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $phoneRaw = trim((string) ($input['phone'] ?? ''));
    $subject = trim((string) ($input['subject'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));

    if ($name === '' || strlen($name) > 120) {
        return ['ok' => false, 'error' => 'Enter Your Name.', 'field' => 'name'];
    }

    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        return ['ok' => false, 'error' => 'Enter A Valid Email Address.', 'field' => 'email'];
    }

    if ($phoneRaw === '') {
        return ['ok' => false, 'error' => 'Enter A Valid Phone Number.', 'field' => 'phone'];
    }

    $phone = contact_normalize_sa_phone($phoneRaw);
    if ($phone === null || $phone === '') {
        return ['ok' => false, 'error' => 'Enter A Valid Phone Number.', 'field' => 'phone'];
    }

    if ($subject === '') {
        $subject = 'CAS Portal Enquiry';
    }

    if (strlen($subject) > 190) {
        return ['ok' => false, 'error' => 'Subject Is Too Long.', 'field' => 'subject'];
    }

    if ($message === '') {
        return ['ok' => false, 'error' => 'Enter A Message.', 'field' => 'message'];
    }

    if (strlen($message) > 5000) {
        return ['ok' => false, 'error' => 'Message Is Too Long.', 'field' => 'message'];
    }

    $statement = db()->prepare('
        INSERT INTO contact_messages (name, email, phone, subject, message)
        VALUES (?, ?, ?, ?, ?)
    ');
    $statement->bind_param('sssss', $name, $email, $phone, $subject, $message);

    if (! $statement->execute()) {
        $statement->close();

        return ['ok' => false, 'error' => 'Could Not Save Your Message. Please Try Again.', 'field' => ''];
    }

    $statement->close();

    return ['ok' => true, 'error' => '', 'field' => ''];
}

function contact_messages_list(): array
{
    $result = db()->query('
        SELECT id, name, email, phone, subject, message, created_at
        FROM contact_messages
        ORDER BY created_at DESC, id DESC
    ');

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function contact_message_get(int $id): ?array
{
    $statement = db()->prepare('
        SELECT id, name, email, phone, subject, message, created_at
        FROM contact_messages
        WHERE id = ?
        LIMIT 1
    ');
    $statement->bind_param('i', $id);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return $row ?: null;
}

function contact_message_delete(int $id): array
{
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'That Message Was Not Found.'];
    }

    $statement = db()->prepare('DELETE FROM contact_messages WHERE id = ?');
    $statement->bind_param('i', $id);

    if (! $statement->execute()) {
        $statement->close();

        return ['ok' => false, 'error' => 'Could Not Remove That Message.'];
    }

    $removed = $statement->affected_rows > 0;
    $statement->close();

    if (! $removed) {
        return ['ok' => false, 'error' => 'That Message Was Not Found.'];
    }

    return ['ok' => true, 'error' => ''];
}
