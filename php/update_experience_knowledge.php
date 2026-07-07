<?php
session_start();
require("connect_db.php");

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tokyo');

if (!isset($_SESSION['USERID'])) {
    echo json_encode(['status' => 'error', 'message' => 'ログイン情報を確認できませんでした'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_POST['experience_knowledge_id']) || $_POST['experience_knowledge_id'] === '') {
    echo json_encode(['status' => 'error', 'message' => '更新する組織知を確認できませんでした'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = intval($_SESSION['USERID']);
$experience_knowledge_id = intval($_POST['experience_knowledge_id']);
$knowledge_fragment_title = isset($_POST['knowledge_fragment_title']) ? $_POST['knowledge_fragment_title'] : '';
$contents_json = isset($_POST['contents']) ? $_POST['contents'] : '[]';
$timestamp = date("Y-m-d H:i:s") . "." . substr(explode(".", (microtime(true) . ""))[1], 0, 3);

$stage1_items = [];
$stage2_items = [];
$stage3_items = [];
$contents = json_decode($contents_json, true);

if (is_array($contents)) {
    foreach ($contents as $content_item) {
        $type = isset($content_item['type']) ? $content_item['type'] : '';
        $content = isset($content_item['content']) ? $content_item['content'] : '';
        if (trim($content) === '') continue;

        switch ($type) {
            case 'stage1':
                $stage1_items[] = $content;
                break;
            case 'stage2':
                $stage2_items[] = $content;
                break;
            case 'stage3':
                $stage3_items[] = $content;
                break;
            default:
                $stage1_items[] = $content;
        }
    }
}

$stage1 = implode("\n", $stage1_items);
$stage2 = implode("\n", $stage2_items);
$stage3 = implode("\n", $stage3_items);

$check_sql = "SELECT experience_knowledge_id
    FROM experience_knowledges
    WHERE experience_knowledge_id = ?
        AND user_id = ?
        AND deleted = 0";

if (!($check_stmt = $mysqli->prepare($check_sql))) {
    echo json_encode(['status' => 'error', 'message' => '更新対象の確認に失敗しました'], JSON_UNESCAPED_UNICODE);
    exit;
}

$check_stmt->bind_param('ii', $experience_knowledge_id, $user_id);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows < 1) {
    $check_stmt->close();
    echo json_encode(['status' => 'error', 'message' => '更新できる組織知が見つかりませんでした'], JSON_UNESCAPED_UNICODE);
    exit;
}

$check_stmt->close();

$sql = "UPDATE experience_knowledges
    SET knowledge_fragment_content = ?,
        stage1 = ?,
        stage2 = ?,
        stage3 = ?,
        updated_at = ?
    WHERE experience_knowledge_id = ?
        AND user_id = ?
        AND deleted = 0";

if (!($stmt = $mysqli->prepare($sql))) {
    echo json_encode(['status' => 'error', 'message' => '更新処理の準備に失敗しました'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param('sssssii', $knowledge_fragment_title, $stage1, $stage2, $stage3, $timestamp, $experience_knowledge_id, $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => '組織知の更新に失敗しました'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->close();
echo json_encode([
    'status' => 'ok',
    'knowledge_fragment_title' => $knowledge_fragment_title,
    'stage1' => $stage1,
    'stage2' => $stage2,
    'stage3' => $stage3
], JSON_UNESCAPED_UNICODE);
?>
