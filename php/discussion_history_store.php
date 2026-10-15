<?php
declare(strict_types=1);
require_once __DIR__ . '/fragment_references.php';

function ok_core_discussion_stored_refs(array $row): array
{
    $type = (string)($row['fragment_source_type'] ?? '') ?: 'experience';
    try {
        return ok_core_fragment_refs($row['knowledge_fragment_id'] ?? '', $type);
    } catch (Throwable $error) {
        return [];
    }
}

/** Mixed selections use typed CSV in the existing VARCHAR column. */
function ok_core_discussion_storage(array $refs): array
{
    $types = array_keys(array_filter(ok_core_refs_by_type($refs)));
    $type = count($types) > 1 ? 'mixed' : ($types[0] ?? 'experience');
    $ids = $type === 'mixed' ? implode(',', $refs)
        : implode(',', ok_core_refs_by_type($refs)[$type]);
    if (strlen($ids) > 255) throw new InvalidArgumentException('議論対象のKFが多すぎます。選択を減らしてください。');
    return [$ids, $type];
}

/** Fetch posts intersecting this selection; every target must be shared in group. */
function ok_core_discussion_rows(mysqli $db, array $refs, string $group, int $limit = 200): array
{
    if (!$refs) return [];
    $conditions = [];
    $params = [];
    foreach ($refs as $ref) {
        [$type, $id] = explode(':', $ref, 2);
        $legacyType = $type === 'experience'
            ? "(dh.fragment_source_type = 'experience' OR dh.fragment_source_type IS NULL OR dh.fragment_source_type = '')"
            : "dh.fragment_source_type = 'discussion'";
        $conditions[] = "((dh.fragment_source_type = 'mixed' AND FIND_IN_SET(?, dh.knowledge_fragment_id))
            OR ($legacyType AND FIND_IN_SET(?, dh.knowledge_fragment_id)))";
        $params[] = $ref;
        $params[] = $id;
    }
    $sql = "SELECT dh.*, COALESCE(u.name, '') AS user_name FROM discussion_history dh
        LEFT JOIN users u ON u.user_id = dh.user_id WHERE (" . implode(' OR ', $conditions) . ")
        ORDER BY dh.discussion_history_id DESC";
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('discussion_query_prepare_failed');
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    // Stream results to avoid dropping older accessible posts behind inaccessible ones.
    while ($row = $result->fetch_assoc()) {
        $targets = ok_core_discussion_stored_refs($row);
        if (!$targets || !ok_core_group_has_fragment_refs($db, $group, $targets)) continue;
        $row['fragment_refs'] = $targets;
        $rows[] = $row;
        if (count($rows) >= $limit) break;
    }
    $result->free();
    $stmt->close();
    return $rows;
}

function ok_core_insert_discussion(mysqli $db, int $user, string $content, array $refs): array
{
    [$ids, $type] = ok_core_discussion_storage($refs);
    $column = $db->query("SHOW COLUMNS FROM discussion_history LIKE 'knowledge_fragment_id'")->fetch_assoc();
    if (!$column || !preg_match('/varchar\((\d+)\)|text/i', $column['Type'], $match)
        || (isset($match[1]) && strlen($ids) > (int)$match[1])) {
        throw new RuntimeException('discussion_history.knowledge_fragment_id requires VARCHAR(255) or TEXT');
    }
    $pk = $db->query("SHOW COLUMNS FROM discussion_history LIKE 'discussion_history_id'")->fetch_assoc();
    $auto = stripos((string)($pk['Extra'] ?? ''), 'auto_increment') !== false;
    $lock = null;
    try {
        if (!$auto) {
            $lock = $db->query("SELECT CONCAT(DATABASE(), ':discussion_history_id')")->fetch_row()[0];
            $stmt = $db->prepare('SELECT GET_LOCK(?, 5)');
            $stmt->bind_param('s', $lock);
            $stmt->execute();
            $locked = (int)$stmt->get_result()->fetch_row()[0] === 1;
            $stmt->close();
            if (!$locked) throw new RuntimeException('discussion_id_lock_failed');
            $next = max(55555, (int)$db->query('SELECT COALESCE(MAX(discussion_history_id), 0) + 1 FROM discussion_history')->fetch_row()[0]);
        }
        $sql = 'INSERT INTO discussion_history (user_id, content, knowledge_fragment_id, fragment_source_type'
            . ($auto ? '' : ', discussion_history_id') . ') VALUES (?, ?, ?, ?' . ($auto ? '' : ', ?') . ')';
        $stmt = $db->prepare($sql);
        if (!$stmt) throw new RuntimeException('discussion_insert_prepare_failed');
        if ($auto) $stmt->bind_param('isss', $user, $content, $ids, $type);
        else $stmt->bind_param('isssi', $user, $content, $ids, $type, $next);
        if (!$stmt->execute()) throw new RuntimeException('discussion_insert_failed');
        $id = $auto ? (int)$db->insert_id : $next;
        $stmt->close();
        $stmt = $db->prepare("SELECT dh.*, COALESCE(u.name, '') AS user_name FROM discussion_history dh
            LEFT JOIN users u ON u.user_id = dh.user_id WHERE discussion_history_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $row['fragment_refs'] = $refs;
        return $row;
    } finally {
        if ($lock !== null) {
            $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->bind_param('s', $lock);
            $stmt->execute();
            $stmt->close();
        }
    }
}
