<?php
require_once __DIR__ . '/kf_card_view.php';
require_once __DIR__ . '/okdiag_detail_view.php';

function kf_discussion_view(array $row): ?array
{
    $system = $row['source_system'] ?? '';
    $isAI = $system === 'OKDiag' && ($row['source_user_ref'] ?? '') === 'okdiag-ai';
    if (!$isAI && $system !== 'discussion-kf-system') {
        return null;
    }

    if ($isAI) {
        return [
            'author' => 'OKDiag（AI生成）',
            'title' => (string)($row['title'] ?? ''),
            'evidence' => ['ai' => $row],
        ];
    }

    $card = kf_card_view($row);
    return [
        'author' => (trim($card['author']) !== '' ? $card['author'] : 'ユーザー') . ' さん',
        'title' => $card['title'],
        'evidence' => [
            'body' => (string)($row['content'] ?? ''),
            'selected' => $card['selected'],
            'stages' => $card['stages'],
        ],
    ];
}

function kf_render_discussion_evidence(array $evidence): void
{
    if (isset($evidence['ai'])) {
        okdiag_render_body($evidence['ai']);
        return;
    }

    $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $body = $evidence['body'];
    $selected = $evidence['selected'];
    $normalize = static fn(string $text): string => trim(str_replace(["\r\n", "\r"], "\n", $text));
    ?>
    <?php if ($normalize($body) !== '' && $normalize($body) !== $normalize($selected)) { ?>
    <div class="qa-item">
      <div class="qa-q" data-i18n>KF本文</div>
      <div class="qa-a" style="white-space:pre-wrap;overflow-wrap:anywhere"><?= $escape($body) ?></div>
    </div>
    <?php } ?>
    <?php foreach ($evidence['stages'] as $label => $body) { ?>
    <div class="qa-item"><div class="qa-q" data-i18n>【<?= $escape($label) ?>】</div><div class="qa-a"><?= nl2br($escape($body)) ?></div></div>
    <?php } ?>
    <?php if ($selected !== '') { ?>
    <section class="kf-detail-section">
      <h3 class="kf-detail-section-title">Discussion</h3>
      <div class="selected-utterance"><?= nl2br($escape($selected)) ?></div>
    </section>
    <?php } ?>
    <?php
}

function kf_render_discussion_card(array $view, bool $standalone = false): void
{
    $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($standalone) {
        echo '<div class="card-title">'.$escape($view['title']).'</div><div class="card-meta">'.$escape($view['author']).'</div>';
        kf_render_discussion_evidence($view['evidence']);
        return;
    }
    ?>
    <div class="card-title"><?= $escape($view['author']) ?></div>
    <div class="card-body"><?= $escape($view['title']) ?></div>
    <div class="card-detail" aria-hidden="true"><?php kf_render_discussion_evidence($view['evidence']); ?></div>
    <?php
}
