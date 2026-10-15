<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/discussion_history_store.php';
hcimlab_start_session();
require_once __DIR__ . '/connect_db.php';
function discussion_reply(int $code, array $body): never {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (!isset($mysqli) || !($mysqli instanceof mysqli)) discussion_reply(503, ['status'=>'error','message'=>'DB接続失敗']);
$request = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : $_GET;
$access = ok_core_resolve_active_group($mysqli, (string)($request['group_id'] ?? ''), (string)($_SESSION['USERID'] ?? ''));
if ($access['status'] !== 200 || $access['group_id'] === '') discussion_reply($access['status'] === 200 ? 403 : $access['status'], ['status'=>'error','message'=>'グループを利用できません。']);
