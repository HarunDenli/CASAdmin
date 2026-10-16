<?php

if (is_readable(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
    return;
}

// XAMPP defaults. On Afrihost only, upload includes/config.afrihost.php as includes/config.local.php.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'cas_portal');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'CAS Admin Portal');

define('CONTACT_ADDRESS', '68 Grayston Dr, Sandown, Sandton, 2031, Johannesburg');
define('CONTACT_EMAIL', 'info@minetec.global');
define('CONTACT_PHONE', '+27 11 684 1120');
define('CONTACT_PHONE_TEL', '+27116841120');
define('CONTACT_MAP_QUERY', '68 Grayston Drive, Sandown, Sandton, 2031, South Africa');

// Landing page video. Prefer a local MP4 at assets/video/minetec-cas.mp4 if present.
// Otherwise the YouTube ID below is embedded. Change this ID to your preferred Minetec video.
define('LANDING_YOUTUBE_ID', 'iJaFdZYS9Co');

define('DRIVE_KEY_FILE', 'C:/xampp/cas-secrets/minetec-cas-admin-3966737856cc.json');
define('DRIVE_FOLDER_ID', '1f-8a1AUFYlBIvSspBS9FqWKi1xui-ppa');
define('DRIVE_SERVICE_EMAIL', 'cas-admin-service@minetec-cas-admin.iam.gserviceaccount.com');
define('DRIVE_POLL_MINUTES', 10);
define('DRIVE_POLL_TASK', 'CASPortalDrivePoll');
define('POLL_TOKEN_FILE', 'C:/xampp/cas-secrets/poll-token.txt');
define('POLL_LOCK_FILE', 'C:/xampp/cas-secrets/poll-ingest.lock');
