<?php

function db(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($connection->connect_error) {
        exit('Could not connect to MySQL. Check DB settings in includes/config.local.php (Afrihost) or includes/config.php (XAMPP), then run install.php once.');
    }

    $connection->set_charset(DB_CHARSET);

    return $connection;
}
