<?php
// Presentation only: never rewrite the imported payload or source text.
function kf_card_payload_columns(mysqli $db): string
{
    $columns = [];
    $result = $db->query('SHOW COLUMNS FROM externalized_contents');
    if ($result) {
        while ($column = $result->fetch_assoc()) { $columns[] = $column['Field']; }
        $result->free();
    }
    return implode(', ', array_map(function ($name) use ($columns) {
        return in_array($name, $columns, true) ? 'ec.`'.$name.'`' : 'NULL AS `'.$name.'`';
    }, ['title', 'raw_payload', 'source_system']));
}

function kf_card_view(array $row): array
{
    $payload = json_decode((string)($row['raw_payload'] ?? ''), true);
    $payload = is_array($payload) ? $payload : [];
    $titleReceived = array_key_exists('title', $row) || array_key_exists('title', $payload);
    $title = trim((string)($row['title'] ?? ''));
    if ($title === '' && is_string($payload['title'] ?? null)) {
        $title = trim($payload['title']);
    }
    $content = trim((string)($row['content'] ?? ''));
    $selected = trim((string)($row['selected_contents'] ?? ''));
    $imported = ($row['source_system'] ?? $payload['source_system'] ?? '') === 'discussion-kf-system';
    if ($title === '') {
        $title = $imported
            ? ($titleReceived ? '知のタイトル未設定' : '知のタイトル未受信')
            : ($content !== '' ? $content : 'タイトルなし');
    }
    $normalize = function ($text) { return trim(str_replace(["\r\n", "\r"], "\n", $text)); };
    return [
        'title' => $title,
        'content' => $normalize($content) !== $normalize($selected) && $normalize($content) !== $normalize($title) ? $content : '',
        'selected' => $selected,
        'author' => (string)($row['user_name'] ?? 'ユーザー'),
        'kind' => ($row['source_type'] ?? '') === 'experience' ? '経験KF' : (($row['source_type'] ?? '') === 'SRL' ? 'SRL KF' : '議論KF'),
        'stages' => [
            ($row['source_type'] ?? '') === 'experience' ? '経験の振り返り' : '議論の振り返り' => (string)($row['stage1'] ?? ''),
            '活動文脈固有の振り返り' => (string)($row['stage2'] ?? ''),
            '研究固有の振り返り' => (string)($row['stage3'] ?? ''),
        ],
    ];
}

function kf_render_card_content(array $row): void
{
    $view = kf_card_view($row);
    $escape = function ($text) { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
    ?>
    <h3 class="card-title"><?php echo $escape($view['title']); ?></h3>
    <div class="card-meta"><span class="card-author"><?php echo $escape($view['author']); ?> さん</span><span class="card-kind"><?php echo $escape($view['kind']); ?></span></div>
    <template class="kf-detail-template">
      <?php if ($view['content'] !== '') { ?>
        <section><h3>KFの内容</h3><div class="kf-detail-text"><?php echo $escape($view['content']); ?></div></section>
      <?php } ?>
      <?php if ($view['selected'] !== '') { ?>
        <section><h3>元の発言・経験</h3><div class="kf-detail-text"><?php echo $escape($view['selected']); ?></div></section>
      <?php } ?>
      <?php foreach ($view['stages'] as $label => $body) { ?>
        <details class="kf-reflection"><summary><?php echo $escape($label); ?></summary><div class="kf-detail-text"><?php echo $escape(trim($body) !== '' ? $body : '未入力'); ?></div></details>
      <?php } ?>
    </template>
    <div class="card-actions"><button type="button" class="detail-button" aria-controls="kf-detail-panel" aria-expanded="false">詳細を見る</button></div>
    <?php
}
