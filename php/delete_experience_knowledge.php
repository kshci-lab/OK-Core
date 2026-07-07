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
    echo json_encode(['status' => 'error', 'message' => '削除する組織知を確認できませんでした'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = intval($_SESSION['USERID']);
$experience_knowledge_id = intval($_POST['experience_knowledge_id']);
$timestamp = date("Y-m-d H:i:s") . "." . substr(explode(".", (microtime(true) . ""))[1], 0, 3);

$sql = "UPDATE experience_knowledges
    SET deleted = 1, updated_at = ?
    WHERE experience_knowledge_id = ?
        AND user_id = ?
        AND deleted = 0";

if (!($stmt = $mysqli->prepare($sql))) {
    echo json_encode(['status' => 'error', 'message' => '削除処理の準備に失敗しました'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param('sii', $timestamp, $experience_knowledge_id, $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => '組織知の削除に失敗しました'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($stmt->affected_rows < 1) {
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => '削除できる組織知が見つかりませんでした'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->close();
echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
?>
