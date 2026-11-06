<?php
declare(strict_types=1);

/** Append-only backup; callers own the surrounding transaction and parent-row lock. */
final class OrganizationalHistoryStorageException extends RuntimeException {}

function ok_core_history_statement(mysqli $db, string $sql, array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('history_prepare_failed');
    if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('history_query_failed');
    }
    return $stmt;
}

/** Identifiers come only from this module's fixed allowlist, never from request SQL. */
function ok_core_history_table_exists(mysqli $db, string $table): bool
{
    $stmt = ok_core_history_statement($db,
        'SELECT TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
    $meta = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$meta) return false;
    if ($meta['TABLE_TYPE'] !== 'BASE TABLE' || strcasecmp((string)$meta['ENGINE'], 'InnoDB') !== 0) {
        throw new OrganizationalHistoryStorageException('履歴保存には対象テーブルのInnoDB構造が必要です。管理者に確認してください。');
    }
    return true;
}

function ok_core_require_organizational_history_storage(mysqli $db): void
{
    if (!ok_core_history_table_exists($db, 'organizational_knowledge_histories')) {
        throw new OrganizationalHistoryStorageException('組織知の履歴テーブルが未作成です。2026-11-06_create_organizational_knowledge_histories.sqlをOK-Core DBに適用してください。');
    }
    $stmt = ok_core_history_statement($db,
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'organizational_knowledge_histories' AND COLUMN_NAME IN ('created_at', 'updated_at')");
    $columns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if (count($columns) !== 2) {
        throw new OrganizationalHistoryStorageException('履歴の日時カラムが未適用です。2026-11-06_add_organizational_history_source_timestamps.sqlをOK-Core DBに適用してください。');
    }
}

function ok_core_history_source(string $source): array
{
    return match ($source) {
        'knowledge_explorer' => ['knowledge_node_id', 'knowledge_explorer_fragment_links', 'knowledge_node_id', 'id'],
        'organizational_knowledges' => ['organizational_knowledge_id', 'organizational_knowledge_fragment_links', 'organizational_knowledge_id', 'fragment_link_id'],
        default => throw new InvalidArgumentException('保存元はorganizational_knowledgesまたはknowledge_explorerを指定してください。'),
    };
}

/** Optional tables use null to distinguish "not installed" from an empty relation. */
function ok_core_history_related(mysqli $db, string $table, string $foreignKey, string $id, string $order): ?array
{
    if (!ok_core_history_table_exists($db, $table)) return null;
    $stmt = ok_core_history_statement($db, "SELECT * FROM `$table` WHERE `$foreignKey` = ? ORDER BY `$order`", [$id]);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Capture the full persisted row and relations; never accept a client-supplied snapshot. */
function ok_core_organizational_history_snapshot(mysqli $db, string $source, string $id, int $group): array
{
    [$primary, $links, $foreign, $order] = ok_core_history_source($source);
    if (!preg_match('/^[1-9][0-9]{0,19}$/D', $id)
        || (strlen($id) === 20 && strcmp($id, '18446744073709551615') > 0) || $group <= 0) {
        throw new InvalidArgumentException('組織知IDまたはグループIDが不正です。');
    }
    if (!ok_core_history_table_exists($db, $source)) {
        throw new OrganizationalHistoryStorageException('指定した保存元テーブルがありません。現行画面の保存元はknowledge_explorerです。');
    }
    $stmt = ok_core_history_statement($db, "SELECT * FROM `$source` WHERE `$primary` = ?"
        . ($source === 'knowledge_explorer' ? ' AND knowledge_group_id = ?' : '') . ' FOR UPDATE',
        $source === 'knowledge_explorer' ? [$id, (string)$group] : [$id]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new DomainException('選択グループに組織知がありません。');

    $category = null;
    if ($source === 'organizational_knowledges') {
        if (!ok_core_history_table_exists($db, 'organizational_knowledge_categories')) {
            throw new OrganizationalHistoryStorageException('組織知のカテゴリテーブルがありません。');
        }
        $stmt = ok_core_history_statement($db, 'SELECT * FROM organizational_knowledge_categories WHERE category_id = ? AND knowledge_group_id = ?', [(string)$row['category_id'], (string)$group]);
        $category = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$category) throw new DomainException('選択グループに組織知がありません。');
    }
    return [
        'schema_version' => 1,
        'source_table' => $source,
        'knowledge' => $row,
        'category' => $category,
        'fragment_links' => ok_core_history_related($db, $links, $foreign, $id, $order),
        'discussion_posts' => $source === 'organizational_knowledges'
            ? ok_core_history_related($db, 'organizational_knowledge_discussion_posts', 'organizational_knowledge_id', $id, 'discussion_post_id') : null,
    ];
}

/** Appends only. No UPDATE/DELETE of histories, no writes to the original record. */
function ok_core_save_organizational_knowledge_history(mysqli $db, string $source, string $id,
    int $group, int $actor, string $event, string $operation): string
{
    if ($actor <= 0 || !preg_match('/^[a-f0-9]{32}$/D', $operation)
        || !in_array($event, ['created', 'updated', 'before_delete', 'deleted',
            'before_rename', 'renamed', 'before_reorder', 'reordered', 'manual'], true)) {
        throw new InvalidArgumentException('履歴の保存条件が不正です。');
    }
    ok_core_require_organizational_history_storage($db);
    $snapshot = ok_core_organizational_history_snapshot($db, $source, $id, $group);
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $hash = hash('sha256', $json);
    // Preserve the dates of the captured version; saved_at is the separate backup time.
    $createdAt = $snapshot['knowledge']['created_at'] ?? null;
    $updatedAt = $snapshot['knowledge']['updated_at'] ?? null;
    $savedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i:s.v');
    $stmt = ok_core_history_statement($db, 'INSERT INTO organizational_knowledge_histories
        (source_table, organizational_knowledge_id, knowledge_group_id, event_type, operation_id,
         snapshot_version, snapshot_json, snapshot_sha256, saved_by_user_id, created_at, updated_at, saved_at)
        VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)',
        [$source, $id, (string)$group, $event, $operation, $json, $hash, (string)$actor, $createdAt, $updatedAt, $savedAt]);
    $historyId = (string)$db->insert_id;
    $stmt->close();
    return $historyId;
}
