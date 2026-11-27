<?php

declare(strict_types=1);

/** Shared, authenticated OKDiag KF import service. */

function okdiag_private_config(): array
{
    static $config;
    if (is_array($config)) return $config;
    $path = trim((string)getenv('OKDIAG_CONFIG_FILE'));
    if ($path === '') {
        $home = trim((string)getenv('HOME'));
        if ($home === '') {
            $user = trim((string)getenv('USER')) ?: trim((string)get_current_user());
            $home = $user !== '' ? '/Users/' . $user : '';
        }
        $path = $home !== '' ? $home . '/Library/Application Support/OK-Core/local-https/okdiag-api.json' : '';
    }
    $config = [];
    if ($path !== '' && is_file($path) && is_readable($path)) {
        $decoded = json_decode((string)file_get_contents($path), true);
        if (is_array($decoded)) $config = $decoded;
    }
    return $config;
}

function okdiag_json_response(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function okdiag_trusted_local_proxy(): bool
{
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https'
        || !in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true)) {
        return false;
    }
    $secretFile = trim((string)getenv('OKCORE_TRUSTED_PROXY_SECRET_FILE'));
    if ($secretFile === '') {
        $home = trim((string)getenv('HOME'));
        if ($home === '') {
            $user = trim((string)getenv('USER')) ?: trim((string)get_current_user());
            $home = $user !== '' ? '/Users/' . $user : '';
        }
        $secretFile = $home !== '' ? $home . '/Library/Application Support/OK-Core/local-https/proxy-secret' : '';
    }
    $expected = $secretFile !== '' ? @trim((string)file_get_contents($secretFile)) : '';
    $provided = trim((string)($_SERVER['HTTP_X_OKCORE_PROXY_SECRET'] ?? ''));
    return $expected !== '' && hash_equals($expected, $provided);
}

function okdiag_require_authentication(): void
{
    $config = okdiag_private_config();
    $expected = trim((string)getenv('OKDIAG_API_TOKEN'));
    if ($expected === '') $expected = trim((string)($config['OKDIAG_API_TOKEN'] ?? ''));
    $provided = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if ($provided === '' && okdiag_trusted_local_proxy()) {
        $provided = trim((string)($_SERVER['HTTP_X_OKDIAG_AUTHORIZATION'] ?? ''));
    }
    if ($expected === '' || !preg_match('/^Bearer\s+(.+)$/i', $provided, $m)
        || !hash_equals($expected, trim($m[1]))) {
        okdiag_json_response(401, ['success' => false, 'message' => 'Authentication required']);
    }
}

function okdiag_require_https(): void
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    if (!$isHttps && okdiag_trusted_local_proxy()) $isHttps = true;
    $localOverride = strtolower((string)getenv('APP_ENV')) !== 'production'
        && getenv('OKDIAG_ALLOW_INSECURE_LOCAL') === '1'
        && in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);
    if (!$isHttps && !$localOverride) {
        okdiag_json_response(400, ['success' => false, 'message' => 'HTTPS is required']);
    }
}

function okdiag_read_json(): array
{
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($method !== 'POST' || strpos($contentType, 'application/json') !== 0) {
        okdiag_json_response(405, ['success' => false, 'message' => 'JSON POST required']);
    }
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);
    if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
        okdiag_json_response(400, ['success' => false, 'message' => 'Invalid JSON']);
    }
    return [$payload, (string)$raw];
}

function okdiag_string(array $payload, string $key, bool $required = false): string
{
    $value = isset($payload[$key]) && is_scalar($payload[$key]) ? trim((string)$payload[$key]) : '';
    if ($required && $value === '') {
        okdiag_json_response(400, ['success' => false, 'message' => "Missing required field: {$key}"]);
    }
    return $value;
}

function okdiag_allowed_group(int $groupId): bool
{
    $raw = trim((string)getenv('OKDIAG_ALLOWED_GROUP_IDS'));
    if ($raw === '') {
        $config = okdiag_private_config();
        $raw = trim((string)($config['OKDIAG_ALLOWED_GROUP_IDS'] ?? ''));
    }
    if ($raw === '') return false;
    $allowed = array_filter(array_map('intval', preg_split('/\s*,\s*/', $raw)));
    return in_array($groupId, $allowed, true);
}

