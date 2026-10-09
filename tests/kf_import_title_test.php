<?php
require __DIR__ . '/../php/connect_db.php';
require __DIR__ . '/../php/kf_import_title.php';

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    throw new RuntimeException('Local ok_core database is unavailable');
}

function verify($condition, $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

// A connection-local table with the real schema. It disappears on disconnect.
verify($mysqli->query('CREATE TEMPORARY TABLE kf_title_test_contents LIKE externalized_contents') !== false, 'Create temporary table');
verify($mysqli->query("INSERT INTO kf_title_test_contents SELECT * FROM externalized_contents WHERE source_system = 'discussion-kf-system' ORDER BY externalized_contents_id LIMIT 1") !== false, 'Copy an existing imported KF');
$row = $mysqli->query('SELECT * FROM kf_title_test_contents LIMIT 1')->fetch_assoc();
verify($row !== null, 'Imported KF fixture exists');
$id = (int)$row['externalized_contents_id'];
$legacyPayload = json_decode((string)$row['raw_payload'], true);
verify(is_array($legacyPayload), 'Stored source payload is JSON');
unset($legacyPayload['title']);
$legacyJson = json_encode($legacyPayload, JSON_UNESCAPED_UNICODE);
$reset = $mysqli->prepare('UPDATE kf_title_test_contents SET raw_payload = ? WHERE externalized_contents_id = ?');
$reset->bind_param('si', $legacyJson, $id);
verify($reset->execute(), 'Prepare legacy payload');
$reset->close();
$before = $mysqli->query('SELECT * FROM kf_title_test_contents LIMIT 1')->fetch_assoc();

$title = '再送された知のタイトル';
verify(import_kf_update_duplicate_title($mysqli, $id, ['title' => $title, 'knowledge_fragment_content' => '変更してはいけない本文'], 'kf_title_test_contents'), 'Update title');
$after = $mysqli->query('SELECT * FROM kf_title_test_contents LIMIT 1')->fetch_assoc();
verify(json_decode($after['raw_payload'], true)['title'] === $title, 'Title is saved in raw_payload');
$oldPayload = json_decode($before['raw_payload'], true);
$newPayload = json_decode($after['raw_payload'], true);
unset($newPayload['title']);
verify($oldPayload == $newPayload, 'Other source payload fields are retained');
foreach (['externalized_contents_id', 'selected_contents', 'knowledge_fragment_content', 'stage1', 'stage2', 'stage3', 'group_id', 'user_id', 'source_user_ref', 'discussed', 'deleted'] as $field) {
    verify($before[$field] === $after[$field], $field . ' is retained');
}

$title = '';
verify(import_kf_update_duplicate_title($mysqli, $id, ['title' => $title], 'kf_title_test_contents'), 'Update to an empty title');
$empty = $mysqli->query('SELECT raw_payload FROM kf_title_test_contents LIMIT 1')->fetch_assoc();
verify(array_key_exists('title', json_decode($empty['raw_payload'], true)), 'Empty title key remains present');
verify(json_decode($empty['raw_payload'], true)['title'] === '', 'Empty title remains empty');
verify(!import_kf_update_duplicate_title($mysqli, $id, ['knowledge_fragment_content' => '無関係'], 'kf_title_test_contents'), 'Missing key is a no-op');
$unchanged = $mysqli->query('SELECT raw_payload FROM kf_title_test_contents LIMIT 1')->fetch_assoc();
verify($unchanged['raw_payload'] === $empty['raw_payload'], 'Missing key preserves existing title');
$mysqli->close();
echo "PASS: duplicate title update, empty title, original payload and KF fields retained\n";
