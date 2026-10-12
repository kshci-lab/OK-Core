<?php

declare(strict_types=1);

/** Temporary, read-only adapter for the discussion producer's existing schema. */
class DiscussionTranscriptException extends RuntimeException
{
    public string $errorCode;
    public int $httpStatus;

    public function __construct(string $code, string $message, int $status = 404)
    {
        parent::__construct($message);
        $this->errorCode = $code;
        $this->httpStatus = $status;
    }
}

function discussion_transcript_config(): array
{
    $config = ['host' => 'localhost', 'port' => 3306, 'database' => 'hci_knowledge_db',
        'user' => '', 'password' => '', 'source_system' => 'discussion-kf-system'];
    $path = __DIR__ . '/discussion_transcript_local.php';
    if (is_file($path)) {
        $local = require $path;
        if (is_array($local)) $config = array_replace($config, $local);
    }
    foreach (['host' => 'HOST', 'port' => 'PORT', 'database' => 'NAME',
        'user' => 'USER', 'password' => 'PASSWORD', 'source_system' => 'SOURCE_SYSTEM'] as $key => $suffix) {
        $value = getenv('DISCUSSION_TRANSCRIPT_DB_' . $suffix);
        if ($value !== false) $config[$key] = $value;
    }
    if (trim((string)$config['user']) === '' || trim((string)$config['database']) === ''
        || trim((string)$config['source_system']) === '') {
        throw new DiscussionTranscriptException('DISCUSSION_DB_NOT_CONFIGURED',
            '議論の文字起こしDBへの接続設定がありません。', 503);
    }
    return $config;
}

function discussion_transcript_connect(array $config): mysqli
{
    try {
        $db = mysqli_init();
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) $db->options(MYSQLI_OPT_READ_TIMEOUT, 15);
        if (!@$db->real_connect((string)$config['host'], (string)$config['user'],
            (string)$config['password'], (string)$config['database'], (int)$config['port'])) {
            throw new RuntimeException('connection_failed');
        }
        $db->set_charset('utf8mb4');
        return $db;
    } catch (Throwable $error) {
        // Never return connection details or credentials to the browser.
        throw new DiscussionTranscriptException('DISCUSSION_DB_UNAVAILABLE',
            '議論の文字起こしDBに接続できません。', 503);
    }
}

function discussion_transcript_rows(mysqli $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('transcript_prepare_failed');
    try {
        if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        if (!$stmt->execute()) throw new RuntimeException('transcript_query_failed');
        $result = $stmt->get_result();
        if (!$result) throw new RuntimeException('transcript_result_failed');
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        return $rows;
    } finally {
        $stmt->close();
    }
}

/** Use the producer ID saved on import, never the unrelated OK-Core row ID. */
function discussion_transcript_source_id(array $fragment, array $config): string
{
    if ((string)($fragment['source_system'] ?? '') !== (string)$config['source_system']) {
        throw new DiscussionTranscriptException('DISCUSSION_SOURCE_UNSUPPORTED',
            'このKFの産出元は文字起こしの参照対象に設定されていません。');
    }
    $sourceId = trim((string)($fragment['source_id'] ?? ''));
    if (!preg_match('/^(?:knowledge_fragment_)?([1-9][0-9]{0,19})$/D', $sourceId, $matches)) {
        throw new DiscussionTranscriptException('DISCUSSION_SOURCE_REFERENCE_MISSING',
            'このKFには産出元KFの参照IDがありません。');
    }
    return $matches[1];
}

