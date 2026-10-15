<?php
require_once __DIR__ . '/discussion_endpoint_bootstrap.php';
try {
    $refs = ok_core_request_fragment_refs($_GET, 'fragment_id');
    if (!$refs) discussion_reply(200, ['status'=>'ok','items'=>[]]);
    if (!ok_core_group_has_fragment_refs($mysqli, $access['group_id'], $refs)) discussion_reply(403, ['status'=>'error','message'=>'選択KFがグループに共有されていません。']);
    $limit = max(1, min(500, (int)($_GET['limit'] ?? 200)));
    $rows = ok_core_discussion_rows($mysqli, $refs, $access['group_id'], $limit);
    discussion_reply(200, ['status'=>'ok','items'=>array_reverse($rows)]);
} catch (InvalidArgumentException | JsonException $error) {
    discussion_reply(400, ['status'=>'error','message'=>$error->getMessage()]);
} catch (Throwable $error) {
    error_log('Discussion read failed: ' . $error->getMessage());
    discussion_reply(503, ['status'=>'error','message'=>'議論を読み込めません。']);
}
