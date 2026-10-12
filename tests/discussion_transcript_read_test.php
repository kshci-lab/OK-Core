<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../php/discussion_transcript_reader.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$config = discussion_transcript_config();
$sourceId = discussion_transcript_source_id([
    'source_system' => $config['source_system'],
    'source_id' => 'knowledge_fragment_' . ($argv[1] ?? '9'),
], $config);
$db = discussion_transcript_connect($config);
try {
    $initial = discussion_transcript_read($db, $sourceId);
    check(count(array_filter($initial['utterances'], fn($row) => $row['is_linked'])) > 0,
        'Initial page must contain a linked utterance.');
    $exports = discussion_transcript_rows($db,
        "SELECT ok_core_externalized_contents_id, target_group_id FROM kf_exports
          WHERE knowledge_fragment_id = ? AND target_system = 'OK-Core' AND export_status = 'SUCCESS'
            AND ok_core_externalized_contents_id IS NOT NULL LIMIT 1", [$sourceId]);
    if ($exports) {
        discussion_transcript_read($db, $sourceId, null, null, 200, [
            'fragment_id' => $exports[0]['ok_core_externalized_contents_id'],
            'group_id' => $exports[0]['target_group_id'] ?: '1',
        ]);
    }
    try {
        discussion_transcript_read($db, $sourceId, null, null, 200,
            ['fragment_id' => '__unrelated_ok_core_fragment__', 'group_id' => '1']);
        throw new RuntimeException('An unverified OK-Core fragment must be rejected.');
    } catch (DiscussionTranscriptException $error) {
        check($error->errorCode === 'DISCUSSION_EXPORT_NOT_VERIFIED', 'Unexpected export identity error.');
    }
    foreach ($initial['sessions'] as $session) {
        $ids = [];
        $lastTime = '';
        $lastId = '';
        $linkedCount = 0;
        // Small pages also exercise pagination for a short session.
        for ($offset = 0, $total = 1; $offset < $total; $offset += 50) {
            $result = discussion_transcript_read($db, $sourceId, $session['session_id'], $offset, 50);
            $total = $result['page']['total'];
            foreach ($result['utterances'] as $row) {
                $id = $row['discussion_utterance_id'];
                check(!isset($ids[$id]), 'Duplicate utterance across pages.');
                $time = (string)$row['uttered_at'];
                check($time >= $lastTime, 'Utterances must be chronological.');
                if ($time === $lastTime) check((int)$id > (int)$lastId, 'Equal timestamps must use stable ID order.');
                $ids[$id] = true;
                $lastTime = $time;
                $lastId = $id;
                if ($row['is_linked']) $linkedCount++;
            }
        }
        check(count($ids) === $total, 'Pagination must cover the complete session.');
        check($linkedCount === $session['linked_count'], 'All linked utterances must be highlighted.');
    }
    try {
        discussion_transcript_read($db, $sourceId, '__unrelated_test_session__');
        throw new RuntimeException('An unrelated session must be rejected.');
    } catch (DiscussionTranscriptException $error) {
        check($error->httpStatus === 403, 'An unrelated session must return 403.');
    }
    foreach (['', 'unrelated_9', 'knowledge_fragment_0'] as $invalidId) {
        try {
            discussion_transcript_source_id(['source_system' => $config['source_system'], 'source_id' => $invalidId], $config);
            throw new RuntimeException('An invalid source reference must be rejected.');
        } catch (DiscussionTranscriptException $error) {
            check($error->errorCode === 'DISCUSSION_SOURCE_REFERENCE_MISSING', 'Unexpected invalid-reference error.');
        }
    }
    try {
        discussion_transcript_source_id(['source_system' => '__other_producer__', 'source_id' => '9'], $config);
        throw new RuntimeException('An unrelated producer must be rejected.');
    } catch (DiscussionTranscriptException $error) {
        check($error->errorCode === 'DISCUSSION_SOURCE_UNSUPPORTED', 'Unexpected source error.');
    }
    echo "PASS: focus, complete pagination, stable ordering, linked highlights, export identity, session and source boundaries.\n";
} finally {
    $db->close();
}
