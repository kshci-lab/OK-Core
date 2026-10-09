<?php
require __DIR__ . '/../php/connect_db.php';
require __DIR__ . '/../php/organizational_knowledge_read.php';

function ensure_ok($condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
if (!isset($mysqli) || !($mysqli instanceof mysqli)) { throw new RuntimeException('Local ok_core database is unavailable'); }

// Temporary tables shadow the production table names only for this connection.
foreach (['knowledge_groups', 'kgroup_user_link', 'knowledge_explorer', 'knowledge_explorer_fragment_links'] as $table) {
    ensure_ok($mysqli->query("CREATE TEMPORARY TABLE `{$table}` AS SELECT * FROM `{$table}` WHERE 1 = 0") !== false, 'Create temporary ' . $table);
}
ensure_ok($mysqli->query("ALTER TABLE knowledge_groups ADD PRIMARY KEY (group_id)") !== false, 'Index groups');
ensure_ok($mysqli->query("ALTER TABLE knowledge_explorer ADD PRIMARY KEY (knowledge_node_id)") !== false, 'Index nodes');
ensure_ok($mysqli->query("INSERT INTO knowledge_groups (group_id, name, creater, created_at, deleted) VALUES (7001, 'API test', 70001, NOW(), 0), (7002, 'deleted', 70001, NOW(), 1)") !== false, 'Seed groups');
ensure_ok($mysqli->query("INSERT INTO kgroup_user_link (id, group_id, user_id, role, created_at, deleted) VALUES (1, 7001, 70001, 'others', NOW(), 0), (2, 7002, 70001, 'others', NOW(), 0)") !== false, 'Seed membership');
ensure_ok(ok_read_group_access($mysqli, 70001, 7001), 'Active member is authorized');
ensure_ok(!ok_read_group_access($mysqli, 70001, 7002), 'Deleted group is denied');
ensure_ok(!ok_read_group_access($mysqli, 70002, 7001), 'Non-member is denied');

$fragmentRow = $mysqli->query('SELECT externalized_contents_id FROM externalized_contents WHERE deleted = 0 ORDER BY externalized_contents_id LIMIT 1')->fetch_assoc();
ensure_ok($fragmentRow !== null, 'Discussion fragment fixture exists');
$fragmentId = (int)$fragmentRow['externalized_contents_id'];
$nodeSql = "INSERT INTO knowledge_explorer (knowledge_node_id, parent_node_id, node_title, knowledge_fragment_id, comment, tacto_when, tacto_what, tacto_why, organizational_basis, node_type, created_at, updated_at, updated_by, deleted, sort_order, knowledge_group_id) VALUES
 (100, NULL, '親', NULL, '本文', 'いつ', '何を', 'なぜ', '根拠', '分類', NOW(), NOW(), NULL, 0, 2, 7001),
 (101, 100, '子', NULL, NULL, NULL, NULL, NULL, NULL, '分類子', NOW(), NOW(), NULL, 0, 1, 7001),
 (102, 999, '親が除外された子', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL, 0, NULL, 7001),
 (103, NULL, '削除済み', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL, 1, NULL, 7001),
 (104, NULL, '別グループ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL, 0, NULL, 7002)";
ensure_ok($mysqli->query($nodeSql) !== false, 'Seed nodes');
ensure_ok($mysqli->query("INSERT INTO knowledge_explorer_fragment_links (id, knowledge_node_id, fragment_source_type, fragment_source_id, display_order, created_at) VALUES (1, 101, 'discussion', {$fragmentId}, 1, NOW()), (2, 101, 'discussion', 999999, 2, NOW()), (3, 101, 'SRL', 1, 3, NOW())") !== false, 'Seed links');

$tree = ok_read_organizational_knowledge($mysqli, 7001);
ensure_ok(count($tree) === 2, 'Return roots and orphaned descendants only');
$parent = $tree[0]; ensure_ok($parent['title'] === '親' && $parent['content'] === '本文', 'Return node text');
ensure_ok($parent['when'] === 'いつ' && $parent['what'] === '何を' && $parent['why'] === 'なぜ' && $parent['category'] === '分類', 'Return structured fields');
ensure_ok(count($parent['children']) === 1 && $parent['children'][0]['title'] === '子', 'Keep child hierarchy');
$child = $parent['children'][0];
if (!(count($child['fragments']) === 1 && $child['fragments'][0]['source_type'] === 'discussion' && (int)$child['fragments'][0]['source_id'] === $fragmentId)) {
    throw new RuntimeException('Return existing fragment: ' . json_encode($child['fragments'], JSON_UNESCAPED_UNICODE));
}
ensure_ok(count($child['unresolved_fragment_links']) === 2, 'Keep unresolved links separately');
ensure_ok($child['unresolved_fragment_links'][0]['reason'] === 'source_not_found_or_deleted', 'Mark missing source');
ensure_ok($child['unresolved_fragment_links'][1]['reason'] === 'source_not_supported', 'Mark unsupported source');
$json = json_encode(['status' => 'ok', 'group_id' => 7001, 'nodes' => $tree], JSON_UNESCAPED_UNICODE);
ensure_ok(strpos($json, '削除済み') === false && strpos($json, '別グループ') === false, 'Exclude deleted and other-group nodes');
$mysqli->close();
echo "PASS: access, hierarchy, fields, fragments, unresolved links, deleted and group filtering\n";
