<?php
declare(strict_types=1);

// Used after the organizational-knowledge endpoint's session and membership checks.
// Each query proves the visibility of the KF itself. An author's membership alone
// does not grant read access to that author's other KFs.
function ok_discovery_queries(bool $typedShares = false, bool $hasTitle = false): array
{
    $title = $hasTitle ? 'ec.title' : 'NULL AS title';
    if ($typedShares) {
        // Match the deployed group list: typed shares for human KFs, direct group for OKDiag.
        return [
            'experience' => "SELECT DISTINCT sn.knowledge_group_id AS group_id,
                ek.experience_knowledge_id AS source_id, ek.knowledge_fragment_content AS content,
                ek.selected_contents, ek.stage1, ek.stage2, ek.stage3
                FROM shared_nodes sn
                JOIN experience_knowledges ek ON ek.experience_knowledge_id = sn.knowledge_fragment_id
                WHERE sn.knowledge_group_id = ? AND sn.fragment_source_type = 'experience'
                  AND sn.deleted = 0 AND ek.deleted = 0 ORDER BY source_id",
            'discussion' => "SELECT requested.group_id, ec.externalized_contents_id AS source_id,
                ec.knowledge_fragment_content AS content, ec.selected_contents, ec.stage1, ec.stage2, ec.stage3,
                {$title}, ec.raw_payload FROM externalized_contents ec
                CROSS JOIN (SELECT ? AS group_id) requested
                WHERE ec.deleted = 0 AND (
                    (ec.source_system = 'OKDiag' AND ec.source_user_ref = 'okdiag-ai' AND ec.group_id = requested.group_id)
                    OR ((ec.source_system IS NULL OR ec.source_system <> 'OKDiag') AND EXISTS (
                        SELECT 1 FROM shared_nodes sn WHERE sn.fragment_source_type = 'discussion'
                          AND sn.knowledge_fragment_id = ec.externalized_contents_id
                          AND sn.knowledge_group_id = requested.group_id AND sn.deleted = 0
                    ))
                ) ORDER BY source_id",
        ];
    }

    return [
        'experience' => "SELECT DISTINCT ke.knowledge_group_id AS group_id,
            ek.experience_knowledge_id AS source_id, ek.knowledge_fragment_content AS content,
            ek.selected_contents, ek.stage1, ek.stage2, ek.stage3
            FROM knowledge_explorer_fragment_links fl
            JOIN knowledge_explorer ke ON ke.knowledge_node_id = fl.knowledge_node_id
            JOIN experience_knowledges ek ON ek.experience_knowledge_id = fl.fragment_source_id
            WHERE ke.knowledge_group_id = ? AND ke.deleted = 0
              AND fl.fragment_source_type = 'experience' AND ek.deleted = 0
            ORDER BY source_id",
        'discussion' => "SELECT ec.group_id, ec.externalized_contents_id AS source_id,
            ec.knowledge_fragment_content AS content, ec.selected_contents, ec.stage1, ec.stage2, ec.stage3,
            {$title}, ec.raw_payload FROM externalized_contents ec
            WHERE ec.group_id = ? AND ec.deleted = 0 ORDER BY source_id",
    ];
}

function ok_discovery_public_discussion(array $row): array
{
    $payload = json_decode((string)($row['raw_payload'] ?? ''), true);
    unset($row['raw_payload']);
    $title = $row['title'] ?? null;
    if (empty($title) && is_string($payload['title'] ?? null)) $title = $payload['title'];
    $row['title'] = $title;
    if (is_array($payload) && ($payload['source_system'] ?? null) === 'OKDiag'
        && ($payload['source_user_ref'] ?? null) === 'okdiag-ai'
        && is_array($payload['okdiag_provenance'] ?? null)) {
        $row['source_system'] = 'OKDiag';
        $row['source_user_name'] = 'OKDiag（AI生成）';
        $row['okdiag_provenance'] = $payload['okdiag_provenance'];
    }
    return $row;
}

function ok_discovery_response(mysqli $db, int $groupId): array
{
    $catalog = [];
    $visible = [];
    $shareColumns = ok_read_table_columns($db, 'shared_nodes');
    $typedShares = isset($shareColumns['fragment_source_type'], $shareColumns['knowledge_fragment_id']);
    $externalizedColumns = ok_read_table_columns($db, 'externalized_contents');
    foreach (ok_discovery_queries($typedShares, isset($externalizedColumns['title'])) as $type => $sql) {
        $stmt = $db->prepare($sql);
        if (!$stmt || !$stmt->bind_param('i', $groupId) || !$stmt->execute() || !($rows = $stmt->get_result())) {
            throw new RuntimeException('Cannot verify group-visible KFs');
        }
        while ($row = $rows->fetch_assoc()) {
            if ($type === 'discussion') $row = ok_discovery_public_discussion($row);
            $entry = ['source_type' => $type] + $row;
            $catalog[] = $entry;
            $visible[$type][(int)$row['source_id']] = $entry;
        }
        $rows->free();
        $stmt->close();
    }
    return ['status' => 'ok', 'group_id' => $groupId, 'nodes' => ok_read_organizational_knowledge($db, $groupId, $visible),
        'kf_scope' => 'group_visible_v1', 'kfs' => $catalog];
}
