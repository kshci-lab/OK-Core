<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/knowledge_group_access.php';
hcimlab_start_session();
require_once __DIR__ . '/connect_db.php';

function node_update_reply(int $code, string $status, string $message, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['status'=>$status,'message'=>$message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') node_update_reply(405, 'error', 'Method Not Allowed');
if (!isset($mysqli) || !($mysqli instanceof mysqli)) node_update_reply(503, 'error', 'DB接続失敗');
$mysqli->set_charset('utf8mb4');
$userId = (string)($_SESSION['USERID'] ?? '');
$access = ok_core_resolve_active_group($mysqli, trim((string)($_POST['group_id'] ?? '')), $userId);
if ($access['status'] !== 200 || $access['group_id'] === '') {
    node_update_reply($access['status'] === 200 ? 403 : $access['status'], 'error', 'グループを利用できません');
}
$groupId = (int)$access['group_id'];
$nodeRaw = trim((string)($_POST['node_id'] ?? ''));
$title = trim((string)($_POST['node_title'] ?? ''));
if (!ctype_digit($nodeRaw) || (int)$nodeRaw <= 0 || $title === '' ||
    (function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : preg_match_all('/./us', $title)) > 255) {
    node_update_reply(400, 'error', 'node_id またはタイトルが不正です');
}
$nodeId = (int)$nodeRaw;
$sourceType = strtolower(trim((string)($_POST['fragment_source_type'] ?? 'experience')));
if ($sourceType === 'externalized') $sourceType = 'discussion';
if (!in_array($sourceType, ['experience', 'discussion'], true)) node_update_reply(400, 'error', 'fragment_source_type が不正です');
$rawIds = $_POST['knowledge_fragment_id'] ?? '';
$parts = is_array($rawIds) ? $rawIds : explode(',', (string)$rawIds);
$ids = [];
foreach ($parts as $part) {
    $value = trim((string)$part);
    if ($value === '') continue;
    if (!ctype_digit($value) || (int)$value <= 0) node_update_reply(400, 'error', 'knowledge_fragment_id が不正です');
    $ids[(int)$value] = (int)$value;
}
$ids = array_values($ids);
$comment = trim((string)($_POST['comment'] ?? ''));
$when = trim((string)($_POST['tacto_when'] ?? ''));
$what = trim((string)($_POST['tacto_what'] ?? ''));
$why = trim((string)($_POST['tacto_why'] ?? ''));
$basis = trim((string)($_POST['organizational_basis'] ?? ''));
try {
    $mysqli->begin_transaction();
    $stmt = $mysqli->prepare('SELECT knowledge_node_id FROM knowledge_explorer WHERE knowledge_node_id = ? AND knowledge_group_id = ? AND deleted = 0 FOR UPDATE');
    $stmt->bind_param('ii', $nodeId, $groupId);
    $stmt->execute();
    $node = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$node) throw new DomainException('選択グループに組織知がありません');
    if ($ids && !ok_core_group_has_fragments($mysqli, (string)$groupId, $sourceType, $ids)) {
        throw new DomainException('選択KFがグループに共有されていません');
    }
    $legacyCsv = $sourceType === 'experience' && $ids ? implode(',', $ids) : null;
    $uid = (int)$userId;
    $stmt = $mysqli->prepare('UPDATE knowledge_explorer SET node_title = ?, comment = ?, tacto_when = ?,
        tacto_what = ?, tacto_why = ?, organizational_basis = ?, updated_by = ?,
        knowledge_fragment_id = IF(? = "experience", ?, knowledge_fragment_id), updated_at = NOW()
        WHERE knowledge_node_id = ? AND knowledge_group_id = ? AND deleted = 0');
    $stmt->bind_param('ssssssissii', $title, $comment, $when, $what, $why, $basis,
        $uid, $sourceType, $legacyCsv, $nodeId, $groupId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    $stmt = $mysqli->prepare('DELETE FROM knowledge_explorer_fragment_links WHERE knowledge_node_id = ? AND fragment_source_type = ?');
    $stmt->bind_param('is', $nodeId, $sourceType);
    $stmt->execute();
    $stmt->close();
    if ($ids) {
        $stmt = $mysqli->prepare('INSERT INTO knowledge_explorer_fragment_links (knowledge_node_id, fragment_source_type, fragment_source_id, display_order) VALUES (?, ?, ?, ?)');
        foreach ($ids as $index => $id) {
            $order = $index + 1;
            $stmt->bind_param('isii', $nodeId, $sourceType, $id, $order);
            $stmt->execute();
        }
        $stmt->close();
    }
    $mysqli->commit();
    node_update_reply(200, 'ok', '更新しました', ['node_id'=>$nodeId,'node_title'=>$title,
        'tacto_when'=>$when,'tacto_what'=>$what,'tacto_why'=>$why,'organizational_basis'=>$basis,
        'comment'=>$comment,'knowledge_fragment_id'=>$legacyCsv,'fragment_link_count'=>count($ids),
        'affected_rows'=>$affected]);
} catch (DomainException $error) {
    $mysqli->rollback();
    node_update_reply(403, 'error', $error->getMessage());
} catch (Throwable $error) {
    $mysqli->rollback();
    error_log('update_knowledge_node: '.$error->getMessage());
    node_update_reply(500, 'error', '組織知の更新に失敗しました');
}
