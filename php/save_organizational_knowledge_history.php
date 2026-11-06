<?php
declare(strict_types=1);
require_once __DIR__ . '/organizational_history_endpoint_bootstrap.php';
try {
    $source = trim((string)($_POST['source_table'] ?? 'knowledge_explorer'));
    ok_core_history_source($source);
    $id = trim((string)($_POST['organizational_knowledge_id'] ?? $_POST['node_id'] ?? ''));
    $operation = bin2hex(random_bytes(16));
    $mysqli->begin_transaction();
    $historyId = ok_core_save_organizational_knowledge_history($mysqli, $source, $id, $historyGroup, $historyUser, 'manual', $operation);
    $mysqli->commit();
    organizational_history_reply(200, ['status'=>'ok', 'message'=>'スナップショットを保存しました', 'history_id'=>$historyId, 'operation_id'=>$operation]);
} catch (OrganizationalHistoryStorageException $error) {
    $mysqli->rollback();
    organizational_history_reply(503, ['status'=>'error', 'message'=>$error->getMessage()]);
} catch (InvalidArgumentException $error) {
    $mysqli->rollback();
    organizational_history_reply(400, ['status'=>'error', 'message'=>$error->getMessage()]);
} catch (DomainException $error) {
    $mysqli->rollback();
    organizational_history_reply(403, ['status'=>'error', 'message'=>$error->getMessage()]);
} catch (Throwable $error) {
    $mysqli->rollback();
    error_log('save_organizational_knowledge_history: ' . $error->getMessage());
    organizational_history_reply(500, ['status'=>'error', 'message'=>'履歴を保存できませんでした']);
}
