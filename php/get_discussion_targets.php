<?php
require_once __DIR__ . '/discussion_endpoint_bootstrap.php';
try {
    $primary = ok_core_request_fragment_refs($_GET, 'fragment_id');
    if (count($primary) !== 1) throw new InvalidArgumentException('復元対象のKFを1件指定してください。');
    if (!ok_core_group_has_fragment_refs($mysqli, $access['group_id'], $primary)) discussion_reply(403, ['status'=>'error','message'=>'KFを参照できません。']);
    $rows = ok_core_discussion_rows($mysqli, $primary, $access['group_id'], 1);
    $refs = array_values(array_unique(array_merge($primary, $rows ? $rows[0]['fragment_refs'] : [])));
    $ids = array_map(fn($ref)=>(int)explode(':', $ref, 2)[1], $refs);
    discussion_reply(200, ['status'=>'ok','fragment_refs'=>$refs,'targets'=>$ids]);
} catch (InvalidArgumentException | JsonException $error) {
    discussion_reply(400, ['status'=>'error','message'=>$error->getMessage()]);
} catch (Throwable $error) {
    error_log('Discussion target read failed: ' . $error->getMessage());
    discussion_reply(503, ['status'=>'error','message'=>'議論対象を復元できません。']);
}
