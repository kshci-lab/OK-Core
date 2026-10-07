<?php
// get_knowledge_tree.php
// knowledge_explorer テーブルから階層表示用ノード一覧を取得（トップレベル3種を保証）
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
// 環境側で MYSQLI_REPORT_STRICT が有効だと mysqli_* が例外を投げて 500 になりやすいので、このAPI内では例外化を無効化
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/session_bootstrap.php';
hcimlab_start_session();
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/knowledge_group_access.php';

if(!isset($mysqli) || !($mysqli instanceof mysqli)){
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'DB接続失敗']);
    exit;
}
@$mysqli->set_charset('utf8mb4');
$requestedGroupId = isset($_GET['group_id']) ? trim((string)$_GET['group_id']) : '';
$userId = isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : '';
$groupAccess = ok_core_resolve_active_group($mysqli, $requestedGroupId, $userId);
if ($groupAccess['status'] !== 200) {
    http_response_code($groupAccess['status']);
    echo json_encode(['status' => 'error', 'message' => $groupAccess['status'] === 403 ? 'forbidden_group' : 'group_unavailable']);
    exit;
}
$selectedGroupId = $groupAccess['group_id'];
if ($selectedGroupId === '') {
    echo json_encode(['status' => 'ok', 'nodes' => [], 'selected_group_id' => null]);
    exit;
}

function __split_knowledge_tree_ids($value): array {
    if ($value === null || $value === '') { return []; }
    $parts = preg_split('/\s*,\s*/', (string)$value);
    $ids = [];
    foreach ($parts as $part) {
        $id = intval(trim((string)$part), 10);
        if ($id > 0 && !in_array($id, $ids, true)) { $ids[] = $id; }
    }
    return $ids;
}

function __normalize_knowledge_tree_source_type($value): string {
    $type = strtolower(trim((string)$value));
    if ($type === 'discussion') { return 'discussion'; }
    if ($type === 'srl') { return 'SRL'; }
    if (in_array($type, ['experience', 'discussion'], true)) { return $type; }
    if ((string)$value === 'SRL') { return 'SRL'; }
    return '';
}

// テーブル存在チェック
$table = 'knowledge_explorer';
$tbl = $mysqli->query("SHOW TABLES LIKE '".$mysqli->real_escape_string($table)."'");
if(!$tbl){
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'テーブル存在確認エラー: '.$mysqli->error]);
    exit;
}
if($tbl->num_rows===0){
    http_response_code(503);
    echo json_encode(['status'=>'error','message'=>'knowledge_explorer テーブルがありません']);
    exit;
}
$tbl->close();

