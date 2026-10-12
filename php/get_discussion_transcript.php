<?php

declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
hcimlab_start_session();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

function ok_core_transcript_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    header('Allow: GET');
    ok_core_transcript_reply(405, ['error' => 'method_not_allowed']);
}
$userId = (string)($_SESSION['USERID'] ?? '');
session_write_close();
if ($userId === '' || (int)$userId <= 0) ok_core_transcript_reply(401, ['error' => 'not_logged_in', 'message' => 'ログインが必要です。']);
$groupId = (string)($_GET['group_id'] ?? '');
$fragmentId = (string)($_GET['externalized_contents_id'] ?? '');
$sessionId = isset($_GET['session_id']) ? (string)$_GET['session_id'] : null;
$offsetRaw = isset($_GET['offset']) ? (string)$_GET['offset'] : null;
if (!ctype_digit($groupId) || (int)$groupId <= 0 || !ctype_digit($fragmentId) || (int)$fragmentId <= 0
    || ($sessionId !== null && ($sessionId === '' || strlen($sessionId) > 764))
    || ($offsetRaw !== null && (!ctype_digit($offsetRaw) || strlen($offsetRaw) > 9))) {
    ok_core_transcript_reply(400, ['error' => 'invalid_parameters', 'message' => '組織またはKFの指定が正しくありません。']);
}
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/knowledge_group_access.php';
require_once __DIR__ . '/discussion_transcript_reader.php';

$sourceDb = null;
try {
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        ok_core_transcript_reply(503, ['error' => 'database_unavailable', 'message' => 'OK-CoreのDBに接続できません。']);
    }
    $group = ok_core_resolve_active_group($mysqli, $groupId, $userId);
    if ($group['status'] !== 200 || $group['group_id'] === '') {
        ok_core_transcript_reply(403, ['error' => 'forbidden_group', 'message' => 'この組織の閲覧権限がありません。']);
    }
    $rows = discussion_transcript_rows($mysqli,
        "SELECT source_system, source_id FROM externalized_contents
          WHERE externalized_contents_id = ? AND group_id = ? AND deleted = 0
            AND externalized_type = 'discussion'", [$fragmentId, $groupId]);
    if (!$rows) {
        ok_core_transcript_reply(404, ['error' => 'fragment_not_shared', 'message' => 'この組織に共有されたdiscussion KFが見つかりません。']);
    }
    $config = discussion_transcript_config();
    $sourceId = discussion_transcript_source_id($rows[0], $config);
    $sourceDb = discussion_transcript_connect($config);
    $transcript = discussion_transcript_read($sourceDb, $sourceId, $sessionId,
        $offsetRaw === null ? null : (int)$offsetRaw, 200,
        ['fragment_id' => $fragmentId, 'group_id' => $groupId]);
    $sourceDb->close();
    $sourceDb = null;
    ok_core_transcript_reply(200, ['status' => 'ok', 'transcript' => $transcript]);
} catch (DiscussionTranscriptException $error) {
    ok_core_transcript_reply($error->httpStatus, ['error' => $error->errorCode, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('OK-Core discussion transcript read failed (' . get_class($error) . ').');
    ok_core_transcript_reply(500, ['error' => 'transcript_read_failed', 'message' => '文字起こしを取得できません。接続先DBの構造と読み取り権限を確認してください。']);
} finally {
    if ($sourceDb instanceof mysqli) $sourceDb->close();
}
