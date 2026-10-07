<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/knowledge_group_access.php';
hcimlab_start_session();
require_once __DIR__ . '/connect_db.php';

function position_reply(int $code, string $status, string $message, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['status'=>$status,'message'=>$message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') position_reply(405, 'error', 'Method Not Allowed');
if (!isset($mysqli) || !($mysqli instanceof mysqli)) position_reply(503, 'error', 'DB接続失敗');
$mysqli->set_charset('utf8mb4');
$userId = (string)($_SESSION['USERID'] ?? '');
$access = ok_core_resolve_active_group($mysqli, trim((string)($_POST['group_id'] ?? '')), $userId);
if ($access['status'] !== 200 || $access['group_id'] === '') {
    position_reply($access['status'] === 200 ? 403 : $access['status'], 'error', 'グループを利用できません');
}
$groupId = (int)$access['group_id'];
$order = json_decode((string)($_POST['order'] ?? '[]'), true);
$positions = json_decode((string)($_POST['positions'] ?? '[]'), true);
if (!is_array($order) || !is_array($positions)) position_reply(400, 'error', '配置データが不正です');
$items = [];
$requested = ['experience'=>[], 'discussion'=>[]];
foreach ($order as $index => $item) {
    if (!is_array($item)) position_reply(400, 'error', '型付きKF参照が必要です');
    $type = (string)($item['source_type'] ?? '');
    $id = (string)($item['source_id'] ?? '');
    if (!in_array($type, ['experience','discussion'], true) || !ctype_digit($id) || (int)$id <= 0) {
        position_reply(400, 'error', '型付きKF参照が不正です');
    }
    $key = $type.':'.$id;
    $items[$key] = ['type'=>$type, 'id'=>(int)$id, 'x'=>0.0, 'y'=>(float)$index, 'order_only'=>true];
    $requested[$type][] = (int)$id;
}
foreach ($positions as $item) {
    if (!is_array($item)) position_reply(400, 'error', '配置データが不正です');
    $type = (string)($item['source_type'] ?? '');
    $id = (string)($item['source_id'] ?? '');
    $x = $item['x'] ?? null;
    $y = $item['y'] ?? null;
    if (!in_array($type, ['experience','discussion'], true) || !ctype_digit($id) || (int)$id <= 0 ||
        !is_numeric($x) || !is_numeric($y) || !is_finite((float)$x) || !is_finite((float)$y)) {
        position_reply(400, 'error', '配置データが不正です');
    }
    $key = $type.':'.$id;
    $items[$key] = ['type'=>$type, 'id'=>(int)$id, 'x'=>(float)$x, 'y'=>(float)$y, 'order_only'=>false];
    $requested[$type][] = (int)$id;
}
if (!$items) position_reply(400, 'error', '配置対象がありません');
foreach ($requested as $type => $ids) {
    if ($ids && !ok_core_group_has_fragments($mysqli, (string)$groupId, $type, $ids)) {
        position_reply(403, 'error', '選択KFがグループに共有されていません');
    }
}
try {
    $mysqli->begin_transaction();
    $orderStmt = $mysqli->prepare('INSERT INTO knowledge_fragment_positions
        (group_id, fragment_source_type, fragment_source_id, externalized_contents_id, pos_x, pos_y)
        VALUES (?, ?, ?, ?, 0, ?) ON DUPLICATE KEY UPDATE pos_y = VALUES(pos_y)');
    $positionStmt = $mysqli->prepare('INSERT INTO knowledge_fragment_positions
        (group_id, fragment_source_type, fragment_source_id, externalized_contents_id, pos_x, pos_y)
        VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE pos_x = VALUES(pos_x), pos_y = VALUES(pos_y)');
    foreach ($items as $item) {
        $type = $item['type']; $id = $item['id']; $x = $item['x']; $y = $item['y'];
        if ($item['order_only']) {
            $orderStmt->bind_param('isiid', $groupId, $type, $id, $id, $y);
            $orderStmt->execute();
        } else {
            $positionStmt->bind_param('isiidd', $groupId, $type, $id, $id, $x, $y);
            $positionStmt->execute();
        }
    }
    $orderStmt->close();
    $positionStmt->close();
    $mysqli->commit();
    position_reply(200, 'ok', '保存しました', ['items_saved'=>count($items)]);
} catch (Throwable $error) {
    $mysqli->rollback();
    error_log('save_fragment_order: '.$error->getMessage());
    position_reply(500, 'error', '配置の保存に失敗しました');
}