/** All queries run in a READ ONLY transaction; neither database is modified. */
function discussion_transcript_read(mysqli $db, string $sourceId, ?string $sessionId = null,
    ?int $offset = null, int $limit = 200, ?array $exportReference = null): array
{
    $limit = max(1, min(200, $limit));
    if (!$db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY)) {
        throw new RuntimeException('transcript_read_transaction_failed');
    }
    try {
        if ($exportReference !== null) {
            // Confirm the producer actually exported this KF to this OK-Core row.
            // Older exports did not record a group; OK-Core's sharing gate still applies.
            $exports = discussion_transcript_rows($db,
                "SELECT kf_export_id FROM kf_exports
                  WHERE knowledge_fragment_id = ? AND target_system = 'OK-Core'
                    AND export_status = 'SUCCESS' AND ok_core_externalized_contents_id = ?
                    AND (target_group_id IS NULL OR target_group_id = '' OR target_group_id = ?)
                  LIMIT 1", [$sourceId, $exportReference['fragment_id'], $exportReference['group_id']]);
            if (!$exports) {
                throw new DiscussionTranscriptException('DISCUSSION_EXPORT_NOT_VERIFIED',
                    '産出元の送信履歴とこのKFを照合できません。KFの参照IDと送信履歴を確認してください。');
            }
        }
        $fragments = discussion_transcript_rows($db,
            'SELECT knowledge_fragment_id, externalized_knowledge_id, title
               FROM knowledge_fragments WHERE knowledge_fragment_id = ? AND deleted = 0', [$sourceId]);
        if (!$fragments) {
            throw new DiscussionTranscriptException('DISCUSSION_FRAGMENT_NOT_FOUND',
                '産出元のKFが見つかりません。');
        }
        $fragment = $fragments[0];
        $externalizedId = (string)($fragment['externalized_knowledge_id'] ?? '0');
        $time = 'COALESCE(u.uttered_at, IF(u.utter_epoch_time > 0, FROM_UNIXTIME(u.utter_epoch_time), NULL), u.created_at)';
        // New multi-utterance links and the two existing single-utterance paths.
        $linked = discussion_transcript_rows($db,
            "SELECT u.discussion_utterance_id, u.session_id, $time AS timeline_at,
                    s.session_title, s.source_name, s.started_at, s.ended_at
               FROM discussion_utterances u
               INNER JOIN (
                    SELECT discussion_utterance_id FROM knowledge_fragment_utterance_links
                     WHERE knowledge_fragment_id = ?
                    UNION
                    SELECT r.discussion_utterance_id FROM knowledge_fragments k
                     INNER JOIN remarked_utterances r ON r.remarked_utterance_id = k.remarked_utterance_id
                     WHERE k.knowledge_fragment_id = ? AND r.deleted = 0
                    UNION
                    SELECT e.discussion_utterance_id FROM externalized_knowledge e
                     WHERE (e.id = ? OR e.knowledge_fragment_id = ?) AND e.deleted = 0
                    UNION
                    SELECT r.discussion_utterance_id FROM externalized_knowledge e
                     INNER JOIN remarked_utterances r ON r.remarked_utterance_id = e.remarked_utterance_id
                     WHERE (e.id = ? OR e.knowledge_fragment_id = ?) AND e.deleted = 0 AND r.deleted = 0
               ) linked ON linked.discussion_utterance_id = u.discussion_utterance_id
               LEFT JOIN discussion_sessions s ON s.session_id = u.session_id
              WHERE u.deleted = 0 AND (s.discussion_session_id IS NULL OR s.deleted = 0)
              ORDER BY $time, u.discussion_utterance_id LIMIT 1001",
            [$sourceId, $sourceId, $externalizedId, $sourceId, $externalizedId, $sourceId]);
        if (!$linked) {
            throw new DiscussionTranscriptException('DISCUSSION_UTTERANCE_NOT_LINKED',
                'このKFに紐づく発言が見つかりません。');
        }
        if (count($linked) > 1000) throw new RuntimeException('too_many_linked_utterances');
        $sessions = [];
        $linkedIds = [];
        $unassignedCount = 0;
        foreach ($linked as $row) {
            $id = (string)($row['session_id'] ?? '');
            if ($id === '') { $unassignedCount++; continue; }
            $key = 'session:' . $id;
            if (!isset($sessions[$key])) {
                $sessions[$key] = ['session_id' => $id,
                    'title' => trim((string)$row['session_title']) ?: (trim((string)$row['source_name']) ?: 'セッション ' . $id),
                    'started_at' => $row['started_at'], 'ended_at' => $row['ended_at'], 'linked_count' => 0];
            }
            $sessions[$key]['linked_count']++;
            $linkedIds[(string)$row['discussion_utterance_id']] = true;
        }
        if (!$sessions) {
            throw new DiscussionTranscriptException('DISCUSSION_SESSION_MISSING',
                '紐づく発言にsession_idが登録されていないため、セッションを特定できません。');
        }
        if ($sessionId === null) $sessionId = (string)reset($sessions)['session_id'];
        if (!isset($sessions['session:' . $sessionId])) {
            throw new DiscussionTranscriptException('DISCUSSION_SESSION_NOT_LINKED',
                '指定されたセッションはこのKFに紐づいていません。', 403);
        }
        $focus = null;
        foreach ($linked as $row) {
            if ((string)$row['session_id'] === $sessionId) { $focus = $row; break; }
        }
        $count = discussion_transcript_rows($db,
            'SELECT COUNT(*) AS total FROM discussion_utterances WHERE session_id = ? AND deleted = 0', [$sessionId]);
        $total = (int)$count[0]['total'];
        if ($offset === null) {
            $before = discussion_transcript_rows($db,
                "SELECT COUNT(*) AS total FROM discussion_utterances u
                  WHERE u.session_id = ? AND u.deleted = 0
                    AND ($time < ? OR ($time = ? AND u.discussion_utterance_id < ?))",
                [$sessionId, $focus['timeline_at'], $focus['timeline_at'], $focus['discussion_utterance_id']]);
            $offset = intdiv((int)$before[0]['total'], $limit) * $limit;
        }
        $offset = min(max(0, $offset), max(0, (int)(ceil($total / $limit) - 1) * $limit));
        $utterances = discussion_transcript_rows($db,
            "SELECT u.discussion_utterance_id, u.utterance_text, $time AS uttered_at,
                    COALESCE(NULLIF(u.speaker_name, ''), NULLIF(p.display_name, ''),
                             NULLIF(m.display_name, ''), '話者不明') AS speaker_name
               FROM discussion_utterances u
               LEFT JOIN discussion_participants p ON p.room_id = u.room_id AND p.user_id = u.user_id
               LEFT JOIN shimaoka_user_mappings m ON m.shimaoka_user_id = u.user_id
              WHERE u.session_id = ? AND u.deleted = 0
              ORDER BY $time, u.discussion_utterance_id LIMIT $limit OFFSET $offset", [$sessionId]);
        foreach ($utterances as &$row) {
            $row['discussion_utterance_id'] = (string)$row['discussion_utterance_id'];
            $row['is_linked'] = isset($linkedIds[$row['discussion_utterance_id']]);
        }
        unset($row);
        return ['source_kf_id' => $sourceId, 'title' => (string)$fragment['title'],
            'sessions' => array_values($sessions), 'session_id' => $sessionId,
            'utterances' => $utterances, 'unassigned_link_count' => $unassignedCount,
            'page' => ['offset' => $offset, 'limit' => $limit, 'total' => $total,
                'has_previous' => $offset > 0, 'has_next' => $offset + count($utterances) < $total]];
    } finally {
        $db->rollback();
    }
}
