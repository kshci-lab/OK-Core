<?php
// reorder_root_nodes.php (forest-mrn)
// Reorder only root knowledge types (parent_id IS NULL) by swapping sort_order.
// Input: POST node_id, dir=up|down
// Output: { status: 'ok' }

header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/connect_db.php';
if(!isset($mysqli) || !($mysqli instanceof mysqli)){
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'DB接続失敗']);
  exit;
}
@$mysqli->set_charset('utf8mb4');
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

function __resolve_reorder_group_id(mysqli $mysqli): string {
  $groupId = isset($_POST['group_id']) ? trim((string)$_POST['group_id']) : '';
  if($groupId !== ''){ return $groupId; }
  $userId = isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : '';
  if($userId === ''){ return ''; }
  if($stmt = $mysqli->prepare("SELECT group_id FROM kgroup_user_link WHERE user_id = ? ORDER BY created_at DESC LIMIT 1")){
    $stmt->bind_param('s', $userId);
    if($stmt->execute()){
      $stmt->bind_result($gid);
      if($stmt->fetch() && $gid !== null){ $groupId = trim((string)$gid); }
    }
    $stmt->close();
  }
  return $groupId;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
  http_response_code(405);
  echo json_encode(['status'=>'error','message'=>'Method Not Allowed']);
  exit;
}

$node_id = isset($_POST['node_id']) ? (int)$_POST['node_id'] : 0;
$dir = isset($_POST['dir']) ? trim((string)$_POST['dir']) : '';
if($node_id <= 0 || ($dir !== 'up' && $dir !== 'down')){
  echo json_encode(['status'=>'error','message'=>'node_id / dir が不正です']);
  exit;
}

$table = 'knowledge_explorer';
$tbl = $mysqli->query("SHOW TABLES LIKE '".$mysqli->real_escape_string($table)."'");
if(!$tbl || $tbl->num_rows === 0){
  http_response_code(404);
  echo json_encode(['status'=>'error','message'=>'knowledge_explorer テーブルが見つかりません']);
  exit;
}
$tbl->close();

// Detect columns (compat)
$colId = null;
$colParent = null;
$colSort = null;
$colGroup = null;
$hasDeleted = false;
if($resCols = $mysqli->query("SHOW COLUMNS FROM `$table`")){
  while($c = $resCols->fetch_assoc()){
    $f = isset($c['Field']) ? $c['Field'] : '';
    $lf = strtolower($f);
    if($colId === null && in_array($lf, ['knowledge_node_id','node_id','id','knowledge_explorer_id'])){ $colId = $f; }
    if($colParent === null && in_array($lf, ['parent_node_id','parent_id','parent','pid'])){ $colParent = $f; }
    if($colSort === null && $lf === 'sort_order'){ $colSort = $f; }
    if($colGroup === null && in_array($lf, ['knowledge_group_id','group_id'])){ $colGroup = $f; }
    if($lf === 'deleted'){ $hasDeleted = true; }
  }
  $resCols->close();
}
if($colId === null || $colParent === null){
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'必要なカラムが見つかりません']);
  exit;
}

// Ensure sort_order exists
if($colSort === null){
  @$mysqli->query("ALTER TABLE `$table` ADD COLUMN `sort_order` INT(11) NULL DEFAULT NULL");
  $colSort = 'sort_order';
}
if($colGroup === null){
  @$mysqli->query("ALTER TABLE `$table` ADD COLUMN `knowledge_group_id` INT(11) NULL DEFAULT NULL");
  $colGroup = 'knowledge_group_id';
}
$groupId = __resolve_reorder_group_id($mysqli);
$groupFilter = ($colGroup && $groupId !== '') ? " AND `$colGroup`='". $mysqli->real_escape_string($groupId) ."'" : "";

