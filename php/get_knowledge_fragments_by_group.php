<?php
// get_knowledge_fragments_by_group.php (forest-mrn)
// Return knowledge fragments list HTML filtered by the selected knowledge group.
//
// - If group_id is provided (GET), filter fragments shared in that group.
// - If not provided, try to use the latest group for the logged-in user.
// - If no group can be determined, fall back to showing all fragments (legacy).

header('Content-Type: text/html; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/connect_db.php';
if(!isset($mysqli) || !($mysqli instanceof mysqli)){
  http_response_code(500);
  echo '<div class="knowledge-fragment-list"><div class="no-fragment-note">DB接続失敗</div></div>';
  exit;
}
@$mysqli->set_charset('utf8mb4');

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$user_id = isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : '';

/**
 * Fetch all rows from a prepared statement as associative arrays without mysqlnd.
 * Returns [] on failure.
 */
function __stmt_fetch_all_assoc(mysqli_stmt $stmt): array {
  $meta = $stmt->result_metadata();
  if (!$meta) { return []; }

  $fields = $meta->fetch_fields();
  $meta->free();
  if (!$fields) { return []; }

  $row = [];
  $bind = [];
  foreach ($fields as $field) {
    $row[$field->name] = null;
    $bind[] = &$row[$field->name];
  }
  if (!call_user_func_array([$stmt, 'bind_result'], $bind)) { return []; }

  $rows = [];
  while ($stmt->fetch()) {
    $copy = [];
    foreach ($row as $k => $v) { $copy[$k] = $v; }
    $rows[] = $copy;
  }
  return $rows;
}

$group_id = isset($_GET['group_id']) ? trim((string)$_GET['group_id']) : '';
if ($group_id === '' && $user_id !== '') {
  // pick latest group for the user
  if ($st = $mysqli->prepare("SELECT group_id FROM kgroup_user_link WHERE user_id = ? ORDER BY created_at DESC LIMIT 1")) {
    $st->bind_param('s', $user_id);
    if ($st->execute()) {
      $rows = __stmt_fetch_all_assoc($st);
      if (!empty($rows)) {
        $row0 = $rows[0];
        if (isset($row0['group_id']) && trim((string)$row0['group_id']) !== '') {
          $group_id = trim((string)$row0['group_id']);
        }
      }
    }
    $st->close();
  }
}

// Current user name (fallback)
$__current_user_name = 'ユーザー';
if ($user_id !== '') {
  if ($stmtU = $mysqli->prepare("SELECT name FROM users WHERE user_id = ? LIMIT 1")) {
    $stmtU->bind_param('s', $user_id);
    if ($stmtU->execute()) {
      $rowsU = __stmt_fetch_all_assoc($stmtU);
      if (!empty($rowsU)) {
        $rowU0 = $rowsU[0];
        if (isset($rowU0['name']) && trim((string)$rowU0['name']) !== '') {
          $__current_user_name = (string)$rowU0['name'];
        }
      }
    }
    $stmtU->close();
  }
}

$__kfrag_list = [];

if ($resT = $mysqli->query("SHOW TABLES LIKE 'experience_knowledges'")) {
  $hasTable = ($resT->num_rows > 0);
  $resT->free();
  if ($hasTable) {
    if ($group_id !== '') {
      $sql = "SELECT DISTINCT ek.experience_knowledge_id AS source_id,
                     ek.knowledge_fragment_content AS content,
                     ek.stage1, ek.stage2, ek.stage3,
                     ek.selected_contents,
                     ek.updated_at AS updated_at,
                     ek.user_id,
                     COALESCE(u.name,'') AS user_name,
                     ek.discussed,
                     'experience' AS source_type
                FROM shared_nodes sn
                INNER JOIN experience_knowledges ek ON sn.experience_knowledge_id = ek.experience_knowledge_id
                LEFT JOIN users u ON u.user_id = ek.user_id
               WHERE sn.deleted = 0
                 AND sn.knowledge_group_id = ?
                 AND ek.deleted = 0
                 AND ek.knowledge_fragment_content IS NOT NULL
                 AND LENGTH(TRIM(ek.knowledge_fragment_content)) > 0
               ORDER BY updated_at DESC, ek.experience_knowledge_id DESC";
      if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('s', $group_id);
        if ($stmt->execute()) {
          $rows = __stmt_fetch_all_assoc($stmt);
          foreach ($rows as $row) {
            $__val = isset($row['content']) ? (string)$row['content'] : '';
            if (trim($__val) === '') { continue; }
            $__user_name = $__current_user_name;
            if (isset($row['user_name']) && trim((string)$row['user_name']) !== '') {
              $__user_name = (string)$row['user_name'];
            }
            $__kfrag_list[] = [
              'content' => $__val,
              'stage1' => isset($row['stage1']) ? (string)$row['stage1'] : '',
              'stage2' => isset($row['stage2']) ? (string)$row['stage2'] : '',
              'stage3' => isset($row['stage3']) ? (string)$row['stage3'] : '',
              'selected_contents' => isset($row['selected_contents']) ? (string)$row['selected_contents'] : '',
              'user_name' => $__user_name,
              'discussed' => isset($row['discussed']) ? (string)$row['discussed'] : '',
              'source_type' => 'experience',
              'source_id' => isset($row['source_id']) ? (int)$row['source_id'] : null,
              'updated_at' => isset($row['updated_at']) ? (string)$row['updated_at'] : ''
            ];
          }
        }
        $stmt->close();
      }
    } else {
      $sql = "SELECT ek.experience_knowledge_id AS source_id,
                     ek.knowledge_fragment_content AS content,
                     ek.stage1, ek.stage2, ek.stage3,
                     ek.selected_contents,
                     ek.updated_at,
                     ek.user_id,
                     COALESCE(u.name,'') AS user_name,
                     ek.discussed,
                     'experience' AS source_type
                FROM experience_knowledges ek
                LEFT JOIN users u ON u.user_id = ek.user_id
               WHERE ek.deleted = 0
                 AND ek.knowledge_fragment_content IS NOT NULL
                 AND LENGTH(TRIM(ek.knowledge_fragment_content)) > 0
               ORDER BY ek.updated_at DESC, ek.experience_knowledge_id DESC";
      if ($stmt = $mysqli->prepare($sql)) {
        if ($stmt->execute()) {
          $rows = __stmt_fetch_all_assoc($stmt);
          foreach ($rows as $row) {
            $__val = isset($row['content']) ? (string)$row['content'] : '';
            if (trim($__val) === '') { continue; }
            $__user_name = $__current_user_name;
            if (isset($row['user_name']) && trim((string)$row['user_name']) !== '') {
              $__user_name = (string)$row['user_name'];
            }
            $__kfrag_list[] = [
              'content' => $__val,
              'stage1' => isset($row['stage1']) ? (string)$row['stage1'] : '',
              'stage2' => isset($row['stage2']) ? (string)$row['stage2'] : '',
              'stage3' => isset($row['stage3']) ? (string)$row['stage3'] : '',
              'selected_contents' => isset($row['selected_contents']) ? (string)$row['selected_contents'] : '',
              'user_name' => $__user_name,
              'discussed' => isset($row['discussed']) ? (string)$row['discussed'] : '',
              'source_type' => 'experience',
              'source_id' => isset($row['source_id']) ? (int)$row['source_id'] : null,
              'updated_at' => isset($row['updated_at']) ? (string)$row['updated_at'] : ''
            ];
          }
        }
        $stmt->close();
      }
    }
  }
}

