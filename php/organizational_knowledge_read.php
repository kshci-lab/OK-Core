<?php

function ok_read_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ok_read_table_columns(mysqli $db, string $table): array
{
    $allowed = ['knowledge_explorer', 'knowledge_explorer_fragment_links', 'experience_knowledges', 'externalized_contents', 'knowledge_groups', 'kgroup_user_link'];
    if (!in_array($table, $allowed, true)) { return []; }
    $result = $db->query("SHOW COLUMNS FROM `{$table}`");
    if (!$result) { return []; }
    $columns = [];
    while ($row = $result->fetch_assoc()) { $columns[$row['Field']] = true; }
    $result->free();
    return $columns;
}

function ok_read_group_access(mysqli $db, int $userId, int $groupId): bool
{
    $sql = 'SELECT 1
              FROM kgroup_user_link link_row
              INNER JOIN knowledge_groups group_row ON group_row.group_id = link_row.group_id
             WHERE link_row.user_id = ?
               AND link_row.group_id = ?
               AND link_row.deleted = 0
               AND group_row.deleted = 0
             LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) { return false; }
    $stmt->bind_param('ii', $userId, $groupId);
    $allowed = $stmt->execute() && $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $allowed;
}

function ok_read_fragments(mysqli $db, array $links): array
{
    $requested = [];
    foreach ($links as $link) {
        $type = $link['source_type'];
        $id = $link['source_id'];
        $requested[$type][$id] = true;
    }
    $found = [];
    if (!empty($requested['experience'])) {
        $ids = array_keys($requested['experience']);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT experience_knowledge_id AS source_id, knowledge_fragment_content AS content, selected_contents, stage1, stage2, stage3, user_id, discussed
                                FROM experience_knowledges
                               WHERE deleted = 0 AND experience_knowledge_id IN ({$marks})");
        if ($stmt) {
            $types = str_repeat('i', count($ids)); $bind = [$types];
            foreach ($ids as $i => $id) { $bind[] = &$ids[$i]; }
            call_user_func_array([$stmt, 'bind_param'], $bind);
            if ($stmt->execute() && ($result = $stmt->get_result())) {
                while ($row = $result->fetch_assoc()) { $found['experience'][(int)$row['source_id']] = $row; }
                $result->free();
            }
            $stmt->close();
        }
    }
    if (!empty($requested['discussion'])) {
        $ids = array_keys($requested['discussion']);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT externalized_contents_id AS source_id, knowledge_fragment_content AS content, selected_contents, stage1, stage2, stage3, user_id, discussed, raw_payload, source_system, source_type, source_id AS source_external_id
                                FROM externalized_contents
                               WHERE deleted = 0 AND externalized_contents_id IN ({$marks})");
        if ($stmt) {
            $types = str_repeat('i', count($ids)); $bind = [$types];
            foreach ($ids as $i => $id) { $bind[] = &$ids[$i]; }
            call_user_func_array([$stmt, 'bind_param'], $bind);
            if ($stmt->execute() && ($result = $stmt->get_result())) {
                while ($row = $result->fetch_assoc()) {
                    $payload = json_decode((string)($row['raw_payload'] ?? ''), true);
                    $row['title'] = is_array($payload) && array_key_exists('title', $payload) ? $payload['title'] : null;
                    unset($row['raw_payload']);
                    $found['discussion'][(int)$row['source_id']] = $row;
                }
                $result->free();
            }
            $stmt->close();
        }
    }
    return $found;
}

function ok_read_organizational_knowledge(mysqli $db, int $groupId): array
{
    $nodeColumns = ok_read_table_columns($db, 'knowledge_explorer');
    $linkColumns = ok_read_table_columns($db, 'knowledge_explorer_fragment_links');
    $required = ['knowledge_node_id', 'parent_node_id', 'node_title', 'knowledge_group_id', 'deleted'];
    foreach ($required as $column) {
        if (!isset($nodeColumns[$column])) { throw new RuntimeException('Unsupported knowledge_explorer schema'); }
    }
    $stmt = $db->prepare('SELECT knowledge_node_id, parent_node_id, node_title, comment, tacto_when, tacto_what, tacto_why, organizational_basis, node_type, sort_order, created_at, updated_at
                            FROM knowledge_explorer
                           WHERE knowledge_group_id = ? AND deleted = 0
                           ORDER BY COALESCE(sort_order, 2147483647), knowledge_node_id');
    if (!$stmt) { throw new RuntimeException('Failed to prepare node query'); }
    $stmt->bind_param('i', $groupId);
    if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Failed to fetch nodes'); }
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['knowledge_node_id'];
        $items[$id] = [
            'node_id' => $id,
            'parent_id' => $row['parent_node_id'] === null ? null : (int)$row['parent_node_id'],
            'title' => $row['node_title'],
            'content' => $row['comment'],
            'category' => $row['node_type'],
            'when' => $row['tacto_when'],
            'what' => $row['tacto_what'],
            'why' => $row['tacto_why'],
            'organizational_basis' => $row['organizational_basis'],
            'sort_order' => $row['sort_order'] === null ? null : (int)$row['sort_order'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'fragments' => [],
            'unresolved_fragment_links' => [],
            'children' => [],
        ];
    }
    $result->free(); $stmt->close();

    $links = [];
    if ($items && isset($linkColumns['knowledge_node_id'], $linkColumns['fragment_source_type'], $linkColumns['fragment_source_id'])) {
        $ids = array_keys($items); $marks = implode(',', array_fill(0, count($ids), '?'));
        $order = isset($linkColumns['display_order']) ? 'COALESCE(display_order, 2147483647), id' : 'id';
        $linkDeletedWhere = isset($linkColumns['deleted']) ? ' AND deleted = 0' : '';
        $stmt = $db->prepare("SELECT id, knowledge_node_id, fragment_source_type, fragment_source_id FROM knowledge_explorer_fragment_links WHERE knowledge_node_id IN ({$marks}){$linkDeletedWhere} ORDER BY {$order}");
        if (!$stmt) { throw new RuntimeException('Failed to prepare link query'); }
        $types = str_repeat('i', count($ids)); $bind = [$types];
        foreach ($ids as $i => $id) { $bind[] = &$ids[$i]; }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Failed to fetch links'); }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $type = $row['fragment_source_type'] === 'SRL' ? 'SRL' : strtolower((string)$row['fragment_source_type']);
            $links[] = ['link_id' => (int)$row['id'], 'node_id' => (int)$row['knowledge_node_id'], 'source_type' => $type, 'source_id' => (int)$row['fragment_source_id']];
        }
        $result->free(); $stmt->close();
    }
    $fragments = ok_read_fragments($db, $links);
    foreach ($links as $link) {
        $nodeId = $link['node_id']; $type = $link['source_type']; $sourceId = $link['source_id'];
        if (isset($fragments[$type][$sourceId])) {
            $items[$nodeId]['fragments'][] = ['source_type' => $type] + $fragments[$type][$sourceId];
        } else {
            $items[$nodeId]['unresolved_fragment_links'][] = $link + ['reason' => $type === 'SRL' ? 'source_not_supported' : 'source_not_found_or_deleted'];
        }
    }
    $roots = [];
    foreach ($items as $id => &$node) {
        $parentId = $node['parent_id'];
        if ($parentId !== null && isset($items[$parentId])) { $items[$parentId]['children'][] = &$node; }
        else { $roots[] = &$node; }
    }
    unset($node);
    return $roots;
}
