<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/knowledge_group_access.php';
hcimlab_start_session();
require_once __DIR__ . '/connect_db.php';

function node_create_reply(int $code, string $status, string $message, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['status'=>$status,'message'=>$message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') node_create_reply(405, 'error', 'Method Not Allowed');
if (!isset($mysqli) || !($mysqli instanceof mysqli)) node_create_reply(503, 'error', 'DB接続失敗');
$mysqli->set_charset('utf8mb4');
$userId = (string)($_SESSION['USERID'] ?? '');
$access = ok_core_resolve_active_group($mysqli, trim((string)($_POST['group_id'] ?? '')), $userId);
if ($access['status'] !== 200 || $access['group_id'] === '') {
    node_create_reply($access['status'] === 200 ? 403 : $access['status'], 'error', 'グループを利用できません');
}
$groupId = (int)$access['group_id'];
$title = trim((string)($_POST['node_title'] ?? ''));
if ($title === '' || (function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : preg_match_all('/./us', $title)) > 255) node_create_reply(400, 'error', '組織知の要素は1～255文字で入力してください');
$parentRaw = trim((string)($_POST['parent_id'] ?? ''));
if ($parentRaw !== '' && $parentRaw !== '0' && (!ctype_digit($parentRaw) || (int)$parentRaw <= 0)) {
    node_create_reply(400, 'error', 'parent_id が不正です');
}
$parentId = $parentRaw === '' || $parentRaw === '0' ? null : (int)$parentRaw;
$parentLabel = trim((string)($_POST['parent_label'] ?? ''));
$sourceType = strtolower(trim((string)($_POST['fragment_source_type'] ?? 'experience')));
if ($sourceType === 'externalized') $sourceType = 'discussion';
if (!in_array($sourceType, ['experience', 'discussion'], true)) node_create_reply(400, 'error', 'fragment_source_type が不正です');
$rawIds = $_POST['knowledge_fragment_id'] ?? '';
$parts = is_array($rawIds) ? $rawIds : explode(',', (string)$rawIds);
$fragmentIds = [];
foreach ($parts as $part) {
    $value = trim((string)$part);
    if ($value === '') continue;
    if (!ctype_digit($value) || (int)$value <= 0) node_create_reply(400, 'error', 'knowledge_fragment_id が不正です');
    $fragmentIds[(int)$value] = (int)$value;
}
$fragmentIds = array_values($fragmentIds);
$comment = trim((string)($_POST['comment'] ?? ''));
$when = trim((string)($_POST['tacto_when'] ?? ''));
$what = trim((string)($_POST['tacto_what'] ?? ''));
$why = trim((string)($_POST['tacto_why'] ?? ''));
$basis = trim((string)($_POST['organizational_basis'] ?? ($_POST['kf_common_points'] ?? '')));
try {
    $mysqli->begin_transaction();
    if ($parentId !== null) {
        $stmt = $mysqli->prepare('SELECT knowledge_node_id FROM knowledge_explorer WHERE knowledge_node_id = ? AND knowledge_group_id = ? AND deleted = 0 FOR UPDATE');
        $stmt->bind_param('ii', $parentId, $groupId);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$found) throw new DomainException('親ノードが選択グループにありません');
    } elseif ($parentLabel !== '') {
        $stmt = $mysqli->prepare('SELECT knowledge_node_id FROM knowledge_explorer WHERE parent_node_id IS NULL AND node_title = ? AND knowledge_group_id = ? AND deleted = 0 ORDER BY sort_order, knowledge_node_id LIMIT 1 FOR UPDATE');
        $stmt->bind_param('si', $parentLabel, $groupId);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$found) throw new DomainException('親カテゴリが選択グループにありません');
        $parentId = (int)$found['knowledge_node_id'];
    }
    if ($fragmentIds && !ok_core_group_has_fragments($mysqli, (string)$groupId, $sourceType, $fragmentIds)) {
        throw new DomainException('選択KFがグループに共有されていません');
    }
    $sortOrder = null;
    if ($parentId === null) {
        $stmt = $mysqli->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM knowledge_explorer WHERE knowledge_group_id = ? AND parent_node_id IS NULL AND deleted = 0');
        $stmt->bind_param('i', $groupId);
        $stmt->execute();
        $sortOrder = (int)$stmt->get_result()->fetch_assoc()['next_sort'];
        $stmt->close();
    }
    $legacyIds = $sourceType === 'experience' && $fragmentIds ? implode(',', $fragmentIds) : null;
    $uid = (int)$userId;
    $stmt = $mysqli->prepare('INSERT INTO knowledge_explorer (parent_node_id, node_title, knowledge_fragment_id, comment, tacto_when, tacto_what, tacto_why, organizational_basis, updated_by, deleted, sort_order, knowledge_group_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)');
    $stmt->bind_param('isssssssiii', $parentId, $title, $legacyIds, $comment, $when, $what, $why, $basis, $uid, $sortOrder, $groupId);
    $stmt->execute();
    $nodeId = (int)$mysqli->insert_id;
    $stmt->close();
    if ($fragmentIds) {
        $stmt = $mysqli->prepare('INSERT INTO knowledge_explorer_fragment_links (knowledge_node_id, fragment_source_type, fragment_source_id, display_order) VALUES (?, ?, ?, ?)');
        foreach ($fragmentIds as $index => $fragmentId) {
            $displayOrder = $index + 1;
            $stmt->bind_param('isii', $nodeId, $sourceType, $fragmentId, $displayOrder);
            $stmt->execute();
        }
        $stmt->close();
    }
    $mysqli->commit();
    node_create_reply(200, 'ok', '登録しました', ['node_id'=>$nodeId,'parent_id'=>$parentId,'node_title'=>$title,'fragment_link_count'=>count($fragmentIds)]);
} catch (DomainException $error) {
    $mysqli->rollback();
    node_create_reply(403, 'error', $error->getMessage());
} catch (Throwable $error) {
    $mysqli->rollback();
    error_log('insert_knowledge_node: ' . $error->getMessage());
    node_create_reply(500, 'error', '組織知の登録に失敗しました');
}
