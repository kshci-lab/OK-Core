<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/knowledge_group_access.php';
require_once __DIR__ . '/organizational_knowledge_history_store.php';
hcimlab_start_session();
require_once __DIR__ . '/connect_db.php';

function organizational_history_reply(int $code, array $body): never
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    organizational_history_reply(405, ['status'=>'error', 'message'=>'Method Not Allowed']);
}
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    organizational_history_reply(503, ['status'=>'error', 'message'=>'DB接続失敗']);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$historyUser = (int)($_SESSION['USERID'] ?? 0);
$historyAccess = ok_core_resolve_active_group($mysqli, trim((string)($_POST['group_id'] ?? '')), (string)($historyUser ?: ''));
if ($historyAccess['status'] !== 200 || $historyAccess['group_id'] === '') {
    organizational_history_reply($historyAccess['status'] === 200 ? 403 : $historyAccess['status'], ['status'=>'error', 'message'=>'グループを利用できません']);
}
$historyGroup = (int)$historyAccess['group_id'];
