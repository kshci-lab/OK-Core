<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../php/kf_discussion_view.php';
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
$row = [
    'source_system' => 'discussion-kf-system', 'source_type' => 'discussion',
    'content' => "元の発言\r\n二行目", 'selected_contents' => "元の発言\n二行目",
    'stage1' => '第一の振り返り', 'stage2' => '第二の振り返り', 'stage3' => '',
    'user_name' => '作成者', 'raw_payload' => json_encode(['title' => '知のタイトル']),
];
$original = $row;
$view = kf_card_view($row);
check($view['title'] === '知のタイトル', 'Use the received title');
check($view['content'] === '', 'Deduplicate equal utterances, including line endings');
check($row === $original, 'Preserve source data');
ob_start(); kf_render_card_content($row); $html = ob_get_clean();
check(substr_count($html, '元の発言' . "\n") === 1, 'Render the original utterance once');
check(substr_count($html, '<details ') === 3 && strpos($html, '<details open') === false, 'Reflections start collapsed');
check(strpos($html, '<template') < strpos($html, '第一の振り返り'), 'Keep reflections out of the visible card');
$row['raw_payload'] = '{}';
check(kf_card_view($row)['title'] === '知のタイトル未受信', 'Do not invent a missing title');
$row['raw_payload'] = json_encode(['title' => '']);
check(kf_card_view($row)['title'] === '知のタイトル未設定', 'Distinguish an empty title from a missing key');
$row['raw_payload'] = json_encode(['title' => '  ']);
check(kf_card_view($row)['title'] === '知のタイトル未設定', 'Whitespace-only title is unset');
$row['raw_payload'] = '{invalid';
check(kf_card_view($row)['title'] === '知のタイトル未受信', 'Handle malformed historical payloads');
$row['content'] = '元の発言とは異なる内容';
check(kf_card_view($row)['content'] === $row['content'], 'Retain distinct content');
$row['source_system'] = ''; $row['source_type'] = 'experience';
check(kf_card_view($row)['title'] === $row['content'], 'Preserve legacy KF headings');
$row['raw_payload'] = json_encode(['title' => '<script>alert(1)</script>']);
ob_start(); kf_render_card_content($row); $html = ob_get_clean();
check(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'Escape imported titles');
$row['source_system'] = 'OKDiag'; $row['source_type'] = 'discussion';
$row['content'] = "  完全な本文\n"; $row['title'] = '完全な本文';
$row['raw_payload'] = json_encode(['source_user_ref'=>'okdiag-ai', 'okdiag_provenance'=>[
    'discussion_id'=>'demo', 'evidence_turn_ids'=>['0'], 'generation_id'=>'generation-A',
    'selected_source_turns'=>[['turn_id'=>'0','text'=>'<script>source</script>']],
    'knowledge_trace'=>['condition'=>'A','relations'=>[['ref'=>'node:1','reason'=>'reason']]]
]]);
$row['source_user_ref'] = 'okdiag-ai';
$view = kf_discussion_view($row);
ob_start(); kf_render_discussion_card($view); $html = ob_get_clean();
check(str_contains($html, $row['content']), 'AI body preserves whitespace and content equal to title');
check(str_contains($html,'OKDiag（AI生成）') && str_contains($html,'研究確認用の生成情報'), 'AI identity and secondary metadata');
check(!str_contains($html,'kf-reflection') && !str_contains($html,'<script>'), 'No invented stages and escaped evidence');
check(str_contains($html,'既存組織知 node:1') && str_contains($html,'根拠発話ID: 0'), 'Evidence and reference identifiers');
echo "PASS: legacy human, AI exact body, provenance, missing payload, collapsed details, escaping\n";
