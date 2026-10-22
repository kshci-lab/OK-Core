<?php
declare(strict_types=1);

/**
 * Read-only organizational-knowledge JSON export for the diagnosis system.
 *
 * Usage:
 *   php scripts/export_organizational_knowledge.php --group-id=10 --user-id=123 --output=/tmp/ok.json
 *
 * The user must belong to the requested, non-deleted group. This is a CLI
 * exporter; it is intentionally not exposed as an unauthenticated HTTP API.
 */

const REQUIRED_SCHEMA = [
    'knowledge_explorer' => [
        'knowledge_node_id', 'parent_node_id', 'node_title', 'comment',
        'tacto_when', 'tacto_what', 'tacto_why', 'organizational_basis',
        'deleted', 'sort_order', 'knowledge_group_id',
    ],
    'knowledge_explorer_fragment_links' => [
        'knowledge_node_id', 'fragment_source_type', 'fragment_source_id', 'display_order',
    ],
    'experience_knowledges' => [
        'experience_knowledge_id', 'selected_contents', 'knowledge_fragment_content',
        'stage1', 'stage2', 'stage3', 'user_id', 'deleted', 'discussed',
    ],
    'externalized_contents' => [
        'externalized_contents_id', 'selected_contents', 'knowledge_fragment_content',
        'stage1', 'stage2', 'stage3', 'user_id', 'group_id', 'source_system',
        'source_type', 'source_id', 'deleted', 'discussed',
    ],
    'knowledge_groups' => ['group_id', 'name', 'deleted'],
    'kgroup_user_link' => ['group_id', 'user_id', 'deleted'],
];

function fail_export(string $message, int $status = 1): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit($status);
}

