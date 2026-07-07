<?php
// update_discussed_status.php (forest-mrn)
// Update discussed status for a given fragment.
// Input:
//   POST fragment_source_type ('experience'|'externalized'|'discussion'|'SRL')
//   + experience_knowledge_id OR externalized_contents_id OR srl_id
//   + status (YET|UNDERWAY|DONE)
// Output: JSON { status: 'ok', fragment_source_type: ..., fragment_id: ..., discussed: ... }

@header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/connect_db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$response = [ 'status' => 'error' ];
try {
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) { throw new Exception('DB接続が無効です'); }
    @$mysqli->set_charset('utf8mb4');

    $allowed = ['YET','UNDERWAY','DONE'];
    $status = isset($_POST['status']) ? trim((string)$_POST['status']) : '';
    if (!in_array($status, $allowed, true)) { throw new Exception('不正な status 値です'); }

    $sourceType = isset($_POST['fragment_source_type']) ? strtolower(trim((string)$_POST['fragment_source_type'])) : '';
    if ($sourceType === 'externalized') { $sourceType = 'discussion'; }
    if ($sourceType === 'srl') { $sourceType = 'SRL'; }

    $id = 0;
    $table = '';
    $idColumn = '';
    if ($sourceType === 'discussion') {
        $id = isset($_POST['externalized_contents_id']) ? intval($_POST['externalized_contents_id'], 10) : 0;
        $table = 'externalized_contents';
        $idColumn = 'externalized_contents_id';
    } elseif ($sourceType === 'SRL') {
        $id = isset($_POST['srl_id']) ? intval($_POST['srl_id'], 10) : 0;
        $table = 'srl_questions';
        $idColumn = 'srl_id';
    } else {
        $sourceType = 'experience';
        $id = isset($_POST['experience_knowledge_id']) ? intval($_POST['experience_knowledge_id'], 10) : 0;
        if ($id <= 0) {
            $id = isset($_POST['externalized_contents_id']) ? intval($_POST['externalized_contents_id'], 10) : 0;
        }
        $table = 'experience_knowledges';
        $idColumn = 'experience_knowledge_id';
    }
    if ($id <= 0) { throw new Exception('更新対象IDを特定できません'); }

    $hasDiscussed = false;
    if ($res = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'discussed'")) {
        $hasDiscussed = ($res->num_rows > 0);
        $res->free();
    }
    if (!$hasDiscussed) { throw new Exception($table . '.discussed カラムが存在しません'); }

    if (!$stmt = $mysqli->prepare("UPDATE `$table` SET discussed = ? WHERE `$idColumn` = ?")) {
        throw new Exception('UPDATE 準備失敗: ' . $mysqli->error);
    }
    $stmt->bind_param('si', $status, $id);
    if (!$stmt->execute()) { throw new Exception('UPDATE 実行失敗: ' . $stmt->error); }
    $stmt->close();

    $response = [ 'status' => 'ok', 'fragment_source_type' => $sourceType, 'fragment_id' => $id, 'discussed' => $status ];
} catch (Exception $ex) {
    $response['error'] = $ex->getMessage();
}

echo json_encode($response);
