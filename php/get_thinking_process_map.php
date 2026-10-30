<?php

declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
hcimlab_start_session();
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/knowledge_group_access.php';
require_once __DIR__ . '/source_context_service.php';

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
    $fragment = source_context_fragment($mysqli, $userId, (int)$groupRaw, (int)$fragmentRaw, $actingSub);
    $result = source_context_result($fragment, $actingSub, (int)$groupRaw, 'forest.thinking-process-map.v1', 'latest');
    $map = $result['data']['map'];
    $map['summary'] = (string)$fragment['knowledge_fragment_content'];
    ok_core_process_map_reply(200, ['status' => 'ok', 'map' => $map]);
} catch (SourceContextException $error) {
    ok_core_process_map_reply($error->httpStatus, ['error' => $error->errorCode, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('OK-Core process map read failed: ' . get_class($error));
    ok_core_process_map_reply(500, ['error' => 'process_map_read_failed']);
}
