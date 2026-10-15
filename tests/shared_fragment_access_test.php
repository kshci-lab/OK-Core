<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../php/connect_db.php';
require_once __DIR__ . '/../php/knowledge_group_access.php';
require_once __DIR__ . '/../php/shared_fragment_storage.php';

function assert_shared(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

if (!$mysqli) throw new RuntimeException('Local DB unavailable.');
$collision = $mysqli->query('SELECT ek.experience_knowledge_id AS id
    FROM experience_knowledges ek JOIN externalized_contents ec
      ON ec.externalized_contents_id = ek.experience_knowledge_id
    WHERE ek.deleted = 0 AND ec.deleted = 0 LIMIT 1')->fetch_assoc();
$groups = $mysqli->query('SELECT group_id FROM knowledge_groups WHERE deleted = 0 ORDER BY group_id LIMIT 2')->fetch_all(MYSQLI_ASSOC);
assert_shared($collision !== null && count($groups) === 2, 'Requires two groups and an ID present in both KF tables.');
$id = (int)$collision['id'];
$first = (int)$groups[0]['group_id'];
$second = (int)$groups[1]['group_id'];
$ddl = $mysqli->query('SHOW CREATE TABLE shared_nodes')->fetch_row()[1];
// The temporary table shadows shared_nodes on this connection only.
$mysqli->query(preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $ddl));
try {
    $stmt = $mysqli->prepare("INSERT INTO shared_nodes
        (fragment_source_type, knowledge_fragment_id, knowledge_group_id) VALUES ('experience', ?, ?)");
    $stmt->bind_param('ii', $id, $first);
    $stmt->execute();
    $stmt->close();
    ok_core_register_discussion_share($mysqli, $id, $second);
    assert_shared(ok_core_group_has_fragments($mysqli, (string)$first, 'experience', [$id]), 'Experience share must be visible.');
    assert_shared(!ok_core_group_has_fragments($mysqli, (string)$first, 'discussion', [$id]), 'Same numeric ID must not grant discussion access.');
    assert_shared(ok_core_group_has_fragments($mysqli, (string)$second, 'discussion', [$id]), 'Discussion share must be visible.');
    assert_shared(!ok_core_group_has_fragments($mysqli, (string)$second, 'experience', [$id]), 'Discussion share must not grant experience access.');
    ok_core_register_discussion_share($mysqli, $id, $first);
    ok_core_register_discussion_share($mysqli, $id, $first);
    ok_core_register_discussion_share($mysqli, $id, 0);
    assert_shared((int)$mysqli->query('SELECT COUNT(*) FROM shared_nodes')->fetch_row()[0] === 3, 'Reimport must not duplicate shares; missing group must preserve shares.');
    $stmt = $mysqli->prepare("UPDATE shared_nodes SET deleted = 1
        WHERE fragment_source_type = 'experience' AND knowledge_fragment_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    assert_shared(ok_core_group_has_fragments($mysqli, (string)$first, 'discussion', [$id]), 'Experience revocation must preserve discussion sharing.');
    $mysqli->query("UPDATE shared_nodes SET deleted = 1 WHERE fragment_source_type = 'discussion'");
    assert_shared(!ok_core_group_has_fragments($mysqli, (string)$second, 'discussion', [$id]), 'Deleted share must be hidden, even if legacy group_id matches.');
    ok_core_register_discussion_share($mysqli, $id, $second);
    assert_shared(ok_core_group_has_fragments($mysqli, (string)$second, 'discussion', [$id]), 'Reimport must reactivate the specified share.');
    echo "PASS: typed IDs, group isolation, multiple groups, duplicate reimport, scoped deletion and share reactivation.\n";
} finally {
    $mysqli->query('DROP TEMPORARY TABLE shared_nodes');
    $mysqli->close();
}
