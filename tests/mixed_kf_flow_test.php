<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../php/connect_db.php';
require_once __DIR__ . '/../php/discussion_history_store.php';
require_once __DIR__ . '/../php/session_bootstrap.php';

function mixed_check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function mixed_shadow(mysqli $db, string $table, array $rows = []): void {
    $ddl = $db->query('SHOW CREATE TABLE `' . $table . '`')->fetch_row()[1];
    $db->query(preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $ddl));
    foreach ($rows as $row) {
        $columns = implode(',', array_map(fn($key) => '`' . $key . '`', array_keys($row)));
        $values = array_values($row);
        $stmt = $db->prepare('INSERT INTO `' . $table . '` (' . $columns . ') VALUES ('
            . implode(',', array_fill(0, count($values), '?')) . ')');
        $stmt->bind_param(str_repeat('s', count($values)), ...$values);
        $stmt->execute();
        $stmt->close();
    }
}

mixed_check($mysqli instanceof mysqli, 'DB unavailable.');
$fixture = $mysqli->query("SELECT sn.knowledge_fragment_id AS id, sn.knowledge_group_id AS group_id
    FROM shared_nodes sn JOIN shared_nodes ds ON ds.knowledge_fragment_id = sn.knowledge_fragment_id
      AND ds.fragment_source_type = 'discussion' AND ds.knowledge_group_id = sn.knowledge_group_id AND ds.deleted = 0
    JOIN experience_knowledges ek ON ek.experience_knowledge_id = sn.knowledge_fragment_id AND ek.deleted = 0
    JOIN externalized_contents ec ON ec.externalized_contents_id = sn.knowledge_fragment_id AND ec.deleted = 0
    WHERE sn.fragment_source_type = 'experience' AND sn.deleted = 0 LIMIT 1")->fetch_assoc();
mixed_check((bool)$fixture, 'Requires a shared numeric ID present in both KF tables.');
$id = (int)$fixture['id'];
$group = (int)$fixture['group_id'];
$member = $mysqli->query('SELECT user_id FROM kgroup_user_link WHERE group_id = ' . $group . ' AND deleted = 0 LIMIT 1')->fetch_assoc();
mixed_check((bool)$member, 'Requires a group member.');
$user = (int)$member['user_id'];
$refs = ['experience:' . $id, 'discussion:' . $id];
$case = $argv[1] ?? 'store';
mixed_shadow($mysqli, 'discussion_history');
mixed_shadow($mysqli, 'knowledge_explorer');
mixed_shadow($mysqli, 'knowledge_explorer_fragment_links');

if ($case === 'store') {
    ok_core_insert_discussion($mysqli, $user, 'mixed', $refs);
    ok_core_insert_discussion($mysqli, $user, 'legacy experience', [$refs[0]]);
    ok_core_insert_discussion($mysqli, $user, 'legacy discussion', [$refs[1]]);
    $experience = ok_core_discussion_rows($mysqli, [$refs[0]], (string)$group);
    $discussion = ok_core_discussion_rows($mysqli, [$refs[1]], (string)$group);
    mixed_check(count($experience) === 2 && count($discussion) === 2, 'History must match typed references and legacy rows.');
    mixed_check(count(ok_core_discussion_rows($mysqli, $refs, (string)$group)) === 3, 'Mixed selection must not duplicate posts.');
    mixed_check(!in_array('legacy discussion', array_column($experience, 'content'), true), 'Equal IDs must not mix legacy sources.');
    mixed_check(!in_array('legacy experience', array_column($discussion, 'content'), true), 'Equal IDs must not mix legacy sources.');
    mixed_check(ok_core_discussion_stored_refs(['fragment_source_type'=>'mixed', 'knowledge_fragment_id'=>implode(',', $refs)]) === $refs, 'Mixed targets must round-trip.');
    $hidden = $mysqli->query("SELECT sn.knowledge_fragment_id AS id FROM shared_nodes sn
        JOIN experience_knowledges ek ON ek.experience_knowledge_id = sn.knowledge_fragment_id AND ek.deleted = 0
        WHERE sn.fragment_source_type = 'experience' AND sn.deleted = 0 AND NOT EXISTS
          (SELECT 1 FROM shared_nodes allowed WHERE allowed.fragment_source_type = 'experience'
           AND allowed.knowledge_fragment_id = sn.knowledge_fragment_id AND allowed.knowledge_group_id = $group AND allowed.deleted = 0)
        LIMIT 1")->fetch_assoc();
    if ($hidden) {
        ok_core_insert_discussion($mysqli, $user, 'inaccessible target', [$refs[0], 'experience:' . $hidden['id']]);
        mixed_check(count(ok_core_discussion_rows($mysqli, [$refs[0]], (string)$group)) === 2, 'Posts with an unshared target must be hidden.');
    }
    echo "PASS: mixed and legacy history, same-ID isolation, deduplication and target access.\n";
    exit;
}

ini_set('session.use_cookies', '0');
hcimlab_start_session();
$_SESSION['USERID'] = (string)$user;
$_SERVER['REQUEST_METHOD'] = in_array($case, ['save', 'create', 'update', 'status_experience', 'status_discussion'], true) ? 'POST' : 'GET';
$_GET = ['group_id'=>(string)$group, 'fragment_refs'=>json_encode([$refs[0]])];
$_POST = ['group_id'=>(string)$group, 'fragment_refs'=>json_encode($refs), 'content'=>'mixed endpoint', 'node_title'=>'Mixed node'];
$post = ok_core_insert_discussion($mysqli, $user, 'mixed fixture', $refs);
if (in_array($case, ['tree', 'update'], true)) {
    $mysqli->query("INSERT INTO knowledge_explorer (knowledge_node_id, node_title, knowledge_group_id) VALUES (1, 'Mixed fixture', $group)");
    foreach ($refs as $order => $ref) {
        [$type, $sourceId] = explode(':', $ref);
        $stmt = $mysqli->prepare('INSERT INTO knowledge_explorer_fragment_links (knowledge_node_id, fragment_source_type, fragment_source_id, display_order) VALUES (1, ?, ?, ?)');
        $stmt->bind_param('sii', $type, $sourceId, $order);
        $stmt->execute();
        $stmt->close();
    }
    $_POST['node_id'] = '1';
}
if (in_array($case, ['relations', 'status_experience', 'status_discussion'], true)) {
    foreach (['experience_knowledges'=>'experience_knowledge_id', 'externalized_contents'=>'externalized_contents_id'] as $table=>$pk) {
        $row = $mysqli->query("SELECT * FROM $table WHERE $pk = $id")->fetch_assoc();
        $row['discussed'] = $case === 'relations' ? 'UNDERWAY' : 'YET';
        mixed_shadow($mysqli, $table, [$row]);
    }
}
if (str_starts_with($case, 'status_')) {
    $type = substr($case, 7);
    $_POST['fragment_source_type'] = $type;
    $_POST[$type === 'experience' ? 'experience_knowledge_id' : 'externalized_contents_id'] = (string)$id;
    $_POST['status'] = 'UNDERWAY';
}
$endpoints = ['save'=>'save_discussion_history.php', 'get'=>'get_discussion_history.php',
    'targets'=>'get_discussion_targets.php', 'relations'=>'get_underway_kfrag_relations.php',
    'create'=>'knowledge_node_create.php', 'update'=>'update_knowledge_node.php', 'tree'=>'get_knowledge_tree.php',
    'status_experience'=>'update_discussed_status.php', 'status_discussion'=>'update_discussed_status.php'];
mixed_check(isset($endpoints[$case]), 'Unknown test case.');
ob_start();
register_shutdown_function(function() use ($case, $refs, $mysqli, $id) {
    $result = json_decode(ob_get_clean(), true);
    $ok = (http_response_code() ?: 200) === 200 && ($result['status'] ?? '') === 'ok';
    if ($case === 'get') $ok = $ok && count($result['items'] ?? []) === 1 && $result['items'][0]['fragment_refs'] === $refs;
    if ($case === 'targets') $ok = $ok && ($result['fragment_refs'] ?? []) === $refs;
    if ($case === 'relations') $ok = $ok && count($result['relations'] ?? []) === 1;
    if ($case === 'tree') $ok = $ok && count($result['nodes'] ?? []) === 1
        && count($result['nodes'][0]['fragment_source_types']) === 2;
    if (in_array($case, ['create', 'update'], true)) {
        $node = (int)($result['node_id'] ?? 0);
        $types = $mysqli->query('SELECT DISTINCT fragment_source_type FROM knowledge_explorer_fragment_links WHERE knowledge_node_id = ' . $node)->fetch_all(MYSQLI_ASSOC);
        $ok = $ok && count($types) === 2 && (int)$result['fragment_link_count'] === 2;
    }
    if ($case === 'save') $ok = $ok && ($result['fragment_refs'] ?? []) === $refs;
    if (str_starts_with($case, 'status_')) {
        $experienceStatus = $mysqli->query('SELECT discussed FROM experience_knowledges WHERE experience_knowledge_id = ' . $id)->fetch_row()[0];
        $discussionStatus = $mysqli->query('SELECT discussed FROM externalized_contents WHERE externalized_contents_id = ' . $id)->fetch_row()[0];
        $ok = $ok && $experienceStatus === ($case === 'status_experience' ? 'UNDERWAY' : 'YET')
            && $discussionStatus === ($case === 'status_discussion' ? 'UNDERWAY' : 'YET');
    }
    echo ($ok ? 'PASS' : 'FAIL') . ': mixed ' . $case . " endpoint\n";
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    session_destroy();
    if (!$ok) { echo json_encode($result, JSON_UNESCAPED_UNICODE), "\n"; exit(1); }
});
require __DIR__ . '/../php/' . $endpoints[$case];
