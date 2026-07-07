<?php
// update_node_title.php (forest-mrn)
// Update knowledge_explorer.node_title for the given node_id.
// fukushima-system has a similar endpoint; forest-mrn uses in-place UPDATE to keep parent-child links stable.

header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
// prevent warnings/notices from corrupting JSON
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/connect_db.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
  http_response_code(405);
  echo json_encode(['status'=>'error','message'=>'Method Not Allowed']);
  exit;
}

if(!isset($mysqli) || !($mysqli instanceof mysqli)){
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'DB接続失敗']);
  exit;
}
@$mysqli->set_charset('utf8mb4');

$node_id = isset($_POST['node_id']) ? (int)$_POST['node_id'] : 0;
$new_title = isset($_POST['new_title']) ? trim((string)$_POST['new_title']) : '';
if($node_id <= 0 || $new_title === ''){
  echo json_encode(['status'=>'error','message'=>'node_id / new_title が不正です']);
  exit;
}
// DB column is VARCHAR(255) in the default schema
if(mb_strlen($new_title, 'UTF-8') > 255){
  $new_title = mb_substr($new_title, 0, 255, 'UTF-8');
}

$table = 'knowledge_explorer';
$tbl = $mysqli->query("SHOW TABLES LIKE '".$mysqli->real_escape_string($table)."'");
if(!$tbl || $tbl->num_rows === 0){
  http_response_code(404);
  echo json_encode(['status'=>'error','message'=>'knowledge_explorer テーブルが見つかりません']);
  exit;
}
if($tbl) $tbl->close();

// Detect columns (compat)
$colId = null;
$colTitle = null;
$colUpdatedAt = null;
$colUpdatedBy = null;
$hasDeleted = false;
if($resCols = $mysqli->query("SHOW COLUMNS FROM `$table`")){
  while($c = $resCols->fetch_assoc()){
    $f = isset($c['Field']) ? $c['Field'] : '';
    $lf = strtolower($f);
    if($colId === null && in_array($lf, ['knowledge_node_id','node_id','id','knowledge_explorer_id'])){ $colId = $f; }
    if($colTitle === null && in_array($lf, ['node_title','title','name','label'])){ $colTitle = $f; }
    if($colUpdatedAt === null && in_array($lf, ['updated_at','update_at','updated','modified_at'])){ $colUpdatedAt = $f; }
    if($colUpdatedBy === null && $lf === 'updated_by'){ $colUpdatedBy = $f; }
    if($lf === 'deleted'){ $hasDeleted = true; }
  }
  $resCols->close();
}
if($colId === null || $colTitle === null){
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'必要なカラムが見つかりません']);
  exit;
}

$userId = isset($_SESSION['USERID']) ? (int)$_SESSION['USERID'] : null;

// Rebuild with a stable bind order: title first, then updated_by, then id.
$setSql = "`$colTitle` = ?";
$types = 's';
$params = [$new_title];
if($colUpdatedAt){
  $setSql .= ", `$colUpdatedAt` = NOW()";
}
if($colUpdatedBy){
  $setSql .= ", `$colUpdatedBy` = ?";
  $types .= 'i';
  $params[] = $userId;
}
$types .= 'i';
$params[] = $node_id;

$where = "`$colId` = ?";
if($hasDeleted){
  // Only update active nodes
  $where .= " AND deleted = 0";
}

$sql = "UPDATE `$table` SET $setSql WHERE $where";
if(!$stmt = $mysqli->prepare($sql)){
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'prepare失敗: '.$mysqli->error]);
  exit;
}

// bind_param requires references
$bind = [];
$bind[] = $types;
for($i=0; $i<count($params); $i++){
  $bind[] = &$params[$i];
}
if(call_user_func_array([$stmt,'bind_param'], $bind) === false){
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'bind_param失敗: '.$stmt->error]);
  $stmt->close();
  exit;
}

if(!$stmt->execute()){
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'UPDATE失敗: '.$stmt->error]);
  $stmt->close();
  exit;
}
$affected = $stmt->affected_rows;
$stmt->close();
$mysqli->close();

echo json_encode(['status'=>'ok','node_id'=>$node_id,'node_title'=>$new_title,'affected_rows'=>$affected]);
