<?php

declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
hcimlab_start_session();
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/knowledge_group_access.php';
require_once __DIR__ . '/forest_process_map_client.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function ok_core_process_map_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    header('Allow: GET');
    ok_core_process_map_reply(405, ['error' => 'method_not_allowed']);
}
$userId = (int)($_SESSION['USERID'] ?? 0);
$actingSub = trim((string)($_SESSION['HCIMLAB_SSO_SUB'] ?? ''));
if ($userId <= 0 || $actingSub === '') {
    ok_core_process_map_reply(401, ['error' => 'not_logged_in']);
}
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    ok_core_process_map_reply(503, ['error' => 'database_unavailable']);
}
$groupRaw = (string)($_GET['group_id'] ?? '');
$fragmentRaw = (string)($_GET['experience_knowledge_id'] ?? '');
if (!ctype_digit($groupRaw) || (int)$groupRaw <= 0
    || !ctype_digit($fragmentRaw) || (int)$fragmentRaw <= 0) {
    ok_core_process_map_reply(400, ['error' => 'invalid_group_or_fragment']);
}
$group = ok_core_resolve_active_group($mysqli, $groupRaw, (string)$userId);
if ($group['status'] !== 200 || $group['group_id'] === '') {
    ok_core_process_map_reply(403, ['error' => 'forbidden_group']);
}

try {
    $fragmentId = (int)$fragmentRaw;
    $groupId = (int)$groupRaw;
    $statement = $mysqli->prepare(
        'SELECT ek.experience_knowledge_id, ek.thought_experience_node_id,
                km.external_kf_id
           FROM shared_nodes sn
           INNER JOIN experience_knowledges ek
              ON ek.experience_knowledge_id = sn.experience_knowledge_id
           LEFT JOIN kf_source_mappings km
              ON km.experience_knowledge_id = ek.experience_knowledge_id
             AND km.source_system = ? AND km.source_deleted_at IS NULL
          WHERE sn.knowledge_group_id = ? AND sn.deleted = 0
            AND ek.experience_knowledge_id = ? AND ek.deleted = 0
          LIMIT 1'
    );
    if (!$statement) {
        throw new RuntimeException('KF照合に失敗しました。');
    }
    $sourceSystem = 'forest-platform';
    $statement->bind_param('sii', $sourceSystem, $groupId, $fragmentId);
    $statement->execute();
    $result = $statement->get_result();
    $fragment = $result ? $result->fetch_assoc() : null;
    if ($result) $result->free();
    $statement->close();
    if (!$fragment) {
        ok_core_process_map_reply(404, ['error' => 'fragment_not_shared']);
    }

    $mappedId = trim((string)($fragment['external_kf_id'] ?? ''));
    $legacyReference = trim((string)($fragment['thought_experience_node_id'] ?? ''));
    if ($mappedId !== '' && (!ctype_digit($mappedId) || (int)$mappedId <= 0)) {
        ok_core_process_map_reply(404, ['error' => 'source_fragment_unavailable']);
    }
    // Older manually migrated KFs retained their Forest ID and source node ID.
    // The source node must match before accepting that legacy ID as a fallback.
    if ($mappedId === '' && $legacyReference === '') {
        ok_core_process_map_reply(404, ['error' => 'source_fragment_unavailable']);
    }
    $externalId = $mappedId !== '' ? (int)$mappedId : $fragmentId;
    $map = forest_process_map_fetch($externalId, $actingSub, $groupId);
    if ((string)($map['external_kf_id'] ?? '') !== (string)$externalId
        || ($mappedId === '' && (string)($map['source_reference'] ?? '') !== $legacyReference)) {
        ok_core_process_map_reply(502, ['error' => 'source_identity_mismatch']);
    }
    ok_core_process_map_reply(200, ['status' => 'ok', 'map' => $map]);
} catch (ForestProcessMapException $error) {
    ok_core_process_map_reply($error->httpStatus, [
        'error' => $error->errorCode,
        'message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    error_log('OK-Core process map read failed: ' . $error->getMessage());
    ok_core_process_map_reply(500, ['error' => 'process_map_read_failed']);
}