$groupUserIds = [];
if ($group_id !== '') {
  if ($stmt = $mysqli->prepare("SELECT user_id FROM kgroup_user_link WHERE group_id = ?")) {
    $stmt->bind_param('s', $group_id);
    if ($stmt->execute()) {
      $rows = __stmt_fetch_all_assoc($stmt);
      foreach ($rows as $row) {
        if (isset($row['user_id']) && trim((string)$row['user_id']) !== '') {
          $groupUserIds[] = (string)$row['user_id'];
        }
      }
      $groupUserIds = array_values(array_unique($groupUserIds));
    }
    $stmt->close();
  }
}

if ($resT = $mysqli->query("SHOW TABLES LIKE 'externalized_contents'")) {
  $hasTable = ($resT->num_rows > 0);
  $resT->free();
  if ($hasTable) {
    $contentCol = 'knowledge_fragment_content';
    if ($resCol = $mysqli->query("SHOW COLUMNS FROM `externalized_contents` LIKE 'knowledge_fragments_content'")) {
      if ($resCol->num_rows > 0) { $contentCol = 'knowledge_fragments_content'; }
      $resCol->free();
    }
    $sql = "SELECT ec.externalized_contents_id AS source_id,
                   ec.`{$contentCol}` AS content,
                   ec.stage1, ec.stage2, ec.stage3,
                   ec.selected_contents,
                   ec.updated_at,
                   ec.user_id,
                   COALESCE(u.name,'') AS user_name,
                   ec.discussed,
                   'discussion' AS source_type
              FROM externalized_contents ec
              LEFT JOIN users u ON u.user_id = ec.user_id
             WHERE ec.deleted = 0
               AND ec.`{$contentCol}` IS NOT NULL
               AND LENGTH(TRIM(ec.`{$contentCol}`)) > 0";
    $types = '';
    $params = [];
    if ($group_id !== '') {
      if (!$groupUserIds) {
        $sql .= " AND 1 = 0";
      } else {
        $placeholders = implode(',', array_fill(0, count($groupUserIds), '?'));
        $sql .= " AND ec.user_id IN ($placeholders)";
        $types = str_repeat('s', count($groupUserIds));
        $params = $groupUserIds;
      }
    }
    $sql .= " ORDER BY ec.updated_at DESC, ec.externalized_contents_id DESC";
    if ($stmt = $mysqli->prepare($sql)) {
      if ($types !== '') {
        $bind = [$types];
        foreach ($params as $i => $param) { $bind[] = &$params[$i]; }
        call_user_func_array([$stmt, 'bind_param'], $bind);
      }
      if ($stmt->execute()) {
        $rows = __stmt_fetch_all_assoc($stmt);
        foreach ($rows as $row) {
          $__val = isset($row['content']) ? (string)$row['content'] : '';
          if (trim($__val) === '') { continue; }
          $__user_name = $__current_user_name;
          if (isset($row['user_name']) && trim((string)$row['user_name']) !== '') {
            $__user_name = (string)$row['user_name'];
          }
          $__kfrag_list[] = [
            'content' => $__val,
            'stage1' => isset($row['stage1']) ? (string)$row['stage1'] : '',
            'stage2' => isset($row['stage2']) ? (string)$row['stage2'] : '',
            'stage3' => isset($row['stage3']) ? (string)$row['stage3'] : '',
            'selected_contents' => isset($row['selected_contents']) ? (string)$row['selected_contents'] : '',
            'user_name' => $__user_name,
            'discussed' => isset($row['discussed']) ? (string)$row['discussed'] : '',
            'source_type' => 'discussion',
            'source_id' => isset($row['source_id']) ? (int)$row['source_id'] : null,
            'updated_at' => isset($row['updated_at']) ? (string)$row['updated_at'] : ''
          ];
        }
      }
      $stmt->close();
    }
  }
}

