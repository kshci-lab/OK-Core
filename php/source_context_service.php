<?php
declare(strict_types=1);
require_once __DIR__ . '/source_context_client.php';
require_once __DIR__ . '/knowledge_group_access.php';

/** Resolve only an active KF shared to a group the signed-in viewer belongs to. */
function source_context_fragment(mysqli $db, int $userId, int $groupId, int $fragmentId, string $actor = ''): array
{
    $stmt = $db->prepare('SELECT sso_sub, is_active FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $userId); $stmt->execute();
    $viewer = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$viewer || (int)$viewer['is_active'] !== 1 || ($actor !== '' && (string)$viewer['sso_sub'] !== $actor)) {
        throw new SourceContextException('ログイン情報を確認してください。', 401, 'not_logged_in');
    }
    $group = ok_core_resolve_active_group($db, (string)$groupId, (string)$userId);
    if ($group['status'] !== 200 || $group['group_id'] === '') throw new SourceContextException('このグループの閲覧権限がありません。', 403, 'forbidden_group');
    $stmt = $db->prepare('SELECT ek.experience_knowledge_id, ek.thought_experience_node_id, ek.experience_type,
        ek.knowledge_fragment_content, km.source_system, km.external_kf_id, km.snapshot_json, km.source_deleted_at
        FROM shared_nodes sn INNER JOIN experience_knowledges ek ON ek.experience_knowledge_id = sn.knowledge_fragment_id
        LEFT JOIN kf_source_mappings km ON km.experience_knowledge_id = ek.experience_knowledge_id
        WHERE sn.fragment_source_type = \'experience\' AND sn.knowledge_group_id = ? AND sn.deleted = 0
          AND ek.experience_knowledge_id = ? AND ek.deleted = 0 LIMIT 1');
    $stmt->bind_param('ii', $groupId, $fragmentId);
    $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row || $row['source_deleted_at'] !== null) throw new SourceContextException('このグループに共有されたKFが見つかりません。', 404, 'fragment_not_shared');
    $row['legacy'] = $row['external_kf_id'] === null || $row['external_kf_id'] === '';
    if ($row['legacy']) {
        if (trim((string)$row['thought_experience_node_id']) === '') throw new SourceContextException('産出元への参照がありません。', 404, 'source_fragment_unavailable');
        $row['source_system'] = 'forest-platform';
        $row['external_kf_id'] = (string)$fragmentId;
    }
    return $row;
}

function source_context_snapshot(array $fragment): ?array
{
    $payload = json_decode((string)($fragment['snapshot_json'] ?? ''), true);
    if (!is_array($payload['context_package']['items'] ?? null)) return null;
    $package = $payload['context_package'];
    $package['activity'] = $payload['activity'] ?? [];
    return ['format' => 'context-package.v1', 'external_kf_id' => (string)$fragment['external_kf_id'], 'context_package' => $package];
}

function source_context_result(array $fragment, string $actor, int $groupId, string $format, string $mode): array
{
    if (!in_array($format, ['context-package.v1', 'forest.thinking-process-map.v1'], true) || !in_array($mode, ['latest', 'snapshot'], true)) {
        throw new SourceContextException('文脈の表示方式が正しくありません。', 400, 'INVALID_CONTEXT_MODE');
    }
    $snapshot = source_context_snapshot($fragment);
    $meta = ['source_system' => $fragment['source_system'], 'summary' => $fragment['knowledge_fragment_content']];
    if ($mode === 'snapshot') {
        if ($format !== 'context-package.v1' || !$snapshot) throw new SourceContextException('送信時点の文脈が保存されていません。', 404, 'SNAPSHOT_NOT_FOUND');
        return $meta + ['provenance' => 'snapshot', 'data' => $snapshot];
    }
    $packageId = !$fragment['legacy'] && $snapshot ? (string)($snapshot['context_package']['external_context_package_id'] ?? '') : '';
    try {
        $data = source_context_fetch($fragment['source_system'], (string)$fragment['external_kf_id'], $actor, $groupId, $format,
            $packageId, $fragment['legacy'] ? (string)$fragment['thought_experience_node_id'] : '',
            $fragment['legacy'] ? (string)$fragment['experience_type'] : '');
        return $meta + ['provenance' => 'source_api', 'data' => $data];
    } catch (SourceContextException $error) {
        if ($format !== 'context-package.v1' || !$snapshot) throw $error;
        return $meta + ['provenance' => 'snapshot', 'data' => $snapshot,
            'warning' => ['code' => $error->errorCode, 'message' => '産出元から取得できないため、送信時点の保存内容を表示しています。']];
    }
}