// カラム定義を柔軟に解決（互換のため候補名を許容）
$colId = null;      // knowledge_node_id / node_id / id / knowledge_explorer_id
$colParent = null;  // parent_id / parent / pid / parent_node_id
$colTitle = null;   // node_title / title / name / label
$colComment = null; // comment / comments / note / notes / memo
$colUpdated = null; // updated_at / update_at / updated / modified_at
$colUpdatedBy = null; // updated_by （ユーザID）
$colKFragId = null;   // knowledge_fragment_id（外部化IDと同一扱い）
$colExtContentsId = null; // externalized_contents_id（外部化のPK）
$colSort = null; // sort_order
$colGroup = null; // knowledge_group_id
$colTactoWhen = null;
$colTactoWhat = null;
$colTactoWhy = null;
$colOrganizationalBasis = null;
$colLegacyKfCommonPoints = null;
$hasDeleted = false;
$idIsAutoInc = false;
if ($resCols = $mysqli->query("SHOW COLUMNS FROM $table")) {
    while($c = $resCols->fetch_assoc()){
        $f = isset($c['Field']) ? $c['Field'] : '';
        // カラム名はASCII前提のため mbstring 依存を避ける
        $lf = strtolower($f);
        if($colId===null && in_array($lf, ['knowledge_node_id','node_id','id','knowledge_explorer_id'])){ $colId = $f; }
        if($colParent===null && in_array($lf, ['parent_id','parent','pid','parent_node_id'])){ $colParent = $f; }
        if($colTitle===null && in_array($lf, ['node_title','title','name','label'])){ $colTitle = $f; }
        if($colComment===null && in_array($lf, ['comment','comments','note','notes','memo'])){ $colComment = $f; }
        if($colUpdated===null && in_array($lf, ['updated_at','update_at','updated','modified_at'])){ $colUpdated = $f; }
        if($colUpdatedBy===null && in_array($lf, ['updated_by'])){ $colUpdatedBy = $f; }
        if($colKFragId===null && in_array($lf, ['knowledge_fragment_id','knowledgefragment_id','kfrag_id'])){ $colKFragId = $f; }
        if($colExtContentsId===null && in_array($lf, ['externalized_contents_id','externalizedcontent_id','externalized_id'])){ $colExtContentsId = $f; }
        if($colSort===null && in_array($lf, ['sort_order'])){ $colSort = $f; }
        if($colGroup===null && in_array($lf, ['knowledge_group_id','group_id'])){ $colGroup = $f; }
        if($colTactoWhen===null && $lf === 'tacto_when'){ $colTactoWhen = $f; }
        if($colTactoWhat===null && $lf === 'tacto_what'){ $colTactoWhat = $f; }
        if($colTactoWhy===null && $lf === 'tacto_why'){ $colTactoWhy = $f; }
        if($colOrganizationalBasis===null && $lf === 'organizational_basis'){ $colOrganizationalBasis = $f; }
        if($colLegacyKfCommonPoints===null && $lf === 'kf_common_points'){ $colLegacyKfCommonPoints = $f; }
        if($lf === 'deleted'){ $hasDeleted = true; }
        if($f === $colId && isset($c['Extra']) && stripos($c['Extra'], 'auto_increment') !== false){ $idIsAutoInc = true; }
    }
    $resCols->close();
}
// 少なくともタイトルが無いと表示不能
$groupWhere = '';
$groupWhereAlias = '';
if($colGroup !== null && $selectedGroupId !== ''){
    $escapedGroupId = $mysqli->real_escape_string($selectedGroupId);
    $groupWhere = " AND `$colGroup` = '$escapedGroupId'";
    $groupWhereAlias = " AND ke.`$colGroup` = '$escapedGroupId'";
}
if($colTitle === null){
    // 最低限のフォールバック: DBスキーマが未整備でもトップレベルだけ返す
    $fallback = [
        ['node_id'=>1, 'parent_id'=>null, 'node_title'=>'知識関連', 'comment'=>null, 'updated_at'=>null],
        ['node_id'=>2, 'parent_id'=>null, 'node_title'=>'研究方略関連', 'comment'=>null, 'updated_at'=>null],
        ['node_id'=>3, 'parent_id'=>null, 'node_title'=>'その他', 'comment'=>null, 'updated_at'=>null]
    ];
    echo json_encode(['status'=>'ok','nodes'=>$fallback]);
    exit;
}