if (!empty($__kfrag_list)) {
  usort($__kfrag_list, function($a, $b) {
    $at = isset($a['updated_at']) ? strtotime((string)$a['updated_at']) : 0;
    $bt = isset($b['updated_at']) ? strtotime((string)$b['updated_at']) : 0;
    if ($at !== $bt) { return ($at > $bt) ? -1 : 1; }
    $ai = isset($a['source_id']) ? intval($a['source_id'], 10) : 0;
    $bi = isset($b['source_id']) ? intval($b['source_id'], 10) : 0;
    return ($ai > $bi) ? -1 : 1;
  });
}

if (!empty($__kfrag_list)) {
  $totalInitial = count($__kfrag_list);
  foreach ($__kfrag_list as $idxInitial => &$itemInitial) {
    $itemInitial['display_num'] = $totalInitial - $idxInitial;
  }
  unset($itemInitial);

  $orderMap = [];
  $canvasMap = [];
  if ($resP = $mysqli->query("SHOW TABLES LIKE 'knowledge_fragment_positions'")) {
    $hasPositions = ($resP->num_rows > 0);
    $resP->free();
    if ($hasPositions) {
      $hasGroupIdInPositions = false;
      if ($resG = $mysqli->query("SHOW COLUMNS FROM `knowledge_fragment_positions` LIKE 'group_id'")) {
        $hasGroupIdInPositions = ($resG->num_rows > 0);
        $resG->free();
      }
      $idsForOrder = [];
      foreach ($__kfrag_list as $item) {
        if (!isset($item['source_type']) || $item['source_type'] !== 'experience') { continue; }
        $idForOrder = isset($item['source_id']) ? intval($item['source_id'], 10) : 0;
        if ($idForOrder > 0) { $idsForOrder[] = $idForOrder; }
      }
      $idsForOrder = array_values(array_unique($idsForOrder));
      if ($idsForOrder) {
        $in = implode(',', array_map('intval', $idsForOrder));
        if ($hasGroupIdInPositions) {
          $groupWhere = ($group_id > 0) ? " AND group_id IN (0,".intval($group_id).")" : " AND group_id = 0";
          $sqlPos = "SELECT externalized_contents_id, group_id, pos_x, pos_y FROM knowledge_fragment_positions WHERE externalized_contents_id IN ($in)$groupWhere ORDER BY group_id ASC";
        } else {
          $sqlPos = "SELECT externalized_contents_id, 0 AS group_id, pos_x, pos_y FROM knowledge_fragment_positions WHERE externalized_contents_id IN ($in)";
        }
        if ($resO = $mysqli->query($sqlPos)) {
          while ($rowO = $resO->fetch_assoc()) {
            $posId = intval($rowO['externalized_contents_id'], 10);
            $orderMap[$posId] = floatval($rowO['pos_y']);
            $canvasMap[$posId] = [
              'x' => isset($rowO['pos_x']) ? floatval($rowO['pos_x']) : 0.0,
              'y' => isset($rowO['pos_y']) ? floatval($rowO['pos_y']) : 0.0
            ];
          }
          $resO->free();
        }
      }
    }
  }
  if (!empty($orderMap)) {
    $indexMap = [];
    foreach ($__kfrag_list as $idx => $item) {
      if (!isset($item['source_type']) || $item['source_type'] !== 'experience') { continue; }
      $idForIndex = isset($item['source_id']) ? intval($item['source_id'], 10) : 0;
      if ($idForIndex > 0) { $indexMap[$idForIndex] = $idx; }
    }
    usort($__kfrag_list, function($a, $b) use ($orderMap, $indexMap) {
      $aid = (isset($a['source_type']) && $a['source_type'] === 'experience' && isset($a['source_id'])) ? intval($a['source_id'], 10) : 0;
      $bid = (isset($b['source_type']) && $b['source_type'] === 'experience' && isset($b['source_id'])) ? intval($b['source_id'], 10) : 0;
      $ap = array_key_exists($aid, $orderMap) ? $orderMap[$aid] : PHP_INT_MAX;
      $bp = array_key_exists($bid, $orderMap) ? $orderMap[$bid] : PHP_INT_MAX;
      if ($ap == $bp) {
        $ai = array_key_exists($aid, $indexMap) ? $indexMap[$aid] : PHP_INT_MAX;
        $bi = array_key_exists($bid, $indexMap) ? $indexMap[$bid] : PHP_INT_MAX;
        if ($ai == $bi) { return 0; }
        return ($ai < $bi) ? -1 : 1;
      }
      return ($ap < $bp) ? -1 : 1;
    });
  }
}

