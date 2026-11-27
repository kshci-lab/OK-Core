<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once __DIR__ . '/../connect_db.php';
require_once __DIR__ . '/okdiag_kf_import_service.php';

okdiag_require_https();
okdiag_require_authentication();
[$payload, $raw] = okdiag_read_json();
if (!isset($mysqli) || !($mysqli instanceof mysqli)) okdiag_json_response(500, ['success'=>false,'message'=>'Database connection failed']);
@$mysqli->set_charset('utf8mb4');
try {
    $result = okdiag_import($mysqli, $payload, $raw);
    okdiag_json_response(200, ['success'=>true,'message'=>'OKDiag KF registered'] + $result);
} catch (Throwable $e) {
    okdiag_json_response(500, ['success'=>false,'message'=>'KF registration failed']);
}
