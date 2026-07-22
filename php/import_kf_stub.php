<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/connect_db.php';

function import_kf_respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function import_kf_table_exists(mysqli $mysqli, string $table): bool
{
    $escaped = $mysqli->real_escape_string($table);
    if ($res = $mysqli->query("SHOW TABLES LIKE '{$escaped}'")) {
        $exists = ($res->num_rows > 0);
        $res->free();
        return $exists;
    }
    return false;
}

function import_kf_column_exists(mysqli $mysqli, string $table, string $column): bool
{
    $tableEscaped = $mysqli->real_escape_string($table);
    $columnEscaped = $mysqli->real_escape_string($column);
    if ($res = $mysqli->query("SHOW COLUMNS FROM `{$tableEscaped}` LIKE '{$columnEscaped}'")) {
        $exists = ($res->num_rows > 0);
        $res->free();
        return $exists;
    }
    return false;
}

function import_kf_ensure_column(mysqli $mysqli, string $table, string $column, string $definition, ?string $after = null): void
{
    if (import_kf_column_exists($mysqli, $table, $column)) {
        return;
    }
    $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
    if ($after !== null && $after !== '') {
        $sql .= " AFTER `{$after}`";
    }
    if (!$mysqli->query($sql)) {
        import_kf_respond(500, [
            'success' => false,
            'message' => 'Schema migration failed',
            'detail' => $mysqli->error,
        ]);
    }
}

function import_kf_ensure_index(mysqli $mysqli, string $table, string $indexName, string $definition): void
{
    $tableEscaped = $mysqli->real_escape_string($table);
    $indexEscaped = $mysqli->real_escape_string($indexName);
    if ($res = $mysqli->query("SHOW INDEX FROM `{$tableEscaped}` WHERE Key_name = '{$indexEscaped}'")) {
        $exists = ($res->num_rows > 0);
        $res->free();
        if ($exists) {
            return;
        }
    }
    if (!$mysqli->query("ALTER TABLE `{$table}` ADD {$definition}")) {
        import_kf_respond(500, [
            'success' => false,
            'message' => 'Schema migration failed',
            'detail' => $mysqli->error,
        ]);
    }
}

function import_kf_ensure_schema(mysqli $mysqli): void
{
    if (!import_kf_table_exists($mysqli, 'externalized_contents')) {
        import_kf_respond(500, [
            'success' => false,
            'message' => 'Required table not found',
            'detail' => 'externalized_contents',
        ]);
    }

    import_kf_ensure_column($mysqli, 'externalized_contents', 'group_id', 'INT(11) NOT NULL DEFAULT 0', 'user_id');
    import_kf_ensure_column($mysqli, 'externalized_contents', 'source_system', 'VARCHAR(191) DEFAULT NULL', 'group_id');
    import_kf_ensure_column($mysqli, 'externalized_contents', 'source_type', 'VARCHAR(32) DEFAULT NULL', 'source_system');
    import_kf_ensure_column($mysqli, 'externalized_contents', 'source_id', 'VARCHAR(191) DEFAULT NULL', 'source_type');
    import_kf_ensure_column($mysqli, 'externalized_contents', 'source_user_ref', 'VARCHAR(191) DEFAULT NULL', 'source_id');
    import_kf_ensure_column($mysqli, 'externalized_contents', 'raw_payload', 'LONGTEXT DEFAULT NULL', 'source_user_ref');

    import_kf_ensure_index($mysqli, 'externalized_contents', 'ux_externalized_source_fragment', 'UNIQUE KEY `ux_externalized_source_fragment` (`source_system`,`source_type`,`source_id`)');
    import_kf_ensure_index($mysqli, 'externalized_contents', 'idx_externalized_group_id', 'KEY `idx_externalized_group_id` (`group_id`)');
}

