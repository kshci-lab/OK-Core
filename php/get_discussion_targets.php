<?php
// get_discussion_targets.php (forest-mrn)
// Given a fragment_id, return the latest discussion target set (knowledge_fragment_id CSV)
// from discussion_history so the UI can restore multi-fragment selection after reload.
//
// Input: GET fragment_id (int)
// Output: JSON { status: 'ok', fragment_id: <int>, targets: [<int>, ...], raw: "csv" }

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

$fid = isset($_GET['fragment_id']) ? (int)$_GET['fragment_id'] : 0;
if($fid <= 0){
  echo json_encode(['status'=>'error','message'=>'fragment_id が不正です']);
  exit;
}

$table = 'discussion_history';
$tbl = $mysqli->query("SHOW TABLES LIKE '".$mysqli->real_escape_string($table)."'");
if(!$tbl || $tbl->num_rows === 0){
  echo json_encode(['status'=>'ok','fragment_id'=>$fid,'targets'=>[$fid],'raw'=>null]);
  exit;
}
$tbl->close();

// Determine column type (int vs varchar)
$colType = '';
if($resCol = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'knowledge_fragment_id'")){
  $rowCol = $resCol->fetch_assoc();
  if($rowCol && isset($rowCol['Type'])) $colType = strtolower((string)$rowCol['Type']);
  $resCol->free();
}
$isVarchar = (strpos($colType,'varchar') !== false) || (strpos($colType,'text') !== false);

$raw = null;
if($isVarchar){
  $sql = "SELECT knowledge_fragment_id
            FROM `$table`
           WHERE FIND_IN_SET(?, knowledge_fragment_id)
        ORDER BY posted_time DESC, discussion_history_id DESC
           LIMIT 1";
  if($stmt = $mysqli->prepare($sql)){
    $stmt->bind_param('i', $fid);
    if($stmt->execute()){
      $stmt->bind_result($kraw);
      if($stmt->fetch()){ $raw = $kraw; }
    }
    $stmt->close();
  }
} else {
  $sql = "SELECT knowledge_fragment_id
            FROM `$table`
           WHERE knowledge_fragment_id = ?
        ORDER BY posted_time DESC, discussion_history_id DESC
           LIMIT 1";
  if($stmt = $mysqli->prepare($sql)){
    $stmt->bind_param('i', $fid);
    if($stmt->execute()){
      $stmt->bind_result($kraw);
      if($stmt->fetch()){ $raw = $kraw; }
    }
    $stmt->close();
  }
}

$targets = [];
if($raw !== null){
  $parts = explode(',', (string)$raw);
  foreach($parts as $p){
    $p = trim($p);
    if($p === '') continue;
    $v = (int)$p;
    if($v > 0) $targets[] = $v;
  }
  $targets = array_values(array_unique($targets));
}
if(count($targets) === 0){
  $targets = [$fid];
}

echo json_encode([
  'status' => 'ok',
  'fragment_id' => $fid,
  'targets' => $targets,
  'raw' => $raw
]);

