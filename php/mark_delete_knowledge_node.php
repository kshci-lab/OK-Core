<?php
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/connect_db.php';

if(!isset($mysqli) || !($mysqli instanceof mysqli)){
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'DB接続失敗']);
    exit;
}

$id = null;
// log raw input for debugging
if(isset($_POST['node_id'])){ error_log('mark_delete called with raw node_id=' . var_export($_POST['node_id'], true)); $id = intval($_POST['node_id']); }
if(!$id){ echo json_encode(['status'=>'error','message'=>'node_id が不正です']); exit; }

$table = 'knowledge_explorer';
$tbl = $mysqli->query("SHOW TABLES LIKE '".$mysqli->real_escape_string($table)."'");
if(!$tbl || $tbl->num_rows===0){ echo json_encode(['status'=>'error','message'=>'knowledge_explorer テーブルが見つかりません']); exit; }
$tbl->close();

// カラム検出
$idCol = null; $hasDeleted = false;
if($resCols = $mysqli->query("SHOW COLUMNS FROM $table")){
    while($c = $resCols->fetch_assoc()){
        $f = isset($c['Field']) ? $c['Field'] : '';
        $lf = strtolower($f);
        if($idCol===null && in_array($lf, ['knowledge_node_id','node_id','id','knowledge_explorer_id'])){ $idCol = $f; }
        if($lf === 'deleted'){ $hasDeleted = true; }
    }
    $resCols->close();
}
if($idCol === null){ echo json_encode(['status'=>'error','message'=>'識別子カラムが見つかりません']); exit; }

// deleted カラムが無ければ追加する（互換性向上のため）
if(!$hasDeleted){
    $alter = "ALTER TABLE $table ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0";
    if(!$mysqli->query($alter)){
        // 追加に失敗しても続けて更新を試みる
        // ログは残す
        error_log('failed to add deleted column: '.$mysqli->error);
    } else {
        $hasDeleted = true;
    }
}

// UPDATE 実行
$sql = "UPDATE $table SET deleted=1 WHERE $idCol = ?";
if($stmt = $mysqli->prepare($sql)){
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    if($ok){ echo json_encode(['status'=>'ok','node_id'=>$id]); }
    else { echo json_encode(['status'=>'error','message'=>'更新に失敗しました']); }
} else {
    echo json_encode(['status'=>'error','message'=>'更新準備に失敗しました: '.$mysqli->error]);
}

$mysqli->close();

?>