function import_kf_get_or_create_system_user_id(mysqli $mysqli): int
{
    $sub = 'ok-core-import-bot';
    if ($stmt = $mysqli->prepare('SELECT user_id FROM users WHERE sso_sub = ? LIMIT 1')) {
        $stmt->bind_param('s', $sub);
        if ($stmt->execute()) {
            $stmt->bind_result($userId);
            if ($stmt->fetch()) {
                $stmt->close();
                return (int)$userId;
            }
        }
        $stmt->close();
    }

    $name = 'KF Import Bot';
    $password = 'SSO_LOGIN_DISABLED';
    $displayName = 'KF Import Bot';
    $role = 'system';
    $claimsJson = json_encode([
        'sub' => $sub,
        'name' => $name,
        'source' => 'ok-core-import-api',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $userId = 0;
    for ($i = 0; $i < 30; $i++) {
        $candidate = function_exists('random_int') ? random_int(10000, 2147483647) : mt_rand(10000, 2147483647);
        if ($check = $mysqli->prepare('SELECT user_id FROM users WHERE user_id = ? LIMIT 1')) {
            $check->bind_param('i', $candidate);
            if ($check->execute()) {
                $check->store_result();
                if ($check->num_rows === 0) {
                    $userId = $candidate;
                    $check->close();
                    break;
                }
            }
            $check->close();
        }
    }
    if ($userId <= 0) {
        import_kf_respond(500, [
            'success' => false,
            'message' => 'Failed to generate system user_id',
        ]);
    }

    if ($stmt = $mysqli->prepare(
        'INSERT INTO users (user_id, sso_sub, name, password, sso_user_id, sso_username, email, display_name, role, is_active, sso_claims_json, sso_updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())'
    )) {
        $ssoUserId = $sub;
        $ssoUsername = $sub;
        $email = 'import-bot@localhost';
        $stmt->bind_param('isssssssss', $userId, $sub, $name, $password, $ssoUserId, $ssoUsername, $email, $displayName, $role, $claimsJson);
        if (!$stmt->execute()) {
            $stmt->close();
            import_kf_respond(500, [
                'success' => false,
                'message' => 'Failed to create system user',
                'detail' => $stmt ? $stmt->error : $mysqli->error,
            ]);
        }
        $stmt->close();
        return $userId;
    }

    import_kf_respond(500, [
        'success' => false,
        'message' => 'Failed to prepare system user creation',
        'detail' => $mysqli->error,
    ]);
}

function import_kf_get_or_create_external_user_id(mysqli $mysqli, string $sourceSystem, string $sourceUserRef, string $sourceUserName): int
{
    $sourceUserName = trim($sourceUserName);
    if ($sourceUserName === '') {
        return import_kf_get_or_create_system_user_id($mysqli);
    }

    $safeSystem = $sourceSystem !== '' ? $sourceSystem : 'external';
    $safeRef = $sourceUserRef !== '' ? $sourceUserRef : hash('sha256', $sourceUserName);
    $sub = 'external:' . $safeSystem . ':' . $safeRef;

    if ($stmt = $mysqli->prepare('SELECT user_id FROM users WHERE sso_sub = ? LIMIT 1')) {
        $stmt->bind_param('s', $sub);
        if ($stmt->execute()) {
            $stmt->bind_result($userId);
            if ($stmt->fetch()) {
                $stmt->close();
                if ($update = $mysqli->prepare('UPDATE users SET name = ?, display_name = ?, sso_username = ?, sso_updated_at = NOW() WHERE user_id = ?')) {
                    $update->bind_param('sssi', $sourceUserName, $sourceUserName, $sourceUserName, $userId);
                    $update->execute();
                    $update->close();
                }
                return (int)$userId;
            }
        }
        $stmt->close();
    }

    $password = 'SSO_LOGIN_DISABLED';
    $role = 'external';
    $claimsJson = json_encode([
        'sub' => $sub,
        'name' => $sourceUserName,
        'source' => 'discussion-kf-import',
        'source_system' => $safeSystem,
        'source_user_ref' => $safeRef,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $userId = 0;
    for ($i = 0; $i < 30; $i++) {
        $candidate = function_exists('random_int') ? random_int(10000, 2147483647) : mt_rand(10000, 2147483647);
        if ($check = $mysqli->prepare('SELECT user_id FROM users WHERE user_id = ? LIMIT 1')) {
            $check->bind_param('i', $candidate);
            if ($check->execute()) {
                $check->store_result();
                if ($check->num_rows === 0) {
                    $userId = $candidate;
                    $check->close();
                    break;
                }
            }
            $check->close();
        }
    }

    if ($userId <= 0) {
        import_kf_respond(500, [
            'success' => false,
            'message' => 'Failed to generate external user_id',
        ]);
    }

    if ($stmt = $mysqli->prepare(
        'INSERT INTO users (user_id, sso_sub, name, password, sso_user_id, sso_username, email, display_name, role, is_active, sso_claims_json, sso_updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())'
    )) {
        $email = 'external-kf-import@localhost';
        $stmt->bind_param('isssssssss', $userId, $sub, $sourceUserName, $password, $safeRef, $sourceUserName, $email, $sourceUserName, $role, $claimsJson);
        if (!$stmt->execute()) {
            $stmt->close();
            import_kf_respond(500, [
                'success' => false,
                'message' => 'Failed to create external user',
                'detail' => $stmt ? $stmt->error : $mysqli->error,
            ]);
        }
        $stmt->close();
        return $userId;
    }

    import_kf_respond(500, [
        'success' => false,
        'message' => 'Failed to prepare external user creation',
        'detail' => $mysqli->error,
    ]);
}

function import_kf_base_url(): string
{
    $envBaseUrl = getenv('HCIMLAB_SSO_BASE_URL');
    if ($envBaseUrl !== false && trim((string)$envBaseUrl) !== '') {
        return rtrim((string)$envBaseUrl, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== ''
        ? (string)$_SERVER['HTTP_HOST']
        : 'localhost:8888';
    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '/OK-Core/php/import_kf_stub.php';
    $basePath = rtrim(dirname(dirname($script)), '/\\');

    if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
        $basePath = '';
    }

    return rtrim($scheme . '://' . $host . $basePath, '/');
}

function import_kf_ok_core_url(int $externalizedContentsId): string
{
    return import_kf_base_url() . '/index.php?externalized_contents_id=' . rawurlencode((string)$externalizedContentsId);
}

function import_kf_read_payload(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        import_kf_respond(400, [
            'success' => false,
            'message' => 'Invalid JSON',
            'raw' => $raw === false ? '' : $raw,
        ]);
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
        import_kf_respond(400, [
            'success' => false,
            'message' => 'Invalid JSON',
            'raw' => $raw,
        ]);
    }

    return [$payload, $raw];
}

function import_kf_trimmed_string($value): string
{
    return trim((string)$value);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    import_kf_respond(200, [
        'success' => true,
        'message' => 'OK-Core KF import endpoint is running. Send POST JSON to this endpoint.',
    ]);
}

if ($method !== 'POST') {
    import_kf_respond(405, [
        'success' => false,
        'message' => 'Method Not Allowed',
    ]);
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    import_kf_respond(500, [
        'success' => false,
        'message' => 'Database connection failed',
    ]);
}

@$mysqli->set_charset('utf8mb4');
import_kf_ensure_schema($mysqli);

[$payload, $rawJson] = import_kf_read_payload();

$sourceSystem = import_kf_trimmed_string($payload['source_system'] ?? '');
$sourceType = strtolower(import_kf_trimmed_string($payload['source_type'] ?? ''));
$sourceId = import_kf_trimmed_string($payload['source_id'] ?? '');
$sourceUserRef = import_kf_trimmed_string($payload['source_user_ref'] ?? '');
$sourceUserName = import_kf_trimmed_string($payload['source_user_name'] ?? '');
$groupRaw = import_kf_trimmed_string($payload['group_id'] ?? '');
$selectedContents = (string)($payload['selected_contents'] ?? '');
$knowledgeFragmentContent = import_kf_trimmed_string($payload['knowledge_fragment_content'] ?? '');
$stage1 = (string)($payload['stage1'] ?? '');
$stage2 = (string)($payload['stage2'] ?? '');
$stage3 = (string)($payload['stage3'] ?? '');

if ($sourceType === 'externalized') {
    $sourceType = 'discussion';
}

if ($sourceType === 'srl') {
    $sourceType = 'SRL';
}

if ($sourceType !== 'discussion' && $sourceType !== 'experience' && $sourceType !== 'SRL') {
    import_kf_respond(400, [
        'success' => false,
        'message' => 'Invalid source_type',
    ]);
}

if ($sourceSystem === '' || $sourceId === '' || $knowledgeFragmentContent === '') {
    import_kf_respond(400, [
        'success' => false,
        'message' => 'Missing required fields',
        'required' => [
            'source_system',
            'source_type',
            'source_id',
            'knowledge_fragment_content',
        ],
    ]);
}

$groupId = 0;
if ($groupRaw !== '') {
    if (!ctype_digit($groupRaw)) {
        import_kf_respond(400, [
            'success' => false,
            'message' => 'Invalid group_id',
        ]);
    }
    $groupId = (int)$groupRaw;
}

$sourceUserRefValue = ($sourceUserRef === '') ? null : $sourceUserRef;
$rawPayloadValue = $rawJson;

$importUserId = import_kf_get_or_create_external_user_id($mysqli, $sourceSystem, $sourceUserRef, $sourceUserName);

$checkSql = 'SELECT externalized_contents_id
               FROM externalized_contents
              WHERE source_system = ?
                AND source_type = ?
                AND source_id = ?
              LIMIT 1';
if (!($checkStmt = $mysqli->prepare($checkSql))) {
    import_kf_respond(500, [
        'success' => false,
        'message' => 'Failed to prepare duplicate check',
        'detail' => $mysqli->error,
    ]);
}

$checkStmt->bind_param('sss', $sourceSystem, $sourceType, $sourceId);
if (!$checkStmt->execute()) {
    $checkStmt->close();
    import_kf_respond(500, [
        'success' => false,
        'message' => 'Duplicate check failed',
        'detail' => $checkStmt ? $checkStmt->error : $mysqli->error,
    ]);
}

$checkStmt->bind_result($existingId);
if ($checkStmt->fetch()) {
    $checkStmt->close();
    if ($updateStmt = $mysqli->prepare('UPDATE externalized_contents SET user_id = ?, source_user_ref = ?, raw_payload = ?, updated_at = NOW() WHERE externalized_contents_id = ?')) {
        $updateStmt->bind_param('issi', $importUserId, $sourceUserRefValue, $rawPayloadValue, $existingId);
        $updateStmt->execute();
        $updateStmt->close();
    }
    import_kf_respond(200, [
        'success' => true,
        'message' => 'OK-Core received KF successfully',
        'externalizedContentsId' => (int)$existingId,
        'okCoreUrl' => import_kf_ok_core_url((int)$existingId),
        'duplicate' => true,
        'receivedPayload' => $payload,
    ]);
}
$checkStmt->close();

$externalizedType = ($sourceType === 'SRL') ? 'SRL' : 'discussion';
$thoughtExperienceNodeId = $sourceId;

$insertSql = 'INSERT INTO externalized_contents
    (user_id, group_id, remarked_utterance_id, thought_experience_node_id, externalized_type,
     used_remarked_utterance, selected_contents, knowledge_fragment_content, stage1, stage2, stage3,
     source_system, source_type, source_id, source_user_ref, raw_payload, discussed)
    VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

$stmt = $mysqli->prepare($insertSql);
if (!$stmt) {
    import_kf_respond(500, [
        'success' => false,
        'message' => 'Failed to prepare insert',
        'detail' => $mysqli->error,
    ]);
}

$remarkedUtteranceId = null;
$discussed = 'DONE';
$stmt->bind_param(
    'iissssssssssssss',
    $importUserId,
    $groupId,
    $remarkedUtteranceId,
    $thoughtExperienceNodeId,
    $externalizedType,
    $selectedContents,
    $knowledgeFragmentContent,
    $stage1,
    $stage2,
    $stage3,
    $sourceSystem,
    $sourceType,
    $sourceId,
    $sourceUserRefValue,
    $rawPayloadValue,
    $discussed
);

if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    import_kf_respond(500, [
        'success' => false,
        'message' => 'Save failed',
        'detail' => $error,
    ]);
}

$externalizedContentsId = (int)$mysqli->insert_id;
$stmt->close();

import_kf_respond(200, [
    'success' => true,
    'message' => 'OK-Core received KF successfully',
    'externalizedContentsId' => $externalizedContentsId,
    'okCoreUrl' => import_kf_ok_core_url($externalizedContentsId),
    'receivedPayload' => $payload,
]);
