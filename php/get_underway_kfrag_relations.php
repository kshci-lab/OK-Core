<?php
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/connect_db.php';

function respond_underway(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalize_underway_source_type($value): string {
    $type = strtolower(trim((string)$value));
    if ($type === 'discussion') { return 'discussion'; }
    if ($type === 'srl') { return 'SRL'; }
    if (in_array($type, ['experience', 'discussion'], true)) { return $type; }
    if ((string)$value === 'SRL') { return 'SRL'; }
    return '';
}

function has_column_underway(mysqli $mysqli, string $table, string $column): bool {
    if ($res = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE '".$mysqli->real_escape_string($column)."'")) {
        $ok = ($res->num_rows > 0);
        $res->free();
        return $ok;
    }
    return false;
}

function get_underway_rows(mysqli $mysqli, string $table, string $idColumn, string $sourceType): array {
    if (!has_column_underway($mysqli, $table, $idColumn) || !has_column_underway($mysqli, $table, 'discussed')) {
        return [];
    }
    $rows = [];
    $sql = "SELECT `$idColumn` AS fragment_id FROM `$table` WHERE `discussed` = 'UNDERWAY'";
    if ($res = $mysqli->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $fragmentId = isset($row['fragment_id']) ? intval($row['fragment_id'], 10) : 0;
            if ($fragmentId > 0) {
                $rows[] = ['source_type' => $sourceType, 'fragment_id' => $fragmentId];
            }
        }
        $res->free();
    }
    return $rows;
}

function load_latest_targets(mysqli $mysqli, int $fragmentId, string $sourceType): array {
    $table = 'discussion_history';
    $fragmentIds = [];
    if (!has_column_underway($mysqli, $table, 'knowledge_fragment_id')) {
        return [];
    }
    $hasSourceType = has_column_underway($mysqli, $table, 'fragment_source_type');
    $colType = '';
    if ($res = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'knowledge_fragment_id'")) {
        $row = $res->fetch_assoc();
        if ($row && isset($row['Type'])) { $colType = strtolower((string)$row['Type']); }
        $res->free();
    }
    $isVarchar = (strpos($colType, 'varchar') !== false) || (strpos($colType, 'text') !== false);
    if (!$isVarchar) { return []; }

    $sql = "SELECT `knowledge_fragment_id` FROM `$table` WHERE FIND_IN_SET(?, `knowledge_fragment_id`)";
    if ($hasSourceType) {
        if ($sourceType === 'experience') {
            $sql .= " AND (`fragment_source_type` = ? OR `fragment_source_type` IS NULL OR `fragment_source_type` = '')";
        } else {
            $sql .= " AND `fragment_source_type` = ?";
        }
    } elseif ($sourceType !== 'experience') {
        return [];
    }
    $sql .= " ORDER BY `posted_time` DESC, `discussion_history_id` DESC LIMIT 1";
    if (!$stmt = $mysqli->prepare($sql)) { return []; }
    if ($hasSourceType) {
        $stmt->bind_param('is', $fragmentId, $sourceType);
    } else {
        $stmt->bind_param('i', $fragmentId);
    }
    $raw = null;
    if ($stmt->execute()) {
        $stmt->bind_result($csv);
        if ($stmt->fetch()) { $raw = $csv; }
    }
    $stmt->close();
    if ($raw === null) { return []; }
    foreach (explode(',', (string)$raw) as $part) {
        $id = intval(trim((string)$part), 10);
        if ($id > 0 && !in_array($id, $fragmentIds, true)) { $fragmentIds[] = $id; }
    }
    return $fragmentIds;
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    respond_underway(['status' => 'error', 'message' => 'DB接続失敗']);
}
@$mysqli->set_charset('utf8mb4');

$underway = array_merge(
    get_underway_rows($mysqli, 'experience_knowledges', 'experience_knowledge_id', 'experience'),
    get_underway_rows($mysqli, 'externalized_contents', 'externalized_contents_id', 'discussion')
);

$relations = [];
$seen = [];
foreach ($underway as $row) {
    $sourceType = normalize_underway_source_type($row['source_type']);
    $fragmentId = intval($row['fragment_id'], 10);
    if (!$sourceType || $fragmentId <= 0) { continue; }
    $targets = load_latest_targets($mysqli, $fragmentId, $sourceType);
    if (count($targets) < 2) { continue; }
    $refs = [];
    foreach ($targets as $targetId) {
        $ref = $sourceType . ':' . $targetId;
        if (!in_array($ref, $refs, true)) { $refs[] = $ref; }
    }
    $count = count($refs);
    for ($i = 0; $i < $count - 1; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $from = $refs[$i];
            $to = $refs[$j];
            $key = ($from < $to) ? ($from . '|' . $to) : ($to . '|' . $from);
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $parts = explode('|', $key, 2);
            $relations[] = ['from' => $parts[0], 'to' => $parts[1]];
        }
    }
}

$mysqli->close();
respond_underway(['status' => 'ok', 'relations' => $relations]);
