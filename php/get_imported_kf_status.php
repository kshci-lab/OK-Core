<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/connect_db.php';

function imported_kf_respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function imported_kf_trimmed_string($value): string
{
    return trim((string)($value ?? ''));
}

function imported_kf_base_url(): string
{
    $envBaseUrl = getenv('HCIMLAB_SSO_BASE_URL');
    if ($envBaseUrl !== false && trim((string)$envBaseUrl) !== '') {
        return rtrim((string)$envBaseUrl, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== ''
        ? (string)$_SERVER['HTTP_HOST']
        : 'localhost:8888';
    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '/OK-Core/php/get_imported_kf_status.php';
    $basePath = rtrim(dirname(dirname($script)), '/\\');

    if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
        $basePath = '';
    }

    return rtrim($scheme . '://' . $host . $basePath, '/');
}

function imported_kf_ok_core_url(int $externalizedContentsId): string
{
    return imported_kf_base_url() . '/index.php?externalized_contents_id=' . rawurlencode((string)$externalizedContentsId);
}

function imported_kf_organization_node_url(int $organizationNodeId): string
{
    return imported_kf_base_url() . '/index.php?knowledge_node_id=' . rawurlencode((string)$organizationNodeId);
}

function imported_kf_table_exists(mysqli $mysqli, string $tableName): bool
{
    $escaped = $mysqli->real_escape_string($tableName);
    if ($res = $mysqli->query("SHOW TABLES LIKE '{$escaped}'")) {
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }
    return false;
}

function imported_kf_resolve_columns(mysqli $mysqli, string $tableName): array
{
    $columns = [];
    if ($res = $mysqli->query("SHOW COLUMNS FROM `{$tableName}`")) {
        while ($row = $res->fetch_assoc()) {
            if (isset($row['Field'])) {
                $columns[] = (string)$row['Field'];
            }
        }
        $res->free();
    }
    return $columns;
}

function imported_kf_first_column(array $columns, array $candidates): ?string
{
    $lowerMap = [];
    foreach ($columns as $column) {
        $lowerMap[strtolower($column)] = $column;
    }
    foreach ($candidates as $candidate) {
        $key = strtolower($candidate);
        if (isset($lowerMap[$key])) {
            return $lowerMap[$key];
        }
    }
    return null;
}

function imported_kf_fetch_organization_node(mysqli $mysqli, int $organizationNodeId): array
{
    if ($organizationNodeId <= 0 || !imported_kf_table_exists($mysqli, 'knowledge_explorer')) {
        return [];
    }

    $columns = imported_kf_resolve_columns($mysqli, 'knowledge_explorer');
    $idColumn = imported_kf_first_column($columns, ['knowledge_node_id', 'node_id', 'id', 'knowledge_explorer_id']);
    if ($idColumn === null) {
        return [];
    }

    $deletedColumn = imported_kf_first_column($columns, ['deleted']);
    $sql = "SELECT * FROM `knowledge_explorer` WHERE `{$idColumn}` = ?" . ($deletedColumn ? " AND `{$deletedColumn}` = 0" : '') . " LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $organizationNodeId);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $row = $result ? ($result->fetch_assoc() ?: []) : [];
    if ($result) {
        $result->free();
    }
    $stmt->close();

    if (!$row) {
        return [];
    }

    $titleColumn = imported_kf_first_column($columns, ['node_title', 'title', 'name', 'label']);
    $updatedColumn = imported_kf_first_column($columns, ['updated_at', 'update_at', 'updated', 'modified_at']);
    $groupColumn = imported_kf_first_column($columns, ['knowledge_group_id', 'group_id']);

    return [
        'organizationNodeId' => (int)$organizationNodeId,
        'organizationKnowledgeId' => (int)$organizationNodeId,
        'organizationNodeTitle' => $titleColumn && isset($row[$titleColumn]) ? $row[$titleColumn] : null,
        'organizationGroupId' => $groupColumn && isset($row[$groupColumn]) ? $row[$groupColumn] : null,
        'organizationUpdatedAt' => $updatedColumn && isset($row[$updatedColumn]) ? $row[$updatedColumn] : null,
        'organizationNodeUrl' => imported_kf_organization_node_url((int)$organizationNodeId),
    ];
}

function imported_kf_find_organization(mysqli $mysqli, int $externalizedContentsId): array
{
    if ($externalizedContentsId <= 0) {
        return [];
    }

    if (imported_kf_table_exists($mysqli, 'knowledge_explorer_fragment_links')) {
        $sql = "SELECT knowledge_node_id
                  FROM knowledge_explorer_fragment_links
                 WHERE fragment_source_type = 'discussion'
                   AND fragment_source_id = ?
              ORDER BY id DESC
                 LIMIT 1";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param('i', $externalizedContentsId);
            if ($stmt->execute()) {
                $stmt->bind_result($organizationNodeId);
                if ($stmt->fetch()) {
                    $stmt->close();
                    return imported_kf_fetch_organization_node($mysqli, (int)$organizationNodeId);
                }
            }
            $stmt->close();
        }
    }

    if (imported_kf_table_exists($mysqli, 'knowledge_explorer')) {
        $columns = imported_kf_resolve_columns($mysqli, 'knowledge_explorer');
        $idColumn = imported_kf_first_column($columns, ['knowledge_node_id', 'node_id', 'id', 'knowledge_explorer_id']);
        $externalizedColumn = imported_kf_first_column($columns, ['externalized_contents_id', 'externalizedcontent_id', 'externalized_id']);
        $deletedColumn = imported_kf_first_column($columns, ['deleted']);
        if ($idColumn && $externalizedColumn) {
            $sql = "SELECT `{$idColumn}` FROM `knowledge_explorer` WHERE `{$externalizedColumn}` = ?" . ($deletedColumn ? " AND `{$deletedColumn}` = 0" : '') . " ORDER BY `{$idColumn}` DESC LIMIT 1";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param('i', $externalizedContentsId);
                if ($stmt->execute()) {
                    $stmt->bind_result($organizationNodeId);
                    if ($stmt->fetch()) {
                        $stmt->close();
                        return imported_kf_fetch_organization_node($mysqli, (int)$organizationNodeId);
                    }
                }
                $stmt->close();
            }
        }
    }

    return [];
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    imported_kf_respond(500, [
        'success' => false,
        'message' => 'Database connection failed',
    ]);
}

