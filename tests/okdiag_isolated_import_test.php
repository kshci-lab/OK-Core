<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Requires an explicitly supplied, socket-only isolated MySQL instance.
// This test creates a database and must never be pointed at an existing server.
$socket = getenv('OKDIAG_ISOLATED_MYSQL_SOCKET') ?: '';
if (!str_starts_with($socket, '/tmp/okdiag-isolated-mysql-') || !str_ends_with($socket, '/mysql.sock')) {
    throw new RuntimeException('Isolated test socket is required');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', '', 0, $socket);
$db->set_charset('utf8mb4');
require_once __DIR__ . '/../php/api/okdiag_kf_import_service.php';
require_once __DIR__ . '/../php/discovery_knowledge_read.php';

if (($argv[1] ?? '') === 'request') {
    $db->select_db((string)$argv[3]);
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_AUTHORIZATION'] = ($argv[2] ?? '') === 'auth' ? 'Bearer isolated-test-token' : 'Bearer incorrect';
    putenv('OKDIAG_API_TOKEN=isolated-test-token');
    putenv('OKDIAG_ALLOWED_GROUP_IDS=100');
    okdiag_require_https();
    okdiag_require_authentication();
    $payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    try {
        $result = okdiag_import($db, $payload, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        echo json_encode(['success' => true] + $result);
    } catch (Throwable $e) {
        echo json_encode(['exception' => get_class($e)]);
    }
    exit;
}

function ensure(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$database = 'okdiag_test_' . bin2hex(random_bytes(6));
$db->query('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4');
$db->select_db($database);
foreach ([
    'CREATE TABLE users (user_id INT PRIMARY KEY, sso_sub VARCHAR(191) UNIQUE, sso_user_id VARCHAR(191), sso_username VARCHAR(191), name VARCHAR(45), display_name VARCHAR(191), email VARCHAR(191), password VARCHAR(999), role VARCHAR(45), is_active INT, sso_claims_json TEXT, sso_updated_at DATETIME, login_time DATETIME)',
    'CREATE TABLE knowledge_groups (group_id INT PRIMARY KEY, deleted INT)',
    'CREATE TABLE kgroup_user_link (user_id INT, group_id INT, deleted INT)',
    'CREATE TABLE externalized_contents (externalized_contents_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, group_id INT, remarked_utterance_id INT NULL, thought_experience_node_id VARCHAR(191), externalized_type VARCHAR(30), used_remarked_utterance INT, selected_contents TEXT, knowledge_fragment_content VARCHAR(255) NOT NULL, stage1 TEXT, stage2 TEXT, stage3 TEXT, source_system VARCHAR(191), source_type VARCHAR(32), source_id VARCHAR(191), source_user_ref VARCHAR(191), raw_payload LONGTEXT, discussed VARCHAR(30), deleted INT DEFAULT 0, UNIQUE KEY source_identity (source_system, source_type, source_id))',
    'CREATE TABLE knowledge_explorer (knowledge_node_id INT, knowledge_group_id INT, deleted INT)',
    'CREATE TABLE knowledge_explorer_fragment_links (knowledge_node_id INT, fragment_source_type VARCHAR(32), fragment_source_id INT)',
    'CREATE TABLE experience_knowledges (experience_knowledge_id INT, knowledge_fragment_content TEXT, selected_contents TEXT, stage1 TEXT, stage2 TEXT, stage3 TEXT, deleted INT)',
    'INSERT INTO knowledge_groups VALUES (100,0),(200,0)',
] as $sql) $db->query($sql);

require_once __DIR__ . '/../php/hcimlab_sso.php';
$serviceUserId = hcimlab_sso_generate_local_user_id($db);
$insert = "INSERT INTO users (user_id, name, display_name, role, password, is_active)
    VALUES (?, 'OKDiag（AI生成）', 'OKDiag（AI生成）', 'system', 'SSO_LOGIN_DISABLED', 1)";
$userInsert = $db->prepare($insert);
$userInsert->bind_param('i', $serviceUserId);
$userInsert->execute();
$userInsert->close();

foreach (['human-fixture', 'okdiag-ai', (string)$serviceUserId] as $subject) {
    $claims = ['sub' => $subject, 'username' => 'okdiag-ai', 'name' => 'OKDiag（AI生成）'];
    $firstLogin = hcimlab_sso_upsert_user($db, $claims);
    $repeatLogin = hcimlab_sso_upsert_user($db, $claims);
    ensure($firstLogin['user_id'] !== $serviceUserId && $firstLogin === $repeatLogin, 'SSO identity separation');
}
ensure(okdiag_resolve_system_user($db) === $serviceUserId, 'AI API resolves the dedicated row');
$db->begin_transaction();
$secondId = hcimlab_sso_generate_local_user_id($db);
$duplicateInsert = $db->prepare($insert);
$duplicateInsert->bind_param('i', $secondId);
$duplicateInsert->execute();
$ambiguous = false;
try { okdiag_resolve_system_user($db); } catch (RuntimeException $e) { $ambiguous = true; }
ensure($ambiguous, 'ambiguous service identity rejected');
$db->rollback();
$groupInsert = $db->prepare('INSERT INTO kgroup_user_link VALUES (?, 100, 0)');
$groupInsert->bind_param('i', $serviceUserId);
$groupInsert->execute();
$groupInsert->close();

$migration = file_get_contents(__DIR__ . '/../sql/2026-11-19_add_okdiag_ai_user.sql');
ensure(is_string($migration) && $migration !== '', 'Migration file is readable');
if (!$db->multi_query($migration)) throw new RuntimeException('Migration failed: ' . $db->error);
do { if ($result = $db->store_result()) $result->free(); } while ($db->more_results() && $db->next_result());
ensure((int)($db->query("SELECT COUNT(*) AS n FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'externalized_contents' AND column_name = 'title'")->fetch_assoc()['n'] ?? 0) === 1, 'title migration');
ensure(($db->query("SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'externalized_contents' AND column_name = 'knowledge_fragment_content'")->fetch_assoc()['DATA_TYPE'] ?? '') === 'longtext', 'longtext migration');

$payload = [
    'source_system' => 'OKDiag', 'source_type' => 'discussion', 'source_id' => str_repeat('a',24),
    'source_user_ref' => 'okdiag-ai', 'source_user_name' => 'OKDiag（AI生成）', 'group_id' => 100,
    'title' => 'テスト知識', 'selected_contents' => '[t1] 発言', 'knowledge_fragment_content' => str_repeat('長文KF根拠。', 100),
    'okdiag_provenance' => ['generation_id' => str_repeat('b',64), 'discussion_id' => 'test-discussion',
        'evidence_turn_ids' => ['t1'], 'knowledge_trace' => ['relations' => [['ref' => 'node:7'], ['ref' => 'experience:9']]]],
];
$request = static function (array $value, string $auth = 'auth') use ($database): array {
    $proc = proc_open([PHP_BINARY, __FILE__, 'request', $auth, $database], [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']], $pipes);
    if (!is_resource($proc)) throw new RuntimeException('Cannot launch isolated request');
    fwrite($pipes[0], json_encode($value, JSON_UNESCAPED_UNICODE)); fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($proc) !== 0 || $error !== '') throw new RuntimeException('Isolated request failed');
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
};

ensure(($request($payload, 'wrong')['message'] ?? '') === 'Authentication required', 'authentication');
$badGroup = $payload; $badGroup['group_id'] = 200;
ensure(($request($badGroup)['message'] ?? '') === 'Group is not allowed', 'group allowlist');
$db->query('DELETE FROM kgroup_user_link WHERE user_id=' . $serviceUserId . ' AND group_id=100');
ensure(($request($payload)['message'] ?? '') === 'Group access denied', 'membership');
$db->query('INSERT INTO kgroup_user_link VALUES (' . $serviceUserId . ',100,0)');
$missing = $payload; unset($missing['knowledge_fragment_content']);
ensure(str_contains($request($missing)['message'] ?? '', 'Missing required field'), 'required field');
$db->query('UPDATE users SET is_active=0 WHERE user_id=' . $serviceUserId);
ensure(($request($payload)['exception'] ?? '') === 'RuntimeException', 'inactive service account');
$db->query('UPDATE users SET is_active=1 WHERE user_id=' . $serviceUserId);
$tooLong = $payload; $tooLong['title'] = str_repeat('知', 256);
ensure(($request($tooLong)['message'] ?? '') === 'Field length exceeds the database limit', 'unicode title length');
$first = $request($payload);
ensure(($first['success'] ?? false) && ($first['duplicate'] ?? true) === false, 'first insert');
$id = $first['externalizedContentsId'];
$duplicate = $request($payload);
ensure(($duplicate['duplicate'] ?? false) && $duplicate['externalizedContentsId'] === $id, 'idempotent retry');
$conflict = $payload; $conflict['knowledge_fragment_content'] = 'changed';
ensure(($request($conflict)['message'] ?? '') === 'Conflicting source identifier', 'conflict');
$statement = $db->prepare(ok_discovery_queries()['discussion']);
$group = 100; $statement->bind_param('i', $group); $statement->execute();
$row = $statement->get_result()->fetch_assoc();
$saved = json_decode($row['raw_payload'], true, 512, JSON_THROW_ON_ERROR);
$public = ok_discovery_public_discussion($row);
ensure((int)$row['source_id'] === $id && $row['content'] === $payload['knowledge_fragment_content']
    && $saved['source_user_name'] === 'OKDiag（AI生成）'
    && $public['source_system'] === 'OKDiag' && $public['source_user_name'] === 'OKDiag（AI生成）'
    && $public['okdiag_provenance'] === $payload['okdiag_provenance']
    && !isset($public['raw_payload']), 'readback and provenance');
ensure((int)$db->query('SELECT COUNT(*) FROM externalized_contents')->fetch_row()[0] === 1, 'no duplicate rows');
echo "PASS: isolated MySQL authentication, groups, validation, insert, replay, conflict and readback\n";
