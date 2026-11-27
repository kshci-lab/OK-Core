<?php
function kf_render_ai_evidence(array $p): void
{
    $h = static fn($v) => htmlspecialchars(is_scalar($v) ? (string)$v : '未記録', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<section class="kf-detail-section"><h3 class="kf-detail-section-title">Discussion</h3><div>議論ID: '.$h($p['discussion_id'] ?? '未記録').'</div>';
    $ids = $p['evidence_turn_ids'] ?? $p['source_turn_ids'] ?? [];
    if (is_array($ids) && $ids) echo '<div class="kf-detail-text">根拠発話ID: '.implode(', ', array_map($h, $ids)).'</div>';
    echo '<details><summary>根拠発話の原文を読む</summary>';
    foreach ((array)($p['selected_source_turns'] ?? []) as $t) {
        if (is_array($t)) echo '<div class="kf-detail-text"><strong>'.$h($t['turn_id'] ?? '未記録').'</strong><br>'.$h($t['text'] ?? '未記録').'</div>';
    }
    if (empty($p['selected_source_turns'])) echo '<div>未記録</div>';
    echo '</details></section><section><h3>参照知識（組織知への採用を意味しません）</h3>';
    foreach ((array)($p['knowledge_trace']['relations'] ?? []) as $rel) {
        if (!is_array($rel)) continue;
        $ref = (string)($rel['ref'] ?? '');
        echo '<details><summary>'.$h(str_starts_with($ref, 'node:') ? '既存組織知 '.$ref : '既存KF '.$ref).'</summary>';
        foreach (['reason'=>'参照理由','common'=>'共通点','new_point'=>'新しい点','uncertain'=>'不確実な点'] as $key=>$label) echo '<div class="kf-detail-text">'.$label.': '.$h($rel[$key] ?? '未記録').'</div>';
        echo '</details>';
    }
    if (empty($p['knowledge_trace']['relations'])) echo '<div>未記録</div>';
    echo '</section><details><summary>研究確認用の生成情報</summary>';
    foreach (['generation_id'=>'生成ID','model'=>'モデル','generated_at'=>'生成時刻'] as $key=>$label) echo '<div class="kf-detail-text">'.$label.': '.$h($p[$key] ?? '未記録').'</div>';
    echo '<div>生成条件: '.$h($p['knowledge_trace']['condition'] ?? '未記録').'</div></details>';
}


function okdiag_render_body(array $row): void
{
    $payload = json_decode((string)$row['raw_payload'], true);
    echo '<div class="qa-item"><div class="qa-q">KF本文（AI生成）</div><div class="qa-a" style="white-space:pre-wrap;overflow-wrap:anywhere">'.htmlspecialchars($row['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</div></div>';
    kf_render_ai_evidence((array)($payload['okdiag_provenance'] ?? []));
}
