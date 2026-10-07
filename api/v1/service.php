<?php
declare(strict_types=1);

final class OkCoreApiFailure extends RuntimeException
{
    public int $httpStatus;
    public string $apiCode;
    public function __construct(int $status, string $code, string $message)
    {
        parent::__construct($message);
        $this->httpStatus = $status;
        $this->apiCode = $code;
    }
}

function ok_core_api_config(): array
{
    $config = ['token' => (string)(getenv('OK_CORE_API_TOKEN') ?: ''),
               'system_code' => (string)(getenv('OK_CORE_SOURCE_SYSTEM_CODE') ?: 'forest-platform')];
    $path = __DIR__ . '/config.local.php';
    if (is_file($path)) {
        $local = require $path;
        if (is_array($local)) $config = array_replace($config, $local);
    }
    return $config;
}

function ok_core_api_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$key])) return trim((string)$_SERVER[$key]);
    if ($name === 'Authorization' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }
    foreach (function_exists('getallheaders') ? getallheaders() : [] as $header => $value) {
        if (strcasecmp($header, $name) === 0) return trim((string)$value);
    }
    return '';
}

function ok_core_api_reply(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    $requestId = ok_core_api_header('X-Request-Id');
    if ($requestId !== '') {
        $requestId = substr(preg_replace('/[^A-Za-z0-9._-]/', '', $requestId), 0, 80);
        header('X-Request-Id: ' . $requestId);
        $body['meta'] = ['request_id' => $requestId];
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function ok_core_api_require(bool $condition, int $status, string $code, string $message): void
{
    if (!$condition) throw new OkCoreApiFailure($status, $code, $message);
}

function ok_core_api_actor(mysqli $db, array $config): array
{
    $token = (string)$config['token'];
    $auth = ok_core_api_header('Authorization');
    ok_core_api_require($token !== '' && str_starts_with($auth, 'Bearer ') &&
        hash_equals($token, substr($auth, 7)), 401, 'INVALID_TOKEN', 'API token is missing or invalid.');
    $sub = ok_core_api_header('X-Acting-User-Sub');
    ok_core_api_require($sub !== '', 401, 'ACTING_USER_REQUIRED', 'Acting user is required.');
    $stmt = $db->prepare('SELECT user_id, sso_sub FROM users WHERE sso_sub = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('s', $sub);
    $stmt->execute();
    $actor = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    ok_core_api_require(is_array($actor), 403, 'UNKNOWN_USER', 'Acting user is not active in OK-Core.');
    return $actor;
}

function ok_core_api_groups(mysqli $db, int $userId): array
{
    $stmt = $db->prepare('SELECT kg.group_id, kg.name, kul.role
        FROM kgroup_user_link kul INNER JOIN knowledge_groups kg ON kg.group_id = kul.group_id
        WHERE kul.user_id = ? AND kul.deleted = 0 AND kg.deleted = 0 ORDER BY kg.group_id');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = ['group_id' => (string)$row['group_id'], 'name' => (string)$row['name'],
                     'role' => (string)$row['role']];
    }
    $stmt->close();
    return $groups;
}

function ok_core_api_valid_groups(array $raw, array $allowed): array
{
    $allowedIds = array_fill_keys(array_column($allowed, 'group_id'), true);
    $ids = [];
    foreach ($raw as $value) {
        $id = (string)$value;
        ok_core_api_require(ctype_digit($id) && (int)$id > 0 && isset($allowedIds[$id]),
            403, 'GROUP_FORBIDDEN', 'A requested share group is unavailable.');
        $ids[(int)$id] = (int)$id;
    }
    return array_values($ids);
}

function ok_core_api_json_body(): array
{
    $raw = file_get_contents('php://input');
    ok_core_api_require(is_string($raw) && strlen($raw) <= 1048576, 413, 'PAYLOAD_TOO_LARGE', 'Payload exceeds 1 MB.');
    $body = json_decode($raw, true);
    ok_core_api_require(is_array($body), 400, 'INVALID_JSON', 'Expected a JSON object.');
    return $body;
}

function ok_core_api_is_iso_time(string $value): bool
{
    if (!preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/', $value)) return false;
    try { new DateTimeImmutable($value); return true; }
    catch (Throwable $error) { return false; }
}

function ok_core_api_stages(array $body): array
{
    $stages = ['stage1' => '', 'stage2' => '', 'stage3' => ''];
    ok_core_api_require(isset($body['stages']) && is_array($body['stages']), 400, 'INVALID_STAGES', 'Stages are required.');
    foreach ($body['stages'] as $stage) {
        ok_core_api_require(is_array($stage), 400, 'INVALID_STAGES', 'Invalid stage.');
        $code = (string)($stage['stage_code'] ?? '');
        ok_core_api_require(array_key_exists($code, $stages), 400, 'INVALID_STAGES', 'Unknown stage code.');
        $content = (string)($stage['rendered_content'] ?? '');
        ok_core_api_require((function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : preg_match_all('/./us', $content)) <= 255, 422, 'STAGE_TOO_LONG', 'Stage exceeds 255 characters.');
        $stages[$code] = $content;
    }
    return $stages;
}

function ok_core_api_sync(mysqli $db, string $method, string $system, string $externalId, array $actor, array $config): array
{
    ok_core_api_require($system === (string)$config['system_code'], 403, 'SOURCE_SYSTEM_FORBIDDEN', 'Source system is not allowed.');
    ok_core_api_require(ctype_digit($externalId) && (int)$externalId > 0, 400, 'INVALID_EXTERNAL_ID', 'External KF ID must be positive.');
    $revision = 0;
    $deletedAt = null;
    $body = null;
    $summary = '';
    $stages = [];
    $groups = [];
    if ($method === 'PUT') {
        $body = ok_core_api_json_body();
        ok_core_api_require(($body['protocol']['code'] ?? '') === 'OK_CORE_KF' &&
            ($body['protocol']['version'] ?? '') === '1.0', 422, 'UNSUPPORTED_PROTOCOL', 'Unsupported KF protocol.');
        ok_core_api_require(ok_core_api_is_iso_time((string)($body['source_updated_at'] ?? '')),
            400, 'INVALID_UPDATED_AT', 'source_updated_at must include a timezone.');
        ok_core_api_require(is_array($body['activity'] ?? null) &&
            trim((string)($body['activity']['type_code'] ?? '')) !== '' &&
            trim((string)($body['activity']['external_activity_id'] ?? '')) !== '',
            400, 'INVALID_ACTIVITY', 'Activity reference is required.');
        ok_core_api_require(is_array($body['context_package'] ?? null) &&
            trim((string)($body['context_package']['external_context_package_id'] ?? '')) !== '' &&
            is_array($body['context_package']['items'] ?? null),
            400, 'INVALID_CONTEXT', 'Context package is required.');
        ok_core_api_require((string)($body['expresser_sso_sub'] ?? '') === (string)$actor['sso_sub'],
            403, 'EXPRESSER_MISMATCH', 'Expresser differs from acting user.');
        $revisionRaw = (string)($body['source_revision'] ?? '');
        $revision = ctype_digit($revisionRaw) ? (int)$revisionRaw : 0;
        $summary = trim((string)($body['summary'] ?? ''));
        ok_core_api_require($summary !== '' && (function_exists('mb_strlen') ? mb_strlen($summary, 'UTF-8') : preg_match_all('/./us', $summary)) <= 255,
            422, 'INVALID_SUMMARY', 'Summary must contain 1 to 255 characters.');
        ok_core_api_require(isset($body['share_group_ids']) && is_array($body['share_group_ids']),
            400, 'INVALID_GROUPS', 'share_group_ids must be an array.');
        $groups = ok_core_api_valid_groups($body['share_group_ids'], ok_core_api_groups($db, (int)$actor['user_id']));
        $stages = ok_core_api_stages($body);
    } else {
        $revisionRaw = ok_core_api_header('X-Source-Revision');
        $revision = ctype_digit($revisionRaw) ? (int)$revisionRaw : 0;
        $rawDeletedAt = ok_core_api_header('X-Source-Deleted-At');
        ok_core_api_require(ok_core_api_is_iso_time($rawDeletedAt),
            400, 'INVALID_DELETED_AT', 'Source deletion time is required.');
        $deletedAt = date('Y-m-d H:i:s', strtotime($rawDeletedAt));
    }
    ok_core_api_require($revision > 0, 400, 'INVALID_REVISION', 'Source revision must be positive.');
    $snapshot = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('INSERT IGNORE INTO kf_source_mappings (source_system, external_kf_id) VALUES (?, ?)');
        $stmt->bind_param('ss', $system, $externalId);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare('SELECT experience_knowledge_id, source_revision, source_deleted_at, snapshot_json
            FROM kf_source_mappings WHERE source_system = ? AND external_kf_id = ? FOR UPDATE');
        $stmt->bind_param('ss', $system, $externalId);
        $stmt->execute();
        $mapping = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$mapping) throw new RuntimeException('Could not lock source mapping.');
        $currentRevision = (int)$mapping['source_revision'];
        ok_core_api_require($revision >= $currentRevision, 409, 'STALE_REVISION', 'A newer revision was already accepted.');
        if ($revision === $currentRevision) {
            ok_core_api_require($method === 'DELETE' ? $mapping['source_deleted_at'] === $deletedAt :
                $mapping['source_deleted_at'] === null && $mapping['snapshot_json'] === $snapshot,
                409, 'REVISION_CONFLICT', 'The same revision has different content.');
            $db->commit();
            return ['knowledge_fragment_id' => (string)($mapping['experience_knowledge_id'] ?? ''),
                    'external_kf_id' => $externalId, 'accepted_revision' => $revision, 'duplicate' => true];
        }
        $localId = (int)($mapping['experience_knowledge_id'] ?? 0);
        if ($localId > 0) {
            $stmt = $db->prepare('SELECT user_id FROM experience_knowledges WHERE experience_knowledge_id = ? FOR UPDATE');
            $stmt->bind_param('i', $localId);
            $stmt->execute();
            $owner = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            ok_core_api_require($owner && (int)$owner['user_id'] === (int)$actor['user_id'],
                403, 'OWNER_MISMATCH', 'This fragment belongs to another user.');
        }
        if ($method === 'PUT') {
            if ($localId === 0) {
                $candidate = (int)$externalId;
                $stmt = $db->prepare('SELECT ek.user_id, ek.knowledge_fragment_content, km.id AS mapped_id
                    FROM experience_knowledges ek LEFT JOIN kf_source_mappings km
                      ON km.experience_knowledge_id = ek.experience_knowledge_id
                    WHERE ek.experience_knowledge_id = ? FOR UPDATE');
                $stmt->bind_param('i', $candidate);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($existing && $existing['mapped_id'] === null &&
                    (int)$existing['user_id'] === (int)$actor['user_id'] &&
                    trim((string)$existing['knowledge_fragment_content']) === $summary) {
                    $localId = $candidate;
                } else {
                    $stmt = $db->prepare('INSERT INTO experience_knowledges
                        (selected_contents, knowledge_fragment_content, user_id, stage1, stage2, stage3)
                        VALUES (?, ?, ?, ?, ?, ?)');
                    $selected = $summary;
                    $userId = (int)$actor['user_id'];
                    $stmt->bind_param('ssisss', $selected, $summary, $userId,
                        $stages['stage1'], $stages['stage2'], $stages['stage3']);
                    $stmt->execute();
                    $localId = (int)$db->insert_id;
                    $stmt->close();
                }
            }
            $stmt = $db->prepare('UPDATE experience_knowledges SET knowledge_fragment_content = ?,
                stage1 = ?, stage2 = ?, stage3 = ?, user_id = ?, deleted = 0 WHERE experience_knowledge_id = ?');
            $userId = (int)$actor['user_id'];
            $stmt->bind_param('ssssii', $summary, $stages['stage1'], $stages['stage2'], $stages['stage3'], $userId, $localId);
            $stmt->execute();
            $stmt->close();
            $stmt = $db->prepare('UPDATE shared_nodes SET deleted = 1 WHERE experience_knowledge_id = ?');
            $stmt->bind_param('i', $localId);
            $stmt->execute();
            $stmt->close();
            $stmt = $db->prepare('INSERT INTO shared_nodes (experience_knowledge_id, knowledge_group_id, deleted)
                VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE deleted = 0');
            foreach ($groups as $groupId) {
                $stmt->bind_param('ii', $localId, $groupId);
                $stmt->execute();
            }
            $stmt->close();
        } elseif ($localId > 0) {
            $stmt = $db->prepare('UPDATE experience_knowledges SET deleted = 1 WHERE experience_knowledge_id = ?');
            $stmt->bind_param('i', $localId);
            $stmt->execute();
            $stmt->close();
            $stmt = $db->prepare('UPDATE shared_nodes SET deleted = 1 WHERE experience_knowledge_id = ?');
            $stmt->bind_param('i', $localId);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $db->prepare('UPDATE kf_source_mappings SET experience_knowledge_id = ?, source_revision = ?,
            source_deleted_at = ?, snapshot_json = ? WHERE source_system = ? AND external_kf_id = ?');
        $mappedLocalId = $localId > 0 ? $localId : null;
        $stmt->bind_param('iissss', $mappedLocalId, $revision,
            $deletedAt, $snapshot, $system, $externalId);
        $stmt->execute();
        $stmt->close();
        $db->commit();
        return ['knowledge_fragment_id' => $localId > 0 ? (string)$localId : '',
                'external_kf_id' => $externalId, 'accepted_revision' => $revision,
                'synced_at' => date(DATE_ATOM)];
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}

function ok_core_api_dispatch(): void
{
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = (string)(parse_url($requestUri, PHP_URL_PATH) ?: '');
    $prefix = '/api/v1';
    $offset = strpos($path, $prefix);
    $route = $offset === false ? (string)($_SERVER['PATH_INFO'] ?? '') : substr($path, $offset + strlen($prefix));
    $route = rtrim($route, '/');
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    try {
        if ($method === 'GET' && $route === '/health') {
            require __DIR__ . '/../../php/connect_db.php';
            ok_core_api_require(isset($mysqli) && $mysqli instanceof mysqli && $mysqli->ping(),
                503, 'DATABASE_UNAVAILABLE', 'Database unavailable.');
            ok_core_api_reply(200, ['data' => ['status' => 'ok']]);
            return;
        }
        if ($method === 'GET' && $route === '/capabilities') {
            ok_core_api_reply(200, ['data' => ['supported_protocols' => [
                ['protocol_code' => 'OK_CORE_KF', 'protocol_version' => '1.0']],
                'max_payload_bytes' => 1048576]]);
            return;
        }
        $config = ok_core_api_config();
        require __DIR__ . '/../../php/connect_db.php';
        ok_core_api_require(isset($mysqli) && $mysqli instanceof mysqli, 503, 'DATABASE_UNAVAILABLE', 'Database unavailable.');
        $mysqli->set_charset('utf8mb4');
        $actor = ok_core_api_actor($mysqli, $config);
        if ($method === 'GET' && $route === '/users/me/knowledge-groups') {
            ok_core_api_reply(200, ['data' => ok_core_api_groups($mysqli, (int)$actor['user_id'])]);
            return;
        }
        if (preg_match('#^/source-systems/([^/]+)/knowledge-fragments/([^/]+)$#', $route, $match) &&
            in_array($method, ['PUT', 'DELETE'], true)) {
            $data = ok_core_api_sync($mysqli, $method, rawurldecode($match[1]),
                rawurldecode($match[2]), $actor, $config);
            ok_core_api_reply(200, ['data' => $data]);
            return;
        }
        throw new OkCoreApiFailure(404, 'NOT_FOUND', 'API route not found.');
    } catch (OkCoreApiFailure $error) {
        ok_core_api_reply($error->httpStatus, ['error' => ['code' => $error->apiCode,
            'message' => $error->getMessage()]]);
    } catch (Throwable $error) {
        error_log('OK-Core API failure: ' . $error->getMessage());
        ok_core_api_reply(500, ['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Internal API error.']]);
    }
}
