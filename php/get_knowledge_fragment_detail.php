<?php
// get_knowledge_fragment_detail.php
// Input: node_id (knowledge_explorer node) or typed fragment ids.
// Output: HTML snippet for one or more knowledge fragments.
header('Content-Type: text/html; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/connect_db.php';
mysqli_report(MYSQLI_REPORT_OFF);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function split_fragment_ids($value){
    if ($value === null || $value === '') { return []; }
    $parts = is_array($value) ? $value : preg_split('/\s*,\s*/', (string)$value);
    $ids = [];
    foreach ($parts as $part) {
        $id = intval(trim((string)$part), 10);
        if ($id > 0 && !in_array($id, $ids, true)) { $ids[] = $id; }
    }
    return $ids;
}

function table_exists(mysqli $mysqli, string $table): bool {
    $escaped = $mysqli->real_escape_string($table);
    if ($res = $mysqli->query("SHOW TABLES LIKE '{$escaped}'")) {
        $exists = ($res->num_rows > 0);
        $res->free();
        return $exists;
    }
    return false;
}

function column_exists(mysqli $mysqli, string $table, string $column): bool {
    $tableEscaped = $mysqli->real_escape_string($table);
    $columnEscaped = $mysqli->real_escape_string($column);
    if ($res = $mysqli->query("SHOW COLUMNS FROM `{$tableEscaped}` LIKE '{$columnEscaped}'")) {
        $exists = ($res->num_rows > 0);
        $res->free();
        return $exists;
    }
    return false;
}

function add_links(array &$links, string $sourceType, array $ids): void {
    $normalized = strtolower(trim((string)$sourceType));
    if ($normalized === 'externalized') { $sourceType = 'discussion'; }
    elseif ($normalized === 'srl') { $sourceType = 'SRL'; }
    if (!in_array($sourceType, ['experience', 'discussion', 'SRL'], true)) { return; }
    foreach ($ids as $id) {
        $key = $sourceType . ':' . intval($id, 10);
        if ($id > 0 && !isset($links[$key])) {
            $links[$key] = ['type' => $sourceType, 'id' => intval($id, 10)];
        }
    }
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    echo '<div class="error">DB接続失敗</div>';
    exit;
}
@$mysqli->set_charset('utf8mb4');

$nodeId = isset($_GET['node_id']) ? intval($_GET['node_id'], 10) : 0;
$links = [];

if (!$links && $nodeId > 0 && table_exists($mysqli, 'knowledge_explorer_fragment_links')) {
    $sql = "SELECT fragment_source_type, fragment_source_id
              FROM knowledge_explorer_fragment_links
             WHERE knowledge_node_id = ?
          ORDER BY COALESCE(display_order, 999999), id";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('i', $nodeId);
        if ($stmt->execute() && ($res = $stmt->get_result())) {
            while ($row = $res->fetch_assoc()) {
                $sourceType = isset($row['fragment_source_type']) ? (string)$row['fragment_source_type'] : '';
                $sourceId = isset($row['fragment_source_id']) ? intval($row['fragment_source_id'], 10) : 0;
                add_links($links, $sourceType, [$sourceId]);
            }
            $res->free();
        }
        $stmt->close();
    }
}

if (!$links && $nodeId <= 0) {
    if (isset($_GET['experience_knowledge_id'])) { add_links($links, 'experience', split_fragment_ids($_GET['experience_knowledge_id'])); }
    if (isset($_GET['externalized_contents_id'])) { add_links($links, 'discussion', split_fragment_ids($_GET['externalized_contents_id'])); }
    if (isset($_GET['discussion_history_id'])) { add_links($links, 'discussion', split_fragment_ids($_GET['discussion_history_id'])); }
    if (!$links && isset($_GET['fragment_ids'])) { add_links($links, 'experience', split_fragment_ids($_GET['fragment_ids'])); }
    if (!$links && isset($_GET['fragment_id'])) { add_links($links, 'experience', split_fragment_ids($_GET['fragment_id'])); }
}

if (!$links && $nodeId > 0 && table_exists($mysqli, 'knowledge_explorer')) {
    $colNodeId = null;
    $colKfragId = null;
    $colExtId = null;
    if ($cols = $mysqli->query("SHOW COLUMNS FROM `knowledge_explorer`")) {
        while ($c = $cols->fetch_assoc()) {
            $f = isset($c['Field']) ? $c['Field'] : '';
            $lf = strtolower($f);
            if ($colNodeId === null && in_array($lf, ['node_id','id','knowledge_node_id','knowledge_explorer_id'], true)) { $colNodeId = $f; }
            if ($colKfragId === null && in_array($lf, ['knowledge_fragment_id','knowledgefragment_id','kfrag_id'], true)) { $colKfragId = $f; }
            if ($colExtId === null && in_array($lf, ['externalized_contents_id','externalizedcontent_id','externalized_id'], true)) { $colExtId = $f; }
        }
        $cols->free();
    }
    if ($colNodeId !== null) {
        $selects = [];
        if ($colKfragId !== null) { $selects[] = "`$colKfragId` AS kfrag_id"; }
        if ($colExtId !== null) { $selects[] = "`$colExtId` AS ext_id"; }
        if ($selects) {
            $sql = "SELECT ".implode(',', $selects)." FROM `knowledge_explorer` WHERE `$colNodeId` = ? LIMIT 1";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param('i', $nodeId);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    if ($row = $res->fetch_assoc()) {
                        if (isset($row['kfrag_id'])) { add_links($links, 'experience', split_fragment_ids($row['kfrag_id'])); }
                        if (isset($row['ext_id'])) { add_links($links, 'discussion', split_fragment_ids($row['ext_id'])); }
                    }
                    $res->free();
                }
                $stmt->close();
            }
        }
    }
}

