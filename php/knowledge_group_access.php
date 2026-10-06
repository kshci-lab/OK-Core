<?php

/** Resolve an active knowledge group available to the signed-in user. */
function ok_core_resolve_active_group(mysqli $mysqli, string $requestedGroupId, string $userId): array
{
    if ($userId === '') {
        return ['status' => 401, 'group_id' => ''];
    }
    if ($requestedGroupId !== '' && (!ctype_digit($requestedGroupId) || (int)$requestedGroupId <= 0)) {
        return ['status' => 400, 'group_id' => ''];
    }

    $sql = 'SELECT kul.group_id
              FROM kgroup_user_link kul
              INNER JOIN knowledge_groups kg ON kg.group_id = kul.group_id
             WHERE kul.user_id = ? AND kul.deleted = 0 AND kg.deleted = 0';
    if ($requestedGroupId !== '') {
        $sql .= ' AND kul.group_id = ?';
    }
    $sql .= ' ORDER BY kul.created_at DESC, kul.group_id DESC LIMIT 1';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return ['status' => 500, 'group_id' => ''];
    }
    if ($requestedGroupId !== '') {
        $stmt->bind_param('ss', $userId, $requestedGroupId);
    } else {
        $stmt->bind_param('s', $userId);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return ['status' => 500, 'group_id' => ''];
    }
    $stmt->bind_result($groupId);
    $found = $stmt->fetch();
    $stmt->close();
    if (!$found) {
        return ['status' => $requestedGroupId !== '' ? 403 : 200, 'group_id' => ''];
    }
    return ['status' => 200, 'group_id' => (string)$groupId];
}

/** Check that every selected fragment belongs to the current group. */
function ok_core_group_has_fragments(mysqli $mysqli, string $groupId, string $sourceType, array $ids): bool
{
    if ($groupId === '' || !$ids || !in_array($sourceType, ['experience', 'discussion'], true)) return false;
    $ids = array_values(array_unique(array_map('intval', $ids)));
    foreach ($ids as $id) if ($id <= 0) return false;
    $group = (int)$groupId;
    $in = implode(',', $ids);
    if ($sourceType === 'experience') {
        $sql = "SELECT DISTINCT ek.experience_knowledge_id AS id FROM shared_nodes sn
                INNER JOIN experience_knowledges ek ON ek.experience_knowledge_id = sn.experience_knowledge_id
                WHERE sn.knowledge_group_id = $group AND sn.deleted = 0 AND ek.deleted = 0
                  AND ek.experience_knowledge_id IN ($in)";
    } else {
        $sql = "SELECT externalized_contents_id AS id FROM externalized_contents
                WHERE group_id = $group AND deleted = 0 AND externalized_contents_id IN ($in)";
    }
    $result = $mysqli->query($sql);
    if (!$result) return false;
    $found = [];
    while ($row = $result->fetch_assoc()) $found[(int)$row['id']] = true;
    $result->free();
    foreach ($ids as $id) if (!isset($found[$id])) return false;
    return true;
}
