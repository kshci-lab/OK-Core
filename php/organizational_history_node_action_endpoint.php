<?php
declare(strict_types=1);
require_once __DIR__ . '/organizational_history_endpoint_bootstrap.php';
require_once __DIR__ . '/organizational_history_node_actions.php';
if (!isset($historyAction) || !in_array($historyAction, ['rename', 'delete', 'reorder'], true)) {
    organizational_history_reply(404, ['status'=>'error', 'message'=>'Not Found']);
}
try {
    $raw = trim((string)($_POST['node_id'] ?? ''));
    if (!ctype_digit($raw) || (int)$raw <= 0 || strlen($raw) > 10 || (int)$raw > 2147483647) throw new InvalidArgumentException('node_id が不正です');
    $value = $historyAction === 'rename' ? trim((string)($_POST['new_title'] ?? '')) : trim((string)($_POST['dir'] ?? ''));
    $result = ok_core_history_node_action($mysqli, $historyAction, (int)$raw, $historyGroup, $historyUser, $value);
    organizational_history_reply(200, ['status'=>'ok'] + $result);
} catch (OrganizationalHistoryStorageException $error) {
    organizational_history_reply(503, ['status'=>'error', 'message'=>$error->getMessage()]);
} catch (InvalidArgumentException $error) {
    organizational_history_reply(400, ['status'=>'error', 'message'=>$error->getMessage()]);
} catch (DomainException $error) {
    organizational_history_reply(403, ['status'=>'error', 'message'=>$error->getMessage()]);
} catch (Throwable $error) {
    error_log('organizational_history_node_action: ' . $error->getMessage());
    organizational_history_reply(500, ['status'=>'error', 'message'=>'組織知と履歴を保存できませんでした']);
}
