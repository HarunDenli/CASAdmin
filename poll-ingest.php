<?php

require_once __DIR__ . '/includes/bootstrap.php';

$isCli = PHP_SAPI === 'cli';

if (! $isCli) {
    $provided = (string) ($_GET['token'] ?? '');

    if ($provided === '' || ! hash_equals(ingest_poll_token(), $provided)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden';
        exit;
    }
}

$result = ingest_poll_drive();
$line = $result['status'] . ': ' . $result['message'];

if ($isCli) {
    fwrite(STDOUT, $line . PHP_EOL);
    exit($result['ok'] ? 0 : 1);
}

header('Content-Type: text/plain; charset=utf-8');
echo $line;