@$mysqli->set_charset('utf8mb4');

$sourceSystem = imported_kf_trimmed_string($_GET['source_system'] ?? '');
$sourceType = strtolower(imported_kf_trimmed_string($_GET['source_type'] ?? 'discussion'));
$sourceId = imported_kf_trimmed_string($_GET['source_id'] ?? '');

if ($sourceType === 'externalized') {
    $sourceType = 'discussion';
}
if ($sourceType === 'srl') {
    $sourceType = 'SRL';
}

if ($sourceSystem === '' || $sourceType === '' || $sourceId === '') {
    imported_kf_respond(400, [
        'success' => false,
        'message' => 'source_system, source_type, and source_id are required',
    ]);
}

$sql = 'SELECT externalized_contents_id,
               group_id,
               source_system,
               source_type,
               source_id,
               source_user_ref,
               selected_contents,
               knowledge_fragment_content,
               stage1,
               stage2,
               stage3,
               discussed,
               created_at,
               updated_at
          FROM externalized_contents
         WHERE source_system = ?
           AND source_type = ?
           AND source_id = ?
         LIMIT 1';

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    imported_kf_respond(500, [
        'success' => false,
        'message' => 'Failed to prepare status query',
        'detail' => $mysqli->error,
    ]);
}

$stmt->bind_param('sss', $sourceSystem, $sourceType, $sourceId);
if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    imported_kf_respond(500, [
        'success' => false,
        'message' => 'Status query failed',
        'detail' => $error,
    ]);
}

$stmt->bind_result(
    $externalizedContentsId,
    $groupId,
    $foundSourceSystem,
    $foundSourceType,
    $foundSourceId,
    $sourceUserRef,
    $selectedContents,
    $knowledgeFragmentContent,
    $stage1,
    $stage2,
    $stage3,
    $discussed,
    $createdAt,
    $updatedAt
);

$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    imported_kf_respond(404, [
        'success' => false,
        'message' => 'Imported KF not found',
        'sourceSystem' => $sourceSystem,
        'sourceType' => $sourceType,
        'sourceId' => $sourceId,
    ]);
}

$organization = imported_kf_find_organization($mysqli, (int)$externalizedContentsId);
$organizationNodeId = $organization['organizationNodeId'] ?? null;
$organizationStatus = $organizationNodeId ? 'organized' : 'imported';

imported_kf_respond(200, [
    'success' => true,
    'externalizedContentsId' => (int)$externalizedContentsId,
    'sourceSystem' => $foundSourceSystem,
    'sourceType' => $foundSourceType,
    'sourceId' => $foundSourceId,
    'sourceUserRef' => $sourceUserRef,
    'groupId' => (int)$groupId,
    'organizationNodeId' => $organizationNodeId,
    'organizationKnowledgeId' => $organization['organizationKnowledgeId'] ?? null,
    'organizationNodeTitle' => $organization['organizationNodeTitle'] ?? null,
    'organizationGroupId' => $organization['organizationGroupId'] ?? null,
    'organizationUpdatedAt' => $organization['organizationUpdatedAt'] ?? null,
    'organizationNodeUrl' => $organization['organizationNodeUrl'] ?? null,
    'organizationStatus' => $organizationStatus,
    'status' => $organizationStatus,
    'discussed' => $discussed,
    'okCoreUrl' => imported_kf_ok_core_url((int)$externalizedContentsId),
    'updatedAt' => $updatedAt,
    'createdAt' => $createdAt,
]);
