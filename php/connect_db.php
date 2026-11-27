<?php

// ローカルPCから接続するとき
// $db_host = getenv('OK_CORE_DB_HOST') ?: 'localhost';
// $db_port = getenv('OK_CORE_DB_PORT') ?: 8889;
// $db_user = getenv('OK_CORE_DB_USER') ?: 'root';
// $db_password = getenv('OK_CORE_DB_PASSWORD') ?: 'root';
// $db_dbname = getenv('OK_CORE_DB_NAME') ?: 'ok_core';

//　サーバーに接続するとき
$db_host = getenv('OK_CORE_DB_HOST') ?: 'localhost';
$db_port = getenv('OK_CORE_DB_PORT') ?: 3306;
$db_user = getenv('OK_CORE_DB_USER') ?: 'root';
$db_password = getenv('OK_CORE_DB_PASSWORD') ?: 'kslabkslab';
$db_dbname = getenv('OK_CORE_DB_NAME') ?: 'ok_core';

try {
    $mysqli = @new mysqli($db_host, $db_user, $db_password, $db_dbname, (int)$db_port);
    if ($mysqli && !$mysqli->connect_error) {
        $mysqli->set_charset('utf8mb4');
    } else {
        $db_connection_error = $mysqli ? $mysqli->connect_error : 'Unknown database connection error';
        $mysqli = null;
    }
} catch (mysqli_sql_exception $e) {
    $db_connection_error = $e->getMessage();
    $mysqli = null;
}

?>
