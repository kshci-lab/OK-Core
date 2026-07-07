<?php
// insert_knowledge_node.php (動的カラム対応: knowledge_node_id / parent_node_id を検出, 手動採番, deleted=0)
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/connect_db.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){
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

function __resolve_insert_knowledge_group_id(mysqli $mysqli): string {
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

function __parse_fragment_link_ids($value): array {
    if ($value === null || $value === '') { return []; }
    $parts = is_array($value) ? $value : preg_split('/\s*,\s*/', (string)$value);
    $ids = [];
    foreach ($parts as $part) {
        $id = intval(trim((string)$part), 10);
        if ($id > 0 && !in_array($id, $ids, true)) { $ids[] = $id; }
    }
    return $ids;
}

function __normalize_fragment_source_type($value, string $fallback): string {
    $type = strtolower(trim((string)$value));
    if ($type === 'discussion') { return 'discussion'; }
    if ($type === 'srl') { return 'SRL'; }
    if (in_array($type, ['experience', 'discussion'], true)) { return $type; }
    if ((string)$value === 'SRL') { return 'SRL'; }
    return $fallback;
}

function __ensure_knowledge_fragment_links_table(mysqli $mysqli): bool {
    $sql = "CREATE TABLE IF NOT EXISTS `knowledge_explorer_fragment_links` (".
           "`id` INT NOT NULL AUTO_INCREMENT,".
           "`knowledge_node_id` INT NOT NULL,".
           "`fragment_source_type` ENUM('experience','discussion','SRL') NOT NULL,".
           "`fragment_source_id` INT NOT NULL,".
           "`display_order` INT DEFAULT NULL,".
           "`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,".
           "PRIMARY KEY (`id`),".
           "UNIQUE KEY `uniq_knowledge_fragment` (`knowledge_node_id`,`fragment_source_type`,`fragment_source_id`),".
           "KEY `idx_knowledge_node_id` (`knowledge_node_id`),".
           "KEY `idx_fragment_lookup` (`fragment_source_type`,`fragment_source_id`)".
           ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$mysqli->query($sql)) { return false; }
    @$mysqli->query("UPDATE `knowledge_explorer_fragment_links` SET `fragment_source_type` = 'discussion' WHERE `fragment_source_type` = 'externalized'");
    @$mysqli->query("ALTER TABLE `knowledge_explorer_fragment_links` MODIFY COLUMN `fragment_source_type` ENUM('experience','discussion','SRL') NOT NULL");
    return true;
}

function __save_knowledge_fragment_links(mysqli $mysqli, int $knowledgeNodeId, array $fragmentIds, string $sourceType): void {
    if ($knowledgeNodeId <= 0 || empty($fragmentIds)) { return; }
    if (!__ensure_knowledge_fragment_links_table($mysqli)) { return; }
    $sql = "INSERT INTO `knowledge_explorer_fragment_links` ".
           "(`knowledge_node_id`,`fragment_source_type`,`fragment_source_id`,`display_order`) ".
           "VALUES (?,?,?,?) ".
           "ON DUPLICATE KEY UPDATE `display_order` = VALUES(`display_order`)";
    if (!$stmt = $mysqli->prepare($sql)) { return; }
    foreach (array_values($fragmentIds) as $index => $fragmentId) {
        $order = $index + 1;
        $stmt->bind_param('isii', $knowledgeNodeId, $sourceType, $fragmentId, $order);
        $stmt->execute();
    }
    $stmt->close();
}

function __ensure_text_column(mysqli $mysqli, string $table, string $column): void {
    $exists = false;
    $type = '';
    if ($res = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE '".$mysqli->real_escape_string($column)."'")) {
        if ($row = $res->fetch_assoc()) {
            $exists = true;
            $type = isset($row['Type']) ? strtolower((string)$row['Type']) : '';
        }
        $res->free();
    }
    if (!$exists) {
        @$mysqli->query("ALTER TABLE `$table` ADD COLUMN `$column` TEXT NULL DEFAULT NULL");
        return;
    }
    if ($type && strpos($type, 'text') === false) {
        @$mysqli->query("ALTER TABLE `$table` MODIFY COLUMN `$column` TEXT NULL DEFAULT NULL");
    }
}

function __build_structured_comment_fallback(string $comment, string $when, string $what, string $why, string $basis): string {
    $sections = [
        'When' => $when,
        'What' => $what,
        'Why' => $why,
        '組織知化の根拠' => $basis,
        '補足コメント' => $comment
    ];
    $lines = [];
    foreach ($sections as $label => $body) {
        $body = trim($body);
        if ($body !== '') { $lines[] = '【'.$label.'】'."\n".$body; }
    }
    return implode("\n\n", $lines);
}

$parent_label = isset($_POST['parent_label']) ? trim((string)$_POST['parent_label']) : '';
$parent_id = null;
if(isset($_POST['parent_id']) && $_POST['parent_id'] !== '' && $_POST['parent_id'] !== null){
    $pidRaw = (int)$_POST['parent_id'];
    if($pidRaw > 0){ $parent_id = $pidRaw; }
    else if($pidRaw === 0){ $parent_id = null; }
}
$title = isset($_POST['node_title']) ? trim((string)$_POST['node_title']) : '';
$comment = isset($_POST['comment']) ? trim((string)$_POST['comment']) : '';
$tactoWhen = isset($_POST['tacto_when']) ? trim((string)$_POST['tacto_when']) : '';
$tactoWhat = isset($_POST['tacto_what']) ? trim((string)$_POST['tacto_what']) : '';
$tactoWhy = isset($_POST['tacto_why']) ? trim((string)$_POST['tacto_why']) : '';
$organizationalBasis = isset($_POST['organizational_basis']) ? trim((string)$_POST['organizational_basis']) : '';
if ($organizationalBasis === '' && isset($_POST['kf_common_points'])) {
    $organizationalBasis = trim((string)$_POST['kf_common_points']);
}
$fragmentSourceType = __normalize_fragment_source_type(isset($_POST['fragment_source_type']) ? $_POST['fragment_source_type'] : '', 'experience');
// optional: knowledge_fragment_id (accept CSV string or array); store as-is (expects VARCHAR column)
$kfragId = '';
if (isset($_POST['knowledge_fragment_id']) && $_POST['knowledge_fragment_id'] !== '') {
    if (is_array($_POST['knowledge_fragment_id'])) {
        $arr = array_map('trim', $_POST['knowledge_fragment_id']);
        $arr = array_filter($arr, function($v){ return $v !== ''; });
        if (count($arr)) { $kfragId = implode(',', $arr); }
    } else {
        $kraw = trim((string)$_POST['knowledge_fragment_id']);
        if ($kraw !== '') {
            if (strpos($kraw, ',') !== false) {
                $parts = array_map('trim', explode(',', $kraw));
                $parts = array_filter($parts, function($v){ return $v !== ''; });
                if (count($parts)) { $kfragId = implode(',', $parts); }
            } else {
                $kfragId = $kraw;
            }
        }
    }
}
$fragmentLinkIds = __parse_fragment_link_ids($kfragId);
if($title===''){
    echo json_encode(['status'=>'error','message'=>'node_title が空です']);
    exit;
}
$table = 'knowledge_explorer';

foreach (['comment', 'tacto_when', 'tacto_what', 'tacto_why', 'organizational_basis'] as $textColumn) {
    __ensure_text_column($mysqli, $table, $textColumn);
}

// カラム存在の動的検出
$colId = 'knowledge_node_id';
$colParent = 'parent_id';
$colTitle = 'node_title';
$colUpdatedBy = null; // updated_by カラム存在時に使用
$colComment = null;   // comment/memo 等
$colKFragId = null;   // knowledge_fragment_id 等
$colSort = null;      // sort_order (root categories)
$colGroup = null;     // knowledge_group_id
$colTactoWhen = null;
$colTactoWhat = null;
$colTactoWhy = null;
$colOrganizationalBasis = null;
$hasDeleted = false;
$kfragColType = '';
if($cols = $mysqli->query("SHOW COLUMNS FROM $table")){
    while($c = $cols->fetch_assoc()){
        $f = $c['Field'];
        $lf = strtolower($f);
        if(in_array($lf, ['knowledge_node_id','node_id','id'])){ $colId = $f; }
        if(in_array($lf, ['parent_node_id','parent_id','parent','pid'])){ $colParent = $f; }
        if(in_array($lf, ['node_title','title','name','label'])){ $colTitle = $f; }
        if($colComment===null && in_array($lf, ['comment','comments','note','notes','memo'])){ $colComment = $f; }
        if($colKFragId===null && in_array($lf, ['knowledge_fragment_id','knowledgefragment_id','kfrag_id'])){ $colKFragId = $f; }
        if($colSort===null && $lf === 'sort_order'){ $colSort = $f; }
        if($colGroup===null && in_array($lf, ['knowledge_group_id','group_id'])){ $colGroup = $f; }
        if($colTactoWhen===null && $lf === 'tacto_when'){ $colTactoWhen = $f; }
        if($colTactoWhat===null && $lf === 'tacto_what'){ $colTactoWhat = $f; }
        if($colTactoWhy===null && $lf === 'tacto_why'){ $colTactoWhy = $f; }
        if($colOrganizationalBasis===null && $lf === 'organizational_basis'){ $colOrganizationalBasis = $f; }
        if($lf === 'knowledge_fragment_id' && isset($c['Type'])){ $kfragColType = strtolower((string)$c['Type']); }
        if($lf==='deleted'){ $hasDeleted = true; }
        if($lf==='updated_by'){ $colUpdatedBy = $f; }
    }
    $cols->close();
}

// Ensure sort_order exists for root ordering (non-fatal)
if($colSort === null){
    if(@$mysqli->query("ALTER TABLE $table ADD COLUMN sort_order INT(11) NULL DEFAULT NULL")){
        $colSort = 'sort_order';
    }
}
if($colGroup === null){
    if(@$mysqli->query("ALTER TABLE $table ADD COLUMN knowledge_group_id INT(11) NULL DEFAULT NULL")){
        $colGroup = 'knowledge_group_id';
    }
}
$groupId = __resolve_insert_knowledge_group_id($mysqli);

// If we are going to store CSV but the column is numeric, try to widen to VARCHAR
if($colKFragId && $kfragId !== '' && strpos($kfragId, ',') !== false){
    if($kfragColType && (strpos($kfragColType, 'varchar') === false) && (strpos($kfragColType, 'text') === false)){
        @$mysqli->query("ALTER TABLE $table MODIFY COLUMN `$colKFragId` VARCHAR(255) NULL DEFAULT NULL");
        $kfragColType = 'varchar(255)';
    }
}

// 親ID解決（トップレベルノードから一致を探索）
$parentId = null;
if($parent_id !== null){
    // Verify parent exists (and is not deleted if the column exists)
    $groupFilter = ($colGroup && $groupId !== '') ? " AND $colGroup='". $mysqli->real_escape_string($groupId) ."'" : "";
    $sqlP = "SELECT $colId FROM $table WHERE $colId=?".($hasDeleted?" AND deleted=0":"").$groupFilter." LIMIT 1";
    if($stP = $mysqli->prepare($sqlP)){
        $stP->bind_param('i', $parent_id);
        $stP->execute();
        $stP->bind_result($pidOk);
        if($stP->fetch()){ $parentId = (int)$pidOk; }
        $stP->close();
    }
    if($parentId === null){
        echo json_encode(['status'=>'error','message'=>'parent_id が見つかりません: '.$parent_id]);
        exit;
    }
} else if($parent_label!==''){
    $groupFilter = ($colGroup && $groupId !== '') ? " AND $colGroup='". $mysqli->real_escape_string($groupId) ."'" : "";
    $sqlFind = "SELECT $colId FROM $table WHERE $colParent IS NULL AND $colTitle=?".($hasDeleted?" AND deleted=0":"").$groupFilter." LIMIT 1";
    if($st = $mysqli->prepare($sqlFind)){
        $st->bind_param('s',$parent_label);
        $st->execute();
        $st->bind_result($pid);
        if($st->fetch()){ $parentId = ($pid!==null) ? (int)$pid : null; }
        $st->close();
    }
}
if($parent_label!=='' && $parentId===null){
    echo json_encode(['status'=>'error','message'=>'親ラベルが見つかりません: '.$parent_label]);
    exit;
}

// knowledge_node_id 手動採番 (MAX+1, 基準値=113)
$nextId = 113;
if($res = $mysqli->query("SELECT MAX($colId) AS max_id FROM $table")){
    $row = $res->fetch_assoc();
    if($row && $row['max_id']!==null){
        $m = (int)$row['max_id'];
        $nextId = ($m >= 113) ? ($m + 1) : 113;
    }
    $res->close();
}

// INSERT: deleted=0 (あれば) + updated_by (あれば)
$userId = isset($_SESSION['USERID']) ? (int)$_SESSION['USERID'] : null;
// Build INSERT dynamically
$colsIns = [$colId, $colParent, $colTitle];
$valsIns = ['?', '?', '?'];
$types = 'iis';
$params = [$nextId, $parentId, $title];

if($colComment){
    $colsIns[] = $colComment;
    $valsIns[] = '?';
    $types .= 's';
    $hasStructuredColumns = ($colTactoWhen || $colTactoWhat || $colTactoWhy || $colOrganizationalBasis);
    $params[] = $hasStructuredColumns ? $comment : __build_structured_comment_fallback($comment, $tactoWhen, $tactoWhat, $tactoWhy, $organizationalBasis);
}
if($colTactoWhen){
    $colsIns[] = $colTactoWhen;
    $valsIns[] = '?';
    $types .= 's';
    $params[] = $tactoWhen;
}
if($colTactoWhat){
    $colsIns[] = $colTactoWhat;
    $valsIns[] = '?';
    $types .= 's';
    $params[] = $tactoWhat;
}
if($colTactoWhy){
    $colsIns[] = $colTactoWhy;
    $valsIns[] = '?';
    $types .= 's';
    $params[] = $tactoWhy;
}
if($colOrganizationalBasis){
    $colsIns[] = $colOrganizationalBasis;
    $valsIns[] = '?';
    $types .= 's';
    $params[] = $organizationalBasis;
}
if($colKFragId && $kfragId !== ''){
    $colsIns[] = $colKFragId;
    $valsIns[] = '?';
    $types .= 's';
    $params[] = $kfragId;
}
// For root categories (parent_id NULL), assign sort_order at creation time.
if($colSort){
    if($parentId === null){
        $nextSort = 1;
        $groupFilter = ($colGroup && $groupId !== '') ? " AND $colGroup='". $mysqli->real_escape_string($groupId) ."'" : "";
        $sqlMax = "SELECT MAX($colSort) AS mx FROM $table WHERE $colParent IS NULL".($hasDeleted?" AND deleted=0":"").$groupFilter;
        if($resMx = $mysqli->query($sqlMax)){
            $rmx = $resMx->fetch_assoc();
            if($rmx && isset($rmx['mx']) && $rmx['mx'] !== null){
                $m = (int)$rmx['mx'];
                $nextSort = ($m >= 1) ? ($m + 1) : 1;
            }
            $resMx->close();
        }
        $colsIns[] = $colSort;
        $valsIns[] = '?';
        $types .= 'i';
        $params[] = $nextSort;
    }
}
if($colGroup && $groupId !== ''){
    $colsIns[] = $colGroup;
    $valsIns[] = '?';
    $types .= 's';
    $params[] = $groupId;
}
if($hasDeleted){
    $colsIns[] = 'deleted';
    $valsIns[] = '0';
}
if($colUpdatedBy){
    $colsIns[] = $colUpdatedBy;
    $valsIns[] = '?';
    $types .= 'i';
    $params[] = $userId;
}
$colsIns[] = 'created_at';
$colsIns[] = 'updated_at';
$valsIns[] = 'NOW()';
$valsIns[] = 'NOW()';

$sqlIns = "INSERT INTO $table (".implode(',', $colsIns).") VALUES (".implode(',', $valsIns).")";
if(!$stmt = $mysqli->prepare($sqlIns)){
    echo json_encode(['status'=>'error','message'=>'prepare失敗: '.$mysqli->error]);
    exit;
}
// bind_param requires references
$bind = [];
$bind[] = $types;
for($i=0; $i<count($params); $i++){
    $bind[] = &$params[$i];
}
call_user_func_array([$stmt, 'bind_param'], $bind);

if(!$stmt->execute()){
    echo json_encode(['status'=>'error','message'=>'INSERT失敗: '.$stmt->error]);
    $stmt->close();
    exit;
}
$stmt->close();
$savedLinkCount = count($fragmentLinkIds);
__save_knowledge_fragment_links($mysqli, (int)$nextId, $fragmentLinkIds, $fragmentSourceType);
$mysqli->close();

echo json_encode(['status'=>'ok','node_id'=>$nextId,'parent_id'=>$parentId,'node_title'=>$title,'fragment_link_count'=>$savedLinkCount]);
