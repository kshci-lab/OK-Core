<?php
declare(strict_types=1);

require_once __DIR__ . '/contract.php';

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
    if (!preg_match('/^[A-Za-z0-9._:-]{1,80}$/D', $requestId)) $requestId = 'ok-core-' . bin2hex(random_bytes(12));
    header('X-Request-Id: ' . $requestId);
    $body['meta'] = ['request_id' => $requestId];
    if ($status === 401) header('WWW-Authenticate: Bearer');
    if (ob_get_level() > 0) ob_clean();
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function ok_core_api_acting_sub(): string
{
    $sub = ok_core_api_header('X-Acting-User-Sub');
    ok_core_api_require(ok_core_api_text($sub, 1, 191) && !preg_match('/[\x00-\x1f\x7f]/', $sub),
        401, 'ACTING_USER_REQUIRED', 'Acting user is required.');
    return $sub;
}

function ok_core_api_actor(mysqli $db, array $config): array
{
    $sub = ok_core_api_acting_sub();
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
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    ok_core_api_require($contentType === '' || strtolower(trim(explode(';', $contentType)[0])) === 'application/json',
        415, 'UNSUPPORTED_MEDIA_TYPE', 'Use application/json.');
    $raw = file_get_contents('php://input');
    return ok_core_api_decode_fragment(is_string($raw) ? $raw : '');
}

