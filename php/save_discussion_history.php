<?php
require_once __DIR__ . '/discussion_endpoint_bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') discussion_reply(405, ['status'=>'error','message'=>'Method Not Allowed']);
try {
    $refs = ok_core_request_fragment_refs($_POST);
    if (!$refs) throw new InvalidArgumentException('議論対象のKFを選択してください。');
    if (!ok_core_group_has_fragment_refs($mysqli, $access['group_id'], $refs)) discussion_reply(403, ['status'=>'error','message'=>'選択KFがグループに共有されていません。']);
    $content = trim((string)($_POST['content'] ?? ''));
    if ($content === '' || preg_match_all('/./us', $content) > 255) throw new InvalidArgumentException('投稿は1～255文字で入力してください。');
    $row = ok_core_insert_discussion($mysqli, (int)$_SESSION['USERID'], $content, $refs);
    discussion_reply(200, ['status'=>'ok'] + $row);
} catch (InvalidArgumentException | JsonException $error) {
    discussion_reply(400, ['status'=>'error','message'=>$error->getMessage()]);
} catch (Throwable $error) {
    error_log('Discussion save failed: ' . $error->getMessage());
    discussion_reply(503, ['status'=>'error','message'=>'議論を保存できません。discussion_historyの構造を確認してください。']);
}
