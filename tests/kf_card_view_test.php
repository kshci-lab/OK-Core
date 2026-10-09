<?php
require __DIR__ . '/../php/kf_card_view.php';
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
echo "PASS: title, missing/malformed payload, deduplication, preservation, collapsed details, legacy KF, escaping\n";
