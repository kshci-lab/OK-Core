<?php
header('Access-Control-Allow-Origin: http://localhost:8888');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/session_bootstrap.php';
hcimlab_start_session();
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/organizational_knowledge_read.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { ok_read_json(405, ['status' => 'error', 'message' => 'Method Not Allowed']); }
if (!isset($_SESSION['USERID']) || !ctype_digit((string)$_SESSION['USERID'])) { ok_read_json(401, ['status' => 'error', 'message' => 'Authentication required']); }
if (!isset($_GET['group_id']) || !ctype_digit((string)$_GET['group_id']) || (int)$_GET['group_id'] <= 0) { ok_read_json(400, ['status' => 'error', 'message' => 'group_id must be a positive integer']); }
if (!isset($mysqli) || !($mysqli instanceof mysqli)) { ok_read_json(500, ['status' => 'error', 'message' => 'Database connection failed']); }

$userId = (int)$_SESSION['USERID']; $groupId = (int)$_GET['group_id'];
if (!ok_read_group_access($mysqli, $userId, $groupId)) { ok_read_json(403, ['status' => 'error', 'message' => 'Group access denied']); }
try {
    $nodes = ok_read_organizational_knowledge($mysqli, $groupId);
    ok_read_json(200, ['status' => 'ok', 'group_id' => $groupId, 'nodes' => $nodes]);
} catch (Throwable $error) {
    ok_read_json(500, ['status' => 'error', 'message' => 'Failed to read organizational knowledge']);
}