// 全ノード取得（動的カラム名で取得）
$nodes = [];
// 全行取得: SELECT * を使い、PHP側でカラム存在を判定して nodes 配列を構築
if($colUpdatedBy){
    $whereAll = [];
    if($hasDeleted){ $whereAll[] = "ke.deleted=0"; }
    if($groupWhereAlias !== ''){ $whereAll[] = substr($groupWhereAlias, 5); }
    $sqlAll = "SELECT ke.*, u.name AS updated_by_name FROM $table ke LEFT JOIN users u ON ke.$colUpdatedBy = u.user_id".(!empty($whereAll) ? " WHERE ".implode(" AND ", $whereAll) : "");
} else {
    $whereAll = [];
    if($hasDeleted){ $whereAll[] = "deleted=0"; }
    if($groupWhere !== ''){ $whereAll[] = substr($groupWhere, 5); }
    $sqlAll = "SELECT * FROM $table".(!empty($whereAll) ? " WHERE ".implode(" AND ", $whereAll) : "");
}
if($resAll = $mysqli->query($sqlAll)){
    while($row = $resAll->fetch_assoc()){
        // id
        $nid = null;
        if($colId && isset($row[$colId])){ $nid = (int)$row[$colId]; }
        elseif(isset($row['node_id'])){ $nid = (int)$row['node_id']; }
        elseif(isset($row['id'])){ $nid = (int)$row['id']; }
        // parent
        $pid = null;
        if($colParent && array_key_exists($colParent, $row) && $row[$colParent]!==null){ $pid = (int)$row[$colParent]; }
        elseif(array_key_exists('parent_id',$row) && $row['parent_id']!==null){ $pid = (int)$row['parent_id']; }
        // title
        $title = null;
        if($colTitle && isset($row[$colTitle])){ $title = $row[$colTitle]; }
        elseif(isset($row['node_title'])){ $title = $row['node_title']; }
        elseif(isset($row['title'])){ $title = $row['title']; }
        // comment/updated
        $commentVal = null; $updatedVal = null;
        if($colComment && array_key_exists($colComment,$row)) { $commentVal = $row[$colComment]; }
        elseif(array_key_exists('comment',$row)) { $commentVal = $row['comment']; }
        $tactoWhenVal = ($colTactoWhen && array_key_exists($colTactoWhen,$row)) ? $row[$colTactoWhen] : null;
        $tactoWhatVal = ($colTactoWhat && array_key_exists($colTactoWhat,$row)) ? $row[$colTactoWhat] : null;
        $tactoWhyVal = ($colTactoWhy && array_key_exists($colTactoWhy,$row)) ? $row[$colTactoWhy] : null;
        $organizationalBasisVal = ($colOrganizationalBasis && array_key_exists($colOrganizationalBasis,$row)) ? $row[$colOrganizationalBasis] : null;
        if(($organizationalBasisVal === null || trim((string)$organizationalBasisVal) === '') && $colLegacyKfCommonPoints && array_key_exists($colLegacyKfCommonPoints,$row)){
            $organizationalBasisVal = $row[$colLegacyKfCommonPoints];
        }
        if($colUpdated && array_key_exists($colUpdated,$row)) { $updatedVal = $row[$colUpdated]; }
        elseif(array_key_exists('updated_at',$row)) { $updatedVal = $row['updated_at']; }
        $updatedById = null; $updatedByName = null;
        if($colUpdatedBy && array_key_exists($colUpdatedBy,$row)) { $updatedById = $row[$colUpdatedBy]; }
        if(array_key_exists('updated_by_name',$row)) { $updatedByName = $row['updated_by_name']; }
        $sortVal = null;
        if($colSort && array_key_exists($colSort,$row) && $row[$colSort] !== null){ $sortVal = (int)$row[$colSort]; }
        // 参照ID（存在すれば付与）
        // knowledge_fragment_id は CSV 文字列を保持する（例: "11139,11140"）
        $kfragVal = null;
        if($colKFragId && array_key_exists($colKFragId,$row)){
            if($row[$colKFragId] !== null){
                $s = trim((string)$row[$colKFragId]);
                $kfragVal = ($s !== '') ? $s : null;
            }
        }
        $extIdVal = null;
        if($colExtContentsId && array_key_exists($colExtContentsId,$row) && $row[$colExtContentsId] !== null){
            $extIdVal = (int)$row[$colExtContentsId];
        }
        $groupVal = null;
        if($colGroup && array_key_exists($colGroup,$row) && $row[$colGroup] !== null){
            $groupVal = (int)$row[$colGroup];
        }
        $nodes[] = [
            'node_id'=>$nid,
            'parent_id'=>$pid,
            'node_title'=>$title,
            'comment'=> $commentVal !== null ? $commentVal : null,
            'tacto_when'=> $tactoWhenVal !== null ? $tactoWhenVal : null,
            'tacto_what'=> $tactoWhatVal !== null ? $tactoWhatVal : null,
            'tacto_why'=> $tactoWhyVal !== null ? $tactoWhyVal : null,
            'organizational_basis'=> $organizationalBasisVal !== null ? $organizationalBasisVal : null,
            'updated_at'=> $updatedVal !== null ? $updatedVal : null,
            'updated_by'=> $updatedById !== null ? (int)$updatedById : null,
            'updated_by_name'=> $updatedByName !== null ? $updatedByName : null,
            'sort_order'=> $sortVal !== null ? $sortVal : null,
            'knowledge_group_id'=> $groupVal !== null ? $groupVal : null,
            'knowledge_fragment_id'=> $kfragVal !== null ? $kfragVal : null,
            'externalized_contents_id'=> $extIdVal !== null ? $extIdVal : null
        ];
    }
    $resAll->close();
} else {
    // 取得失敗時もフォールバック（トップレベル3種）
    $nodes = [
        ['node_id'=>1, 'parent_id'=>null, 'node_title'=>'知識関連', 'comment'=>null, 'updated_at'=>null],
        ['node_id'=>2, 'parent_id'=>null, 'node_title'=>'研究方略関連', 'comment'=>null, 'updated_at'=>null],
        ['node_id'=>3, 'parent_id'=>null, 'node_title'=>'その他', 'comment'=>null, 'updated_at'=>null]
    ];
}