if (!$links) {
    echo '<div class="error">'.h('フラグメントIDが特定できませんでした（node_id='.$nodeId.'）。').'</div>';
    exit;
}

$orderedLinks = array_values($links);
$idsByType = ['experience' => [], 'discussion' => [], 'SRL' => []];
foreach ($orderedLinks as $link) {
    $idsByType[$link['type']][] = $link['id'];
}

$rows = [];
if ($idsByType['experience'] && table_exists($mysqli, 'experience_knowledges')) {
    $ids = $idsByType['experience'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT ek.experience_knowledge_id AS source_id,
                   ek.knowledge_fragment_content AS content,
                   ek.selected_contents,
                   ek.stage1,
                   ek.stage2,
                   ek.stage3,
                   COALESCE(u.name,'') AS user_name
              FROM experience_knowledges ek
              LEFT JOIN users u ON u.user_id = ek.user_id
             WHERE ek.experience_knowledge_id IN ($placeholders)
               AND ek.deleted = 0";
    $types = str_repeat('i', count($ids));
    if ($stmt = $mysqli->prepare($sql)) {
        $bind = [$types];
        foreach ($ids as $i => $id) { $bind[] = &$ids[$i]; }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        if ($stmt->execute() && ($res = $stmt->get_result())) {
            while ($row = $res->fetch_assoc()) {
                $rows['experience:' . (int)$row['source_id']] = $row + ['source_type' => 'experience'];
            }
            $res->free();
        }
        $stmt->close();
    }
}

if ($idsByType['discussion'] && table_exists($mysqli, 'externalized_contents')) {
    $ids = $idsByType['discussion'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $contentCol = column_exists($mysqli, 'externalized_contents', 'knowledge_fragments_content') ? 'knowledge_fragments_content' : 'knowledge_fragment_content';
    $sql = "SELECT ec.externalized_contents_id AS source_id,
                   ec.`$contentCol` AS content,
                   ec.selected_contents,
                   ec.stage1,
                   ec.stage2,
                   ec.stage3,
                   COALESCE(u.name,'') AS user_name
              FROM externalized_contents ec
              LEFT JOIN users u ON u.user_id = ec.user_id
             WHERE ec.externalized_contents_id IN ($placeholders)
               AND ec.deleted = 0";
    $types = str_repeat('i', count($ids));
    if ($stmt = $mysqli->prepare($sql)) {
        $bind = [$types];
        foreach ($ids as $i => $id) { $bind[] = &$ids[$i]; }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        if ($stmt->execute() && ($res = $stmt->get_result())) {
            while ($row = $res->fetch_assoc()) {
                $rows['discussion:' . (int)$row['source_id']] = $row + ['source_type' => 'discussion'];
            }
            $res->free();
        }
        $stmt->close();
    }
}

echo '<div class="knowledge-detail-summary">選択したフラグメント数: '.count($orderedLinks).'</div>';

foreach ($orderedLinks as $idx => $link) {
    $key = $link['type'] . ':' . $link['id'];
    if (!isset($rows[$key])) {
        if ($link['type'] === 'SRL') {
            echo '<div class="knowledge-detail-fragment error">SRL:'.h($link['id']).' の詳細取得先が未設定です。</div>';
            continue;
        }
        echo '<div class="knowledge-detail-fragment error">'.h($key).' の情報が見つかりません。</div>';
        continue;
    }
    $row = $rows[$key];
    $content = isset($row['content']) ? (string)$row['content'] : '';
    $userName = isset($row['user_name']) && trim((string)$row['user_name']) !== '' ? (string)$row['user_name'] : 'ユーザー';
    $selected = isset($row['selected_contents']) ? (string)$row['selected_contents'] : '';
    $s1 = isset($row['stage1']) ? (string)$row['stage1'] : '';
    $s2 = isset($row['stage2']) ? (string)$row['stage2'] : '';
    $s3 = isset($row['stage3']) ? (string)$row['stage3'] : '';
    ?>
    <div class="knowledge-detail-fragment knowledge_fragment" data-source-type="<?php echo h($link['type']); ?>" data-source-id="<?php echo (int)$link['id']; ?>">
      <div class="card-title">フラグメント<?php echo (int)($idx + 1); ?>: <?php echo nl2br(h($content)); ?></div>
      <div class="card-meta">種類: <?php echo h($link['type']); ?> / 共有したユーザー: <?php echo h($userName); ?></div>
      <?php if (trim($selected) !== '') { ?>
        <div class="selected-utterance">経験: <?php echo nl2br(h($selected)); ?></div>
      <?php } ?>
      <div class="card-detail" aria-hidden="false">
        <div class="qa-item"><div class="qa-q">【経験の振り返り】</div><div class="qa-a"><?php echo nl2br(h($s1)); ?></div></div>
        <div class="qa-item"><div class="qa-q">【活動文脈固有の振り返り】</div><div class="qa-a"><?php echo nl2br(h($s2)); ?></div></div>
        <div class="qa-item"><div class="qa-q">【研究固有の振り返り】</div><div class="qa-a"><?php echo nl2br(h($s3)); ?></div></div>
      </div>
    </div>
    <?php
}
