<?php
// get_discussion_history.php
// Returns last N discussion_history rows (default 100) ordered by discussion_history_id ASC
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL); ini_set('display_errors', 1);

require_once __DIR__ . '/connect_db.php';
if(!isset($mysqli) || !($mysqli instanceof mysqli)){
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'DB接続失敗']);
  exit;
}
@$mysqli->set_charset('utf8mb4');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
if($limit <= 0 || $limit > 500){ $limit = 100; }

// optional fragment filter (knowledge_fragment_id)
// Accept single id or comma-separated ids.
$fragmentRaw = isset($_GET['fragment_id']) ? trim((string)$_GET['fragment_id']) : '';
$fragmentSourceType = isset($_GET['fragment_source_type']) ? trim((string)$_GET['fragment_source_type']) : '';
$fragmentSourceType = strtolower($fragmentSourceType);
if ($fragmentSourceType === 'externalized') { $fragmentSourceType = 'discussion'; }
if (!in_array($fragmentSourceType, ['experience', 'discussion', 'srl'], true)) { $fragmentSourceType = ''; }
$fragmentIds = [];
if ($fragmentRaw !== '') {
  foreach (explode(',', $fragmentRaw) as $p) {
    $p = trim($p);
    if ($p === '') continue;
    $v = (int)$p;
    if ($v > 0) $fragmentIds[] = $v;
  }
  $fragmentIds = array_values(array_unique($fragmentIds));
}

$table = 'discussion_history';
$tbl = $mysqli->query("SHOW TABLES LIKE '".$mysqli->real_escape_string($table)."'");
if(!$tbl){
  echo json_encode(['status'=>'error','message'=>'テーブル確認失敗: '.$mysqli->error]);
  exit;
}
if($tbl->num_rows === 0){
  // No table yet => empty list
  echo json_encode(['status'=>'ok','items'=>[]]);
  exit;
}
$tbl->close();

$sql = "SELECT dh.discussion_history_id, dh.user_id, dh.posted_time, dh.content, COALESCE(u.name,'') AS user_name " .
  "FROM `$table` dh LEFT JOIN `users` u ON dh.user_id = u.user_id ";

// apply fragment filter if provided (support CSV stored values)
$types = '';
$params = [];
$whereParts = [];
$hasSourceTypeCol = false;
if($resSourceCol = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'fragment_source_type'")){
  $hasSourceTypeCol = ($resSourceCol->num_rows > 0);
  $resSourceCol->free();
}
if(count($fragmentIds) > 0){
  // Determine column type of knowledge_fragment_id (int vs varchar)
  $colType = '';
  if($resCol = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'knowledge_fragment_id'")){
    $rowCol = $resCol->fetch_assoc();
    if($rowCol && isset($rowCol['Type'])) $colType = strtolower((string)$rowCol['Type']);
    $resCol->free();
  }
  $isVarchar = (strpos($colType, 'varchar') !== false) || (strpos($colType, 'text') !== false);
  $conds = [];
  if($isVarchar){
    // FIND_IN_SET works for both single-value and CSV strings.
    foreach($fragmentIds as $fid){
      $conds[] = "FIND_IN_SET(?, dh.knowledge_fragment_id)";
      $types .= 'i';
      $params[] = $fid;
    }
  } else {
    // numeric column: IN (...)
    $place = implode(',', array_fill(0, count($fragmentIds), '?'));
    $conds[] = "dh.knowledge_fragment_id IN ($place)";
    $types .= str_repeat('i', count($fragmentIds));
    foreach($fragmentIds as $fid){ $params[] = $fid; }
  }
  $whereParts[] = "(" . implode(' OR ', $conds) . ")";
}
if($fragmentSourceType !== ''){
  if($hasSourceTypeCol){
    if($fragmentSourceType === 'experience'){
      $whereParts[] = "(dh.fragment_source_type = ? OR dh.fragment_source_type IS NULL OR dh.fragment_source_type = '')";
    } else {
      $whereParts[] = "dh.fragment_source_type = ?";
    }
    $types .= 's';
    $params[] = $fragmentSourceType;
  } elseif($fragmentSourceType !== 'experience'){
    echo json_encode(['status'=>'ok','items'=>[]]);
    $mysqli->close();
    exit;
  }
}
if(count($whereParts) > 0){
  $sql .= " WHERE " . implode(' AND ', $whereParts) . " ";
}

$sql .= " ORDER BY dh.discussion_history_id ASC LIMIT ?";
$types .= 'i';
$params[] = $limit;

if(!$stmt = $mysqli->prepare($sql)){
  echo json_encode(['status'=>'error','message'=>'prepare失敗: '.$mysqli->error]);
  exit;
}
// bind_param needs references
$bind = [];
$bind[] = $types;
for($i=0; $i<count($params); $i++){
  $bind[] = &$params[$i];
}
if(call_user_func_array([$stmt,'bind_param'], $bind) === false){
  echo json_encode(['status'=>'error','message'=>'bind_param失敗: '.$stmt->error]); exit;
}
if(!$stmt->execute()){
  $err = $stmt->error; $stmt->close();
  echo json_encode(['status'=>'error','message'=>'SELECT失敗: '.$err]);
  exit;
}
$result = $stmt->get_result();
$items = [];
while($row = $result->fetch_assoc()){
  $items[] = [
    'discussion_history_id' => (int)$row['discussion_history_id'],
    'user_id' => (int)$row['user_id'],
    'posted_time' => $row['posted_time'],
    'content' => $row['content'],
    'user_name' => isset($row['user_name']) ? $row['user_name'] : ''
  ];
}
$result->free();
$stmt->close();
$mysqli->close();

echo json_encode(['status'=>'ok','items'=>$items]);
