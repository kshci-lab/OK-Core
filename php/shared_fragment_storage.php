<?php

declare(strict_types=1);

/** externalized_contents is represented by the existing UI's discussion namespace. */
function ok_core_register_discussion_share(mysqli $db, int $fragmentId, int $groupId): void
{
    // A missing destination must not revoke an existing share or guess a group.
    if ($groupId <= 0) return;
    $stmt = $db->prepare('SELECT group_id FROM knowledge_groups WHERE group_id = ? AND deleted = 0');
    if (!$stmt) throw new RuntimeException('share_group_lookup_failed');
    $stmt->bind_param('i', $groupId);
    if (!$stmt->execute()) throw new RuntimeException('share_group_lookup_failed');
    $stmt->store_result();
    $found = $stmt->num_rows > 0;
    $stmt->close();
    if (!$found) throw new RuntimeException('share_group_not_found');
    $stmt = $db->prepare("INSERT INTO shared_nodes
        (fragment_source_type, knowledge_fragment_id, knowledge_group_id, deleted)
        VALUES ('discussion', ?, ?, 0) ON DUPLICATE KEY UPDATE deleted = 0");
    if (!$stmt) throw new RuntimeException('share_insert_prepare_failed');
    try {
        $stmt->bind_param('ii', $fragmentId, $groupId);
        if (!$stmt->execute()) throw new RuntimeException('share_insert_failed');
    } finally {
        $stmt->close();
    }
}