$allowedExperienceIds = [];
$allowedDiscussionIds = [];
$groupIdForRefs = (int)$selectedGroupId;
if ($res = $mysqli->query("SELECT DISTINCT ek.experience_knowledge_id
                             FROM shared_nodes sn
                             INNER JOIN experience_knowledges ek ON ek.experience_knowledge_id = sn.experience_knowledge_id
                            WHERE sn.knowledge_group_id = $groupIdForRefs AND sn.deleted = 0 AND ek.deleted = 0")) {
    while ($row = $res->fetch_assoc()) { $allowedExperienceIds[(int)$row['experience_knowledge_id']] = true; }
    $res->free();
}
if ($res = $mysqli->query("SELECT externalized_contents_id
                             FROM externalized_contents
                            WHERE group_id = $groupIdForRefs AND deleted = 0")) {
    while ($row = $res->fetch_assoc()) { $allowedDiscussionIds[(int)$row['externalized_contents_id']] = true; }
    $res->free();
}
foreach ($nodes as &$node) {
    if (!empty($node['knowledge_fragment_id'])) {
        $visibleIds = array_values(array_filter(__split_knowledge_tree_ids($node['knowledge_fragment_id']), static function ($id) use ($allowedExperienceIds, $allowedDiscussionIds) {
            return isset($allowedExperienceIds[$id]);
        }));
        $node['knowledge_fragment_id'] = $visibleIds ? implode(',', $visibleIds) : null;
    }
    if (!empty($node['externalized_contents_id']) && !isset($allowedDiscussionIds[(int)$node['externalized_contents_id']])) {
        $node['externalized_contents_id'] = null;
    }
}
unset($node);

$linkTypesByNode = [];
$linkIdsByNode = [];
$hasAnyLinksByNode = [];
if (!empty($nodes)) {
    $nodeIds = [];
    foreach ($nodes as $node) {
        $nidForLink = isset($node['node_id']) ? intval($node['node_id'], 10) : 0;
        if ($nidForLink > 0) { $nodeIds[] = $nidForLink; }
    }
    $nodeIds = array_values(array_unique($nodeIds));
    if ($nodeIds) {
        if ($resLinkTable = $mysqli->query("SHOW TABLES LIKE 'knowledge_explorer_fragment_links'")) {
            $hasLinkTable = ($resLinkTable->num_rows > 0);
            $resLinkTable->free();
            if ($hasLinkTable) {
                $inNodeIds = implode(',', array_map('intval', $nodeIds));
                $sqlLinks = "SELECT knowledge_node_id, fragment_source_type, fragment_source_id
                               FROM knowledge_explorer_fragment_links
                              WHERE knowledge_node_id IN ($inNodeIds)
                           ORDER BY COALESCE(display_order, 999999), id";
                if ($resLinks = $mysqli->query($sqlLinks)) {
                    while ($linkRow = $resLinks->fetch_assoc()) {
                        $linkNodeId = isset($linkRow['knowledge_node_id']) ? intval($linkRow['knowledge_node_id'], 10) : 0;
                        $sourceType = __normalize_knowledge_tree_source_type(isset($linkRow['fragment_source_type']) ? $linkRow['fragment_source_type'] : '');
                        $sourceId = isset($linkRow['fragment_source_id']) ? intval($linkRow['fragment_source_id'], 10) : 0;
                        if ($linkNodeId <= 0 || $sourceType === '' || $sourceId <= 0) { continue; }
                        $hasAnyLinksByNode[$linkNodeId] = true;
                        if ($sourceType === 'experience' && !isset($allowedExperienceIds[$sourceId])) { continue; }
                        if ($sourceType === 'discussion' && !isset($allowedDiscussionIds[$sourceId])) { continue; }
                        if (!isset($linkTypesByNode[$linkNodeId])) { $linkTypesByNode[$linkNodeId] = []; }
                        if (!isset($linkIdsByNode[$linkNodeId])) { $linkIdsByNode[$linkNodeId] = []; }
                        if (!in_array($sourceType, $linkTypesByNode[$linkNodeId], true)) { $linkTypesByNode[$linkNodeId][] = $sourceType; }
                        if (!isset($linkIdsByNode[$linkNodeId][$sourceType])) { $linkIdsByNode[$linkNodeId][$sourceType] = []; }
                        if (!in_array($sourceId, $linkIdsByNode[$linkNodeId][$sourceType], true)) { $linkIdsByNode[$linkNodeId][$sourceType][] = $sourceId; }
                    }
                    $resLinks->free();
                }
            }
        }
    }
    foreach ($nodes as &$node) {
        $nidForLink = isset($node['node_id']) ? intval($node['node_id'], 10) : 0;
        $types = isset($linkTypesByNode[$nidForLink]) ? $linkTypesByNode[$nidForLink] : [];
        $idsByType = isset($linkIdsByNode[$nidForLink]) ? $linkIdsByNode[$nidForLink] : [];
        if (isset($hasAnyLinksByNode[$nidForLink])) {
            $node['knowledge_fragment_id'] = !empty($idsByType['experience'])
                ? implode(',', $idsByType['experience']) : null;
            $node['externalized_contents_id'] = !empty($idsByType['discussion'])
                ? $idsByType['discussion'][0] : null;
        }
        if (empty($types) && !isset($hasAnyLinksByNode[$nidForLink])) {
            if (!empty($node['externalized_contents_id'])) {
                $types[] = 'discussion';
                $idsByType['discussion'] = [(int)$node['externalized_contents_id']];
            } elseif (!empty($node['knowledge_fragment_id'])) {
                $fallbackIds = __split_knowledge_tree_ids($node['knowledge_fragment_id']);
                if ($fallbackIds) {
                    $types[] = 'experience';
                    $idsByType['experience'] = $fallbackIds;
                }
            }
        }
        $node['fragment_source_types'] = array_values(array_unique($types));
        $node['fragment_source_ids'] = $idsByType;
    }
    unset($node);
}
$mysqli->close();

echo json_encode(['status'=>'ok','nodes'=>$nodes]);
