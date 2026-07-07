<?php
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/connect_db.php';
mysqli_report(MYSQLI_REPORT_OFF);

function respond_json($status, $payload = []) {
  echo json_encode(array_merge(['status' => $status], $payload), JSON_UNESCAPED_UNICODE);
  exit;
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
  respond_json('error', ['message' => 'DB connection failed']);
}
@$mysqli->set_charset('utf8mb4');

$groupId = isset($_POST['group_id']) ? intval($_POST['group_id'], 10) : 0;
if ($groupId < 0) { $groupId = 0; }

$rawOrder = isset($_POST['order']) ? (string)$_POST['order'] : '';
$ids = ($rawOrder !== '') ? json_decode($rawOrder, true) : [];
if ($rawOrder !== '' && !is_array($ids)) {
  respond_json('error', ['message' => 'invalid order JSON']);
}

$rawPositions = isset($_POST['positions']) ? (string)$_POST['positions'] : '';
$positions = ($rawPositions !== '') ? json_decode($rawPositions, true) : [];
if ($rawPositions !== '' && !is_array($positions)) {
  respond_json('error', ['message' => 'invalid positions JSON']);
}

$orderedIds = [];
foreach ($ids as $id) {
  $value = intval($id, 10);
  if ($value > 0 && !in_array($value, $orderedIds, true)) {
    $orderedIds[] = $value;
  }
}

$positionItems = [];
foreach ($positions as $item) {
  if (!is_array($item)) { continue; }
  $id = isset($item['id']) ? intval($item['id'], 10) : 0;
  if ($id <= 0) { continue; }
  $positionItems[$id] = [
    'x' => isset($item['x']) ? floatval($item['x']) : 0.0,
    'y' => isset($item['y']) ? floatval($item['y']) : 0.0
  ];
}

if (!$orderedIds && !$positionItems) {
  respond_json('error', ['message' => 'no target fragments']);
}

$createSql = "CREATE TABLE IF NOT EXISTS knowledge_fragment_positions (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL DEFAULT 0,
  externalized_contents_id INT NOT NULL,
  pos_x FLOAT NOT NULL DEFAULT 0,
  pos_y FLOAT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_group_externalized (group_id, externalized_contents_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if (!$mysqli->query($createSql)) {
  respond_json('error', ['message' => 'failed to prepare positions table: '.$mysqli->error]);
}

if ($res = $mysqli->query("SHOW COLUMNS FROM knowledge_fragment_positions LIKE 'group_id'")) {
  if ($res->num_rows === 0) {
    @$mysqli->query("ALTER TABLE knowledge_fragment_positions ADD COLUMN group_id INT NOT NULL DEFAULT 0 AFTER id");
  }
  $res->free();
}

if ($res = $mysqli->query("SHOW COLUMNS FROM knowledge_fragment_positions LIKE 'id'")) {
  if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $extra = isset($row['Extra']) ? (string)$row['Extra'] : '';
    if (stripos($extra, 'auto_increment') === false) {
      @$mysqli->query("ALTER TABLE knowledge_fragment_positions MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
    }
  }
  $res->free();
}

if ($res = $mysqli->query("SHOW INDEX FROM knowledge_fragment_positions WHERE Key_name = 'ux_externalized'")) {
  if ($res->num_rows > 0) { @$mysqli->query("ALTER TABLE knowledge_fragment_positions DROP INDEX ux_externalized"); }
  $res->free();
}
if ($res = $mysqli->query("SHOW INDEX FROM knowledge_fragment_positions WHERE Key_name = 'ux_group_externalized'")) {
  if ($res->num_rows === 0) {
    @$mysqli->query("ALTER TABLE knowledge_fragment_positions ADD UNIQUE KEY ux_group_externalized (group_id, externalized_contents_id)");
  }
  $res->free();
}

$orderStmt = $mysqli->prepare(
  "INSERT INTO knowledge_fragment_positions (group_id, externalized_contents_id, pos_x, pos_y)
   VALUES (?, ?, 0, ?)
   ON DUPLICATE KEY UPDATE pos_y = VALUES(pos_y)"
);
$posStmt = $mysqli->prepare(
  "INSERT INTO knowledge_fragment_positions (group_id, externalized_contents_id, pos_x, pos_y)
   VALUES (?, ?, ?, ?)
   ON DUPLICATE KEY UPDATE pos_x = VALUES(pos_x), pos_y = VALUES(pos_y)"
);
if (!$orderStmt || !$posStmt) {
  respond_json('error', ['message' => 'failed to prepare save SQL']);
}

$saved = 0;
foreach ($orderedIds as $index => $extId) {
  $posY = (float)$index;
  $orderStmt->bind_param('iid', $groupId, $extId, $posY);
  if ($orderStmt->execute()) { $saved++; }
}

foreach ($positionItems as $extId => $pos) {
  $x = (float)$pos['x'];
  $y = (float)$pos['y'];
  $posStmt->bind_param('iidd', $groupId, $extId, $x, $y);
  if ($posStmt->execute()) { $saved++; }
}

$orderStmt->close();
$posStmt->close();

respond_json('ok', ['items_saved' => $saved]);
