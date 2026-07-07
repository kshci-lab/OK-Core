<?php

$db_host = getenv('OK_CORE_DB_HOST') ?: 'localhost';
$db_port = getenv('OK_CORE_DB_PORT') ?: 8889;
$db_user = getenv('OK_CORE_DB_USER') ?: 'root';
$db_password = getenv('OK_CORE_DB_PASSWORD') ?: 'root';
$db_dbname = getenv('OK_CORE_DB_NAME') ?: 'ok_core';

$mysqli = new mysqli($db_host, $db_user, $db_password, $db_dbname, (int)$db_port);
if ($mysqli->connect_error) {
    http_response_code(500);
    print('<p>Database connection failed.</p>' . htmlspecialchars($mysqli->connect_error, ENT_QUOTES, 'UTF-8'));
    exit();
}

$mysqli->set_charset('utf8mb4');

?>