function option_value(array $options, string $name): string
{
    $value = $options[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

function sql_identifier(mysqli $db, string $name): string
{
    return '`' . $db->real_escape_string($name) . '`';
}

function verify_schema(mysqli $db): void
{
    foreach (REQUIRED_SCHEMA as $table => $requiredColumns) {
        $tableSql = sql_identifier($db, $table);
        $result = $db->query("SHOW COLUMNS FROM {$tableSql}");
        if (!$result) {
            fail_export("Cannot inspect table {$table}: {$db->error}");
        }
        $actual = [];
        while ($row = $result->fetch_assoc()) {
            $actual[(string)$row['Field']] = true;
        }
        $result->free();
        $missing = array_values(array_filter($requiredColumns, static fn(string $column): bool => !isset($actual[$column])));
        if ($missing) {
            fail_export("Schema mismatch in {$table}; missing columns: " . implode(', ', $missing));
        }
    }
}

function fetch_all(mysqli_stmt $statement, string $label = 'query'): array
{
    $result = $statement->get_result();
    if (!$result) {
        fail_export("Query result could not be read for {$label}: {$statement->error} (errno {$statement->errno})");
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    $statement->free_result();
    return $rows;
}

$options = getopt('', [
    'group-id:', 'user-id:', 'output:', 'host::', 'port::', 'db::', 'user::', 'password::',
]);
$groupIdRaw = option_value($options, 'group-id');
$userIdRaw = option_value($options, 'user-id');
$output = option_value($options, 'output');
if (!ctype_digit($groupIdRaw) || (int)$groupIdRaw <= 0) fail_export('--group-id must be a positive integer');
if (!ctype_digit($userIdRaw) || (int)$userIdRaw <= 0) fail_export('--user-id must be a positive integer');
if ($output === '') fail_export('--output is required');

$host = option_value($options, 'host') ?: (getenv('OK_CORE_DB_HOST') ?: '127.0.0.1');
$port = (int)(option_value($options, 'port') ?: (getenv('OK_CORE_DB_PORT') ?: '8889'));
$database = option_value($options, 'db') ?: (getenv('OK_CORE_DB_NAME') ?: 'ok_core');
$dbUser = option_value($options, 'user') ?: (getenv('OK_CORE_DB_USER') ?: 'root');
$password = array_key_exists('password', $options)
    ? option_value($options, 'password')
    : (getenv('OK_CORE_DB_PASSWORD') !== false ? (string)getenv('OK_CORE_DB_PASSWORD') : 'root');

mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli($host, $dbUser, $password, $database, $port);
if ($db->connect_error) fail_export('Database connection failed: ' . $db->connect_error);
$db->set_charset('utf8mb4');
verify_schema($db);
// Reconnect after SHOW COLUMNS validation so no metadata result can affect
// the prepared statements used for the export queries.
$db->close();
$db = new mysqli($host, $dbUser, $password, $database, $port);
if ($db->connect_error) fail_export('Database reconnect failed: ' . $db->connect_error);
$db->set_charset('utf8mb4');

$groupId = (int)$groupIdRaw;
$userId = (int)$userIdRaw;

$stmt = $db->prepare('SELECT group_id, name FROM knowledge_groups WHERE group_id = ? AND deleted = 0 LIMIT 1');
$stmt->bind_param('i', $groupId);
$stmt->execute();
$groups = fetch_all($stmt, 'group');
$stmt->close();
if (!$groups) fail_export('Requested group does not exist or is deleted');

$stmt = $db->prepare('SELECT group_id FROM kgroup_user_link WHERE group_id = ? AND user_id = ? AND deleted = 0 LIMIT 1');
$stmt->bind_param('ii', $groupId, $userId);
$stmt->execute();
$membership = fetch_all($stmt, 'membership');
$stmt->close();
if (!$membership) fail_export('User is not a member of the requested group');

$stmt = $db->prepare('SELECT knowledge_node_id, parent_node_id, node_title, comment,
    tacto_when, tacto_what, tacto_why, organizational_basis, sort_order
    FROM knowledge_explorer
    WHERE knowledge_group_id = ? AND deleted = 0
    ORDER BY COALESCE(parent_node_id, 0), sort_order, knowledge_node_id');
$stmt->bind_param('i', $groupId);
$stmt->execute();
$nodes = fetch_all($stmt, 'nodes');
$stmt->close();

$nodeIds = array_map(static fn(array $row): int => (int)$row['knowledge_node_id'], $nodes);
$linksByNode = [];
if ($nodeIds) {
    $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));
    $sql = "SELECT knowledge_node_id, fragment_source_type, fragment_source_id, display_order
            FROM knowledge_explorer_fragment_links
            WHERE knowledge_node_id IN ({$placeholders})
            ORDER BY knowledge_node_id, display_order, id";
    $sql = str_replace($placeholders, implode(',', array_map('intval', $nodeIds)), $sql);
    $result = $db->query($sql);
    if (!$result) fail_export('Links query failed: ' . $db->error);
    $links = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    foreach ($links as $link) $linksByNode[(int)$link['knowledge_node_id']][] = $link;
}

$experienceIds = [];
$discussionIds = [];
foreach ($linksByNode as $links) {
    foreach ($links as $link) {
        $type = (string)$link['fragment_source_type'];
        $id = (int)$link['fragment_source_id'];
        if ($type === 'experience') $experienceIds[$id] = true;
        if ($type === 'discussion') $discussionIds[$id] = true;
    }
}

$kfByKey = [];
if ($experienceIds) {
    $ids = array_keys($experienceIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT experience_knowledge_id AS source_id, selected_contents,
        knowledge_fragment_content, stage1, stage2, stage3, user_id, discussed
        FROM experience_knowledges WHERE experience_knowledge_id IN (" . implode(',', array_map('intval', $ids)) . ") AND deleted = 0";
    $result = $db->query($sql);
    if (!$result) fail_export('Experience KF query failed: ' . $db->error);
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) $kfByKey['experience:' . (int)$row['source_id']] = $row + ['source_type' => 'experience'];
    $result->free();
}
if ($discussionIds) {
    $ids = array_keys($discussionIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT externalized_contents_id AS source_id, selected_contents,
        knowledge_fragment_content, stage1, stage2, stage3, user_id, group_id,
        source_system, source_type, source_id AS external_source_id, discussed
        FROM externalized_contents
        WHERE externalized_contents_id IN (" . implode(',', array_map('intval', $ids)) . ") AND deleted = 0";
    $result = $db->query($sql);
    if (!$result) fail_export('Discussion KF query failed: ' . $db->error);
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) $kfByKey['discussion:' . (int)$row['source_id']] = $row + ['source_type' => 'discussion'];
    $result->free();
}

$exportNodes = [];
foreach ($nodes as $node) {
    $nodeId = (int)$node['knowledge_node_id'];
    $exportKfs = [];
    foreach ($linksByNode[$nodeId] ?? [] as $link) {
        $key = (string)$link['fragment_source_type'] . ':' . (int)$link['fragment_source_id'];
        if (!isset($kfByKey[$key])) continue;
        $kf = $kfByKey[$key];
        $exportKfs[] = [
            'source_type' => (string)$link['fragment_source_type'],
            'source_id' => (int)$link['fragment_source_id'],
            'content' => (string)$kf['knowledge_fragment_content'],
            'selected_contents' => (string)$kf['selected_contents'],
            'stage1' => (string)$kf['stage1'],
            'stage2' => (string)$kf['stage2'],
            'stage3' => (string)$kf['stage3'],
            'user_id' => (int)$kf['user_id'],
            'discussed' => (string)$kf['discussed'],
            'display_order' => $link['display_order'] === null ? null : (int)$link['display_order'],
        ];
        foreach (['group_id', 'source_system', 'source_type', 'external_source_id'] as $optional) {
            if (array_key_exists($optional, $kf)) $exportKfs[count($exportKfs) - 1][$optional] = $kf[$optional];
        }
    }
    $exportNodes[] = [
        'id' => $nodeId,
        'parent_id' => $node['parent_node_id'] === null ? null : (int)$node['parent_node_id'],
        'group_id' => $groupId,
        'title' => (string)$node['node_title'],
        'comment' => $node['comment'] === null ? null : (string)$node['comment'],
        'when' => $node['tacto_when'] === null ? null : (string)$node['tacto_when'],
        'what' => $node['tacto_what'] === null ? null : (string)$node['tacto_what'],
        'why' => $node['tacto_why'] === null ? null : (string)$node['tacto_why'],
        'organizational_basis' => $node['organizational_basis'] === null ? null : (string)$node['organizational_basis'],
        'sort_order' => $node['sort_order'] === null ? null : (int)$node['sort_order'],
        'knowledge_fragments' => $exportKfs,
    ];
}

$payload = [
    'meta' => [
        'system' => 'OK-Core',
        'export_type' => 'organizational_knowledge',
        'schema_version' => '1.0',
        'group_id' => $groupId,
        'exported_at' => date(DATE_ATOM),
        'read_only' => true,
    ],
    'knowledge_group' => ['group_id' => $groupId, 'name' => (string)$groups[0]['name']],
    'organizational_knowledges' => $exportNodes,
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if ($json === false) fail_export('JSON encoding failed: ' . json_last_error_msg());
if (file_put_contents($output, $json . "\n", LOCK_EX) === false) fail_export("Cannot write output: {$output}");

fwrite(STDOUT, "Exported " . count($exportNodes) . " organizational knowledges to {$output}\n");
