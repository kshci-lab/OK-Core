<?php
declare(strict_types=1);
require_once __DIR__ . '/organizational_knowledge_history_store.php';

/** Group-scoped rename/delete/root reorder, including both sides of each change. */
function ok_core_history_node_action(mysqli $db, string $action, int $nodeId, int $group, int $actor, string $value = ''): array
{
    if ($nodeId <= 0 || $group <= 0 || $actor <= 0) throw new InvalidArgumentException('node_id が不正です');
    if (!in_array($action, ['rename', 'delete', 'reorder'], true)) throw new InvalidArgumentException('操作が不正です');
    if ($action === 'rename' && ($value === '' || preg_match_all('/./us', $value) > 255)) {
        throw new InvalidArgumentException('タイトルは1～255文字で入力してください');
    }
    if ($action === 'reorder' && !in_array($value, ['up', 'down'], true)) throw new InvalidArgumentException('dir が不正です');
    $operation = bin2hex(random_bytes(16));
    $db->begin_transaction();
    try {
        ok_core_require_organizational_history_storage($db);
        if ($action === 'reorder') {
            // Lock all active roots in a stable order. Initialization and swap are atomic.
            $stmt = ok_core_history_statement($db, 'SELECT * FROM knowledge_explorer WHERE knowledge_group_id = ? AND parent_node_id IS NULL AND deleted = 0 ORDER BY knowledge_node_id FOR UPDATE', [(string)$group]);
            $roots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $target = array_search($nodeId, array_map('intval', array_column($roots, 'knowledge_node_id')), true);
            if ($target === false) throw new DomainException('選択グループに対象ルートがありません');
            $initialize = in_array(null, array_column($roots, 'sort_order'), true);
            $ordered = $roots;
            if (!$initialize) usort($ordered, static fn(array $a, array $b): int => ((int)$a['sort_order'] <=> (int)$b['sort_order']) ?: ((int)$a['knowledge_node_id'] <=> (int)$b['knowledge_node_id']));
            $position = array_search($nodeId, array_map('intval', array_column($ordered, 'knowledge_node_id')), true);
            $adjacent = $ordered[$position + ($value === 'up' ? -1 : 1)] ?? null;
            $changes = [];
            if ($initialize) foreach ($ordered as $index => $row) $changes[(int)$row['knowledge_node_id']] = $index + 1;
            if ($adjacent) {
                $adjacentId = (int)$adjacent['knowledge_node_id'];
                $changes[$nodeId] = $initialize ? $position + ($value === 'up' ? 0 : 2) : (int)$adjacent['sort_order'];
                $changes[$adjacentId] = $initialize ? $position + 1 : (int)$ordered[$position]['sort_order'];
                // Existing duplicate sort values still need a deterministic visible move.
                if (!$initialize && $changes[$nodeId] === $changes[$adjacentId]) {
                    foreach ($ordered as $index => $row) $changes[(int)$row['knowledge_node_id']] = $index + 1;
                    $changes[$nodeId] = $position + ($value === 'up' ? 0 : 2);
                    $changes[$adjacentId] = $position + 1;
                }
            }
            foreach ($changes as $id => $sort) {
                ok_core_save_organizational_knowledge_history($db, 'knowledge_explorer', (string)$id, $group, $actor, 'before_reorder', $operation);
                $stmt = ok_core_history_statement($db, 'UPDATE knowledge_explorer SET sort_order = ?, updated_by = ?, updated_at = NOW() WHERE knowledge_node_id = ? AND knowledge_group_id = ?', [(string)$sort, (string)$actor, (string)$id, (string)$group]);
                $stmt->close();
                ok_core_save_organizational_knowledge_history($db, 'knowledge_explorer', (string)$id, $group, $actor, 'reordered', $operation);
            }
            $db->commit();
            return ['node_id'=>$nodeId, 'swapped_with'=>$adjacent ? (int)$adjacent['knowledge_node_id'] : null, 'message'=>$adjacent ? '並べ替えました' : 'edge'];
        }

        $snapshot = ok_core_organizational_history_snapshot($db, 'knowledge_explorer', (string)$nodeId, $group);
        if ((int)$snapshot['knowledge']['deleted'] !== 0) throw new DomainException('選択グループに有効な組織知がありません');
        $before = $action === 'delete' ? 'before_delete' : 'before_rename';
        $after = $action === 'delete' ? 'deleted' : 'renamed';
        ok_core_save_organizational_knowledge_history($db, 'knowledge_explorer', (string)$nodeId, $group, $actor, $before, $operation);
        $stmt = ok_core_history_statement($db,
            'UPDATE knowledge_explorer SET ' . ($action === 'delete' ? 'deleted = 1' : 'node_title = ?')
            . ', updated_by = ?, updated_at = NOW() WHERE knowledge_node_id = ? AND knowledge_group_id = ? AND deleted = 0',
            $action === 'delete' ? [(string)$actor, (string)$nodeId, (string)$group] : [$value, (string)$actor, (string)$nodeId, (string)$group]);
        $affected = $stmt->affected_rows;
        $stmt->close();
        $historyId = ok_core_save_organizational_knowledge_history($db, 'knowledge_explorer', (string)$nodeId, $group, $actor, $after, $operation);
        $db->commit();
        return ['node_id'=>$nodeId, 'node_title'=>$action === 'rename' ? $value : $snapshot['knowledge']['node_title'], 'affected_rows'=>$affected, 'history_id'=>$historyId];
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}
