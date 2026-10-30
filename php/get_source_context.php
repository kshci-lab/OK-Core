<?php
declare(strict_types=1);
require_once __DIR__ . '/session_bootstrap.php';
hcimlab_start_session();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/source_context_service.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') throw new SourceContextException('GETを使用してください。', 405, 'METHOD_NOT_ALLOWED');
    $user = (int)($_SESSION['USERID'] ?? 0); $actor = trim((string)($_SESSION['HCIMLAB_SSO_SUB'] ?? ''));
    if ($user <= 0 || $actor === '') throw new SourceContextException('ログインが必要です。', 401, 'not_logged_in');
    $id = $_GET['knowledge_fragment_id'] ?? $_GET['experience_knowledge_id'] ?? '';
    $group = $_GET['group_id'] ?? '';
    if (!is_string($id) || !ctype_digit($id) || (int)$id <= 0 || !is_string($group) || !ctype_digit($group) || (int)$group <= 0) {
        throw new SourceContextException('KFとグループの指定が正しくありません。', 400, 'invalid_group_or_fragment');
    }
    require __DIR__ . '/connect_db.php';
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) throw new SourceContextException('DBに接続できません。', 503, 'database_unavailable');
    $fragment = source_context_fragment($mysqli, $user, (int)$group, (int)$id, $actor);
    $format = $_GET['format'] ?? 'context-package.v1'; $mode = $_GET['mode'] ?? 'latest';
    if (!is_string($format) || !is_string($mode)) throw new SourceContextException('表示方式が正しくありません。', 400, 'INVALID_CONTEXT_MODE');
    echo json_encode(['status' => 'ok', 'context' => source_context_result($fragment, $actor, (int)$group, $format, $mode)], JSON_UNESCAPED_UNICODE);
} catch (SourceContextException $error) {
    http_response_code($error->httpStatus); echo json_encode(['status' => 'error', 'error' => $error->errorCode, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    http_response_code(500); echo '{"status":"error","error":"CONTEXT_READ_FAILED","message":"形成文脈を取得できません。"}';
}