function ok_core_api_sync(mysqli $db, string $method, string $system, string $externalId, array $actor, array $config): array
{
    ok_core_api_require($system === (string)$config['system_code'], 403, 'SOURCE_SYSTEM_FORBIDDEN', 'Source system is not allowed.');
    ok_core_api_require(ok_core_api_external_id($externalId), 400, 'INVALID_EXTERNAL_ID', 'External KF ID must be a valid string of 1 to 191 characters.');
    $revision = 0;
    $deletedAt = null;
    $body = null;
    $summary = '';
    $stages = [];
    $groups = [];
    if ($method === 'PUT') {
        $body = ok_core_api_json_body();
        $stages = ok_core_api_validate_fragment($body, (string)$actor['sso_sub']);
        $revision = (int)$body['source_revision'];
        $summary = trim($body['summary']);
        $groups = ok_core_api_valid_groups($body['share_group_ids'], ok_core_api_groups($db, (int)$actor['user_id']));
    } else {
        $revisionRaw = ok_core_api_header('X-Source-Revision');
        $revision = ok_core_api_positive_integer($revisionRaw) ? (int)$revisionRaw : 0;
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
        $stmt = $db->prepare('SELECT external_kf_id, experience_knowledge_id, source_revision, source_deleted_at, snapshot_json
            FROM kf_source_mappings WHERE source_system = ? AND external_kf_id = ? FOR UPDATE');
        $stmt->bind_param('ss', $system, $externalId);
        $stmt->execute();
        $mapping = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$mapping) throw new RuntimeException('Could not lock source mapping.');
        // Existing installations may use a case/accent-insensitive DB collation.
        // Never silently update a different producer ID matched by that collation.
        ok_core_api_require((string)$mapping['external_kf_id'] === $externalId,
            409, 'EXTERNAL_ID_COLLISION', 'External ID conflicts with an existing ID under the current database collation.');
        $currentRevision = (int)$mapping['source_revision'];
        ok_core_api_require($revision >= $currentRevision, 409, 'STALE_REVISION', 'A newer revision was already accepted.');
        if ($revision === $currentRevision) {
            ok_core_api_require($method === 'DELETE' ? $mapping['source_deleted_at'] === $deletedAt :
                $mapping['source_deleted_at'] === null && ok_core_api_same_snapshot($mapping['snapshot_json'], $snapshot),
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
                $existing = null;
                // Numeric-ID adoption exists only for explicitly enabled legacy imports.
                if (($config['legacy_adopt_numeric_ids'] ?? false) && ok_core_api_positive_integer($externalId, '2147483647')) {
                    $candidate = (int)$externalId;
                    $stmt = $db->prepare('SELECT ek.user_id, ek.knowledge_fragment_content, km.id AS mapped_id
                        FROM experience_knowledges ek LEFT JOIN kf_source_mappings km
                          ON km.experience_knowledge_id = ek.experience_knowledge_id
                        WHERE ek.experience_knowledge_id = ? FOR UPDATE');
                    $stmt->bind_param('i', $candidate);
                    $stmt->execute();
                    $existing = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }
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
            if (($config['share_policy'] ?? 'replace') === 'replace') {
                $stmt = $db->prepare('UPDATE shared_nodes SET deleted = 1 WHERE fragment_source_type = \'experience\' AND knowledge_fragment_id = ?');
                $stmt->bind_param('i', $localId);
                $stmt->execute();
                $stmt->close();
            }
            $stmt = $db->prepare('INSERT INTO shared_nodes (fragment_source_type, knowledge_fragment_id, knowledge_group_id, deleted)
                VALUES (\'experience\', ?, ?, 0) ON DUPLICATE KEY UPDATE deleted = 0');
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
            $stmt = $db->prepare('UPDATE shared_nodes SET deleted = 1 WHERE fragment_source_type = \'experience\' AND knowledge_fragment_id = ?');
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

function ok_core_api_method(string $method, array $allowed): void
{
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        throw new OkCoreApiFailure(405, 'METHOD_NOT_ALLOWED', 'Method is not allowed for this resource.');
    }
}

function ok_core_api_dispatch(): void
{
    // Suppress incidental DB/bootstrap output; every API response is JSON.
    ob_start();
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = (string)(parse_url($requestUri, PHP_URL_PATH) ?: '');
    $prefix = '/api/v1';
    $offset = strpos($path, $prefix);
    $route = $offset === false ? (string)($_SERVER['PATH_INFO'] ?? '') : substr($path, $offset + strlen($prefix));
    $route = preg_replace('#^/index\.php(?=/|$)#', '', $route);
    $route = rtrim($route, '/');
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    try {
        if ($route === '/capabilities') {
            ok_core_api_method($method, ['GET']);
            ok_core_api_reply(200, ['data' => ok_core_api_capability_data()]);
            return;
        }
        if ($route === '/health') {
            ok_core_api_method($method, ['GET']);
            require __DIR__ . '/../../php/connect_db.php';
            ok_core_api_require(isset($mysqli) && $mysqli instanceof mysqli && $mysqli->ping(),
                503, 'DATABASE_UNAVAILABLE', 'Database unavailable.');
            ok_core_api_reply(200, ['data' => ['status' => 'ok']]);
            return;
        }
        $requestedSystem = null;
        $externalId = null;
        if ($route === '/users/me/knowledge-groups') {
            ok_core_api_method($method, ['GET']);
            $scope = 'groups:read';
        } elseif (preg_match('#^/source-systems/([^/]+)/knowledge-fragments/([^/]+)$#', $route, $match)) {
            ok_core_api_method($method, ['PUT', 'DELETE']);
            $requestedSystem = rawurldecode($match[1]);
            $externalId = rawurldecode($match[2]);
            $scope = $method === 'PUT' ? 'fragments:write' : 'fragments:delete';
        } else {
            throw new OkCoreApiFailure(404, 'NOT_FOUND', 'API route not found.');
        }
        $config = ok_core_api_config();
        $source = ok_core_api_authenticate($config, ok_core_api_header('Authorization'), $requestedSystem);
        ok_core_api_scope($source, $scope);
        ok_core_api_acting_sub();
        require __DIR__ . '/../../php/connect_db.php';
        ok_core_api_require(isset($mysqli) && $mysqli instanceof mysqli, 503, 'DATABASE_UNAVAILABLE', 'Database unavailable.');
        $mysqli->set_charset('utf8mb4');
        $actor = ok_core_api_actor($mysqli, $source);
        if ($externalId === null) {
            ok_core_api_reply(200, ['data' => ok_core_api_groups($mysqli, (int)$actor['user_id'])]);
            return;
        }
        $data = ok_core_api_sync($mysqli, $method, $requestedSystem, $externalId, $actor, $source);
        ok_core_api_reply(200, ['data' => $data]);
    } catch (OkCoreApiFailure $error) {
        $body = ['error' => ['code' => $error->apiCode, 'message' => $error->getMessage()]];
        if ($error->details !== []) $body['error']['details'] = $error->details;
        ok_core_api_reply($error->httpStatus, $body);
    } catch (Throwable $error) {
        error_log('OK-Core API failure: ' . get_class($error));
        ok_core_api_reply(500, ['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Internal API error.']]);
    } finally {
        ob_end_flush();
    }
}