$mysqli->close();
?>
<div class="knowledge-fragment-list">
  <?php if (!empty($__kfrag_list)) {
        $totalK = count($__kfrag_list);
        for ($i = 0; $i < $totalK; $i++) {
            $__kfrag_raw = $__kfrag_list[$i];
            $__tmp = is_array($__kfrag_raw) && isset($__kfrag_raw['content']) ? trim((string)$__kfrag_raw['content']) : '';
            if ($__tmp === '') { continue; }
            $__s1 = isset($__kfrag_raw['stage1']) ? (string)$__kfrag_raw['stage1'] : '';
            $__s2 = isset($__kfrag_raw['stage2']) ? (string)$__kfrag_raw['stage2'] : '';
            $__s3 = isset($__kfrag_raw['stage3']) ? (string)$__kfrag_raw['stage3'] : '';
            $__uname = isset($__kfrag_raw['user_name']) ? (string)$__kfrag_raw['user_name'] : $__current_user_name;
            $__sourceType = isset($__kfrag_raw['source_type']) ? (string)$__kfrag_raw['source_type'] : 'experience';
            $__selectedLabel = ($__sourceType === 'discussion') ? 'discussion' : '経験';
            $__stage1Label = ($__sourceType === 'discussion') ? '【discussionの振り返り】' : '【経験の振り返り】';
            $num = isset($__kfrag_raw['display_num']) ? intval($__kfrag_raw['display_num'], 10) : ($totalK - $i);
  ?>
    <div class="fragment-node-wrapper" data-source-type="<?php echo htmlspecialchars(isset($__kfrag_raw['source_type']) ? (string)$__kfrag_raw['source_type'] : 'experience', ENT_QUOTES, 'UTF-8'); ?>"<?php
      $sourceType = isset($__kfrag_raw['source_type']) ? (string)$__kfrag_raw['source_type'] : 'experience';
      $sourceId = isset($__kfrag_raw['source_id']) ? intval($__kfrag_raw['source_id'],10) : 0;
      if($sourceId>0){ echo ' data-source-id="'.$sourceId.'"'; }
      if($sourceType === 'experience' && $sourceId>0 && isset($canvasMap) && isset($canvasMap[$sourceId])){
        echo ' data-canvas-x="'.htmlspecialchars((string)$canvasMap[$sourceId]['x'], ENT_QUOTES, 'UTF-8').'"';
        echo ' data-canvas-y="'.htmlspecialchars((string)$canvasMap[$sourceId]['y'], ENT_QUOTES, 'UTF-8').'"';
      }
    ?>>
      <div class="fragment-number-badge" aria-hidden="true"><?php echo intval($num,10); ?></div>
      <div class="knowledge_fragment" data-source-type="<?php echo htmlspecialchars($sourceType, ENT_QUOTES, 'UTF-8'); ?>" data-kfrag-num="<?php echo intval($num,10); ?>"<?php
      $disc = isset($__kfrag_raw['discussed']) ? trim($__kfrag_raw['discussed']) : '';
      if($disc!==''){ echo ' data-discussed="'.htmlspecialchars($disc,ENT_QUOTES,'UTF-8').'"'; }
      if($sourceId>0){ echo ' data-source-id="'.$sourceId.'"'; }
    ?>>
        <div class="card-title"><?php echo htmlspecialchars($__uname, ENT_QUOTES, 'UTF-8'); ?> さん</div>
        <div class="card-body"><?php echo nl2br(htmlspecialchars($__tmp, ENT_QUOTES, 'UTF-8')); ?></div>
        <div class="card-detail" aria-hidden="true">
          <?php 
            $__sel = isset($__kfrag_raw['selected_contents']) ? trim((string)$__kfrag_raw['selected_contents']) : '';
            if ($__sel !== '') { ?>
              <div class="selected-utterance"><?php echo htmlspecialchars($__selectedLabel, ENT_QUOTES, 'UTF-8'); ?>: <?php echo nl2br(htmlspecialchars($__sel, ENT_QUOTES, 'UTF-8')); ?></div>
          <?php } ?>
          <div class="qa-item"><div class="qa-q"><?php echo htmlspecialchars($__stage1Label, ENT_QUOTES, 'UTF-8'); ?></div><div class="qa-a"><?php echo nl2br(htmlspecialchars($__s1, ENT_QUOTES, 'UTF-8')); ?></div></div>
          <div class="qa-item"><div class="qa-q">【活動文脈固有の振り返り】</div><div class="qa-a"><?php echo nl2br(htmlspecialchars($__s2, ENT_QUOTES, 'UTF-8')); ?></div></div>
          <div class="qa-item"><div class="qa-q">【研究固有の振り返り】</div><div class="qa-a"><?php echo nl2br(htmlspecialchars($__s3, ENT_QUOTES, 'UTF-8')); ?></div></div>
        </div>
        <div class="card-actions">
          <button type="button" class="detail-button">詳細▼</button>
        </div>
      </div>
    </div>
  <?php } } else { ?>
    <div class="no-fragment-note">表示できるフラグメントがありません。</div>
  <?php } ?>
</div>