function okdiag_resolve_system_user(mysqli $db): int
{
    // Resolve the provisioned local identity independently of incoming SSO claims.
    $result = $db->query("SELECT user_id FROM users
        WHERE BINARY name = 'OKDiag（AI生成）' AND role = 'system' AND is_active = 1
          AND BINARY password = 'SSO_LOGIN_DISABLED'
          AND sso_sub IS NULL AND sso_user_id IS NULL AND sso_username IS NULL
        LIMIT 2");
    if (!$result || $result->num_rows !== 1) {
        throw new RuntimeException('Expected exactly one active OKDiag service user');
    }
    return (int)$result->fetch_row()[0];
}

function okdiag_validate_group(mysqli $db, int $userId, int $groupId): void
{
    if ($groupId <= 0 || !okdiag_allowed_group($groupId)) {
        okdiag_json_response(403, ['success' => false, 'message' => 'Group is not allowed']);
    }
    $stmt = $db->prepare('SELECT 1 FROM knowledge_groups kg INNER JOIN kgroup_user_link kug ON kug.group_id=kg.group_id AND kug.user_id=? AND kug.deleted=0 WHERE kg.group_id=? AND kg.deleted=0 LIMIT 1');
    if (!$stmt) throw new RuntimeException('Failed to prepare group validation');
    $stmt->bind_param('ii', $userId, $groupId); $stmt->execute(); $stmt->store_result();
    $ok = $stmt->num_rows > 0; $stmt->close();
    if (!$ok) okdiag_json_response(403, ['success' => false, 'message' => 'Group access denied']);
}

function okdiag_import(mysqli $db, array $payload, string $raw, bool $retryDuplicate = true): array
{
    if (array_key_exists('user_id', $payload)) {
        okdiag_json_response(400, ['success' => false, 'message' => 'user_id must not be supplied']);
    }
    $sourceSystem = okdiag_string($payload, 'source_system', true);
    $sourceType = strtolower(okdiag_string($payload, 'source_type', true));
    $sourceId = okdiag_string($payload, 'source_id', true);
    $sourceUserRef = okdiag_string($payload, 'source_user_ref', true);
    $sourceUserName = okdiag_string($payload, 'source_user_name', true);
    $groupRaw = okdiag_string($payload, 'group_id', true);
    if (!ctype_digit($groupRaw)) okdiag_json_response(400, ['success' => false, 'message' => 'group_id must be a positive integer']);
    $groupId = (int)$groupRaw;
    $content = okdiag_string($payload, 'knowledge_fragment_content', true);
    if ($sourceSystem !== 'OKDiag' || $sourceType !== 'discussion' || $sourceUserRef !== 'okdiag-ai'
        || $sourceUserName !== 'OKDiag（AI生成）' || $groupId <= 0) {
        okdiag_json_response(400, ['success' => false, 'message' => 'Invalid source or group']);
    }
    if (!isset($payload['okdiag_provenance']) || !is_array($payload['okdiag_provenance'])) {
        okdiag_json_response(400, ['success' => false, 'message' => 'okdiag_provenance must be an object']);
    }
    if (strlen($sourceId) > 191 || mb_strlen(okdiag_string($payload, 'title'), 'UTF-8') > 255) {
        okdiag_json_response(400, ['success' => false, 'message' => 'Field length exceeds the database limit']);
    }
    $userId = okdiag_resolve_system_user($db);
    okdiag_validate_group($db, $userId, $groupId);
    $fields = [$sourceSystem, $sourceType, $sourceId, (string)$groupId, okdiag_string($payload, 'title'), okdiag_string($payload, 'selected_contents'), $content, json_encode($payload['okdiag_provenance'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    $check = $db->prepare('SELECT externalized_contents_id,title,selected_contents,knowledge_fragment_content,raw_payload,group_id FROM externalized_contents WHERE source_system=? AND source_type=? AND source_id=? LIMIT 1');
    if (!$check) throw new RuntimeException('Failed to prepare duplicate check');
    $check->bind_param('sss', $sourceSystem, $sourceType, $sourceId); $check->execute(); $result = $check->get_result();
    if ($existing = $result->fetch_assoc()) {
        $check->close();
        $existingPayload = json_decode((string)$existing['raw_payload'], true);
        $same = (string)$existing['title'] === $fields[4]
            && (string)$existing['selected_contents'] === $fields[5]
            && (string)$existing['knowledge_fragment_content'] === $fields[6]
            && (int)$existing['group_id'] === $groupId
            && is_array($existingPayload)
            && json_encode($existingPayload['okdiag_provenance'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) === $fields[7];
        if (!$same) okdiag_json_response(409, ['success' => false, 'message' => 'Conflicting source identifier', 'externalizedContentsId' => (int)$existing['externalized_contents_id']]);
        return ['externalizedContentsId' => (int)$existing['externalized_contents_id'], 'duplicate' => true];
    }
    $check->close();
    $title=$fields[4]; $selected=$fields[5]; $sourceRef=$sourceUserRef; $type='discussion'; $externalizedType='discussion'; $discussed='DONE'; $null=null;
    $stmt=$db->prepare('INSERT INTO externalized_contents (user_id,group_id,remarked_utterance_id,thought_experience_node_id,externalized_type,used_remarked_utterance,selected_contents,knowledge_fragment_content,stage1,stage2,stage3,source_system,source_type,source_id,source_user_ref,title,raw_payload,discussed) VALUES (?, ?, ?, ?, ?, 0, ?, ?, "", "", "", ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) throw new RuntimeException('Failed to prepare KF insert');
    $stmt->bind_param('iissssssssssss', $userId,$groupId,$null,$sourceId,$externalizedType,$selected,$content,$sourceSystem,$type,$sourceId,$sourceRef,$title,$raw,$discussed);
    try {
        $inserted = $stmt->execute();
        $errno = $stmt->errno;
    } catch (mysqli_sql_exception $error) {
        $errno = $error->getCode();
        $inserted = false;
    }
    if (!$inserted) {
        $stmt->close();
        // A concurrent request can win the unique-key race after the lookup.
        // Re-read and apply the identical-content check; never update that row.
        if ($errno === 1062 && $retryDuplicate) return okdiag_import($db, $payload, $raw, false);
        throw new RuntimeException('KF insert failed');
    }
    $id=(int)$db->insert_id; $stmt->close(); return ['externalizedContentsId'=>$id,'duplicate'=>false];
}