// Helper: get sort_order for a node (root only)
$curSort = null;
$sqlCur = "SELECT `$colSort` FROM `$table` WHERE `$colId`=? AND `$colParent` IS NULL".($hasDeleted?" AND deleted=0":"").$groupFilter." LIMIT 1";
if(!$stmtC = $mysqli->prepare($sqlCur)){
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'prepare失敗']);
  exit;
}
$stmtC->bind_param('i', $node_id);
$stmtC->execute();
$stmtC->bind_result($s0);
if($stmtC->fetch()){
  $curSort = ($s0 !== null) ? (int)$s0 : null;
}
$stmtC->close();
if($curSort === null){
  // Initialize sort_order for all roots if missing
  $sqlInit = "SELECT `$colId` FROM `$table` WHERE `$colParent` IS NULL".($hasDeleted?" AND deleted=0":"").$groupFilter." ORDER BY `$colId` ASC";
  if($res = $mysqli->query($sqlInit)){
    $i = 1;
    while($r = $res->fetch_assoc()){
      $rid = (int)$r[$colId];
      if($st = $mysqli->prepare("UPDATE `$table` SET `$colSort`=? WHERE `$colId`=?")){
        $st->bind_param('ii', $i, $rid);
        @$st->execute();
        $st->close();
      }
      $i++;
    }
    $res->close();
  }
  // refetch current
  if($stmtC2 = $mysqli->prepare($sqlCur)){
    $stmtC2->bind_param('i', $node_id);
    $stmtC2->execute();
    $stmtC2->bind_result($s1);
    if($stmtC2->fetch()){
      $curSort = ($s1 !== null) ? (int)$s1 : null;
    }
    $stmtC2->close();
  }
  if($curSort === null){
    echo json_encode(['status'=>'error','message'=>'対象ノードが見つかりません']);
    exit;
  }
}

// Find adjacent root node by sort_order
if($dir === 'up'){
  $sqlAdj = "SELECT `$colId`, `$colSort` FROM `$table` WHERE `$colParent` IS NULL".($hasDeleted?" AND deleted=0":"").$groupFilter." AND (`$colSort` < ?) ORDER BY `$colSort` DESC, `$colId` DESC LIMIT 1";
} else {
  $sqlAdj = "SELECT `$colId`, `$colSort` FROM `$table` WHERE `$colParent` IS NULL".($hasDeleted?" AND deleted=0":"").$groupFilter." AND (`$colSort` > ?) ORDER BY `$colSort` ASC, `$colId` ASC LIMIT 1";
}
if(!$stmtA = $mysqli->prepare($sqlAdj)){
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'prepare失敗']);
  exit;
}
$stmtA->bind_param('i', $curSort);
$stmtA->execute();
$stmtA->bind_result($adjId, $adjSort);
if(!$stmtA->fetch()){
  // already at edge
  $stmtA->close();
  $mysqli->close();
  echo json_encode(['status'=>'ok','message'=>'edge']);
  exit;
}
$stmtA->close();
$adjId = (int)$adjId;
$adjSort = ($adjSort !== null) ? (int)$adjSort : null;
if($adjId <= 0 || $adjSort === null){
  $mysqli->close();
  echo json_encode(['status'=>'error','message'=>'隣接ノード取得失敗']);
  exit;
}

// Swap sort_order
if($st1 = $mysqli->prepare("UPDATE `$table` SET `$colSort`=? WHERE `$colId`=?")){
  $st1->bind_param('ii', $adjSort, $node_id);
  if(!$st1->execute()){ $st1->close(); $mysqli->close(); echo json_encode(['status'=>'error','message'=>'更新失敗']); exit; }
  $st1->close();
}
if($st2 = $mysqli->prepare("UPDATE `$table` SET `$colSort`=? WHERE `$colId`=?")){
  $st2->bind_param('ii', $curSort, $adjId);
  if(!$st2->execute()){ $st2->close(); $mysqli->close(); echo json_encode(['status'=>'error','message'=>'更新失敗']); exit; }
  $st2->close();
}

$mysqli->close();
echo json_encode(['status'=>'ok','node_id'=>$node_id,'swapped_with'=>$adjId]);

