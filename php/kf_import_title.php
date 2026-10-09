<?php

// Keep the normalized title column and the raw payload in sync.
function import_kf_update_title_sql(string $table = 'externalized_contents'): string
{
    if (!preg_match('/^[a-z_]+$/', $table)) {
        throw new InvalidArgumentException('Invalid table name');
    }
    return "UPDATE `{$table}`
               SET title = ?,
                   raw_payload = JSON_SET(
                   IF(JSON_VALID(raw_payload), raw_payload, '{}'),
                   '$.title', ?
               )
             WHERE externalized_contents_id = ?";
}

function import_kf_update_duplicate_title(mysqli $mysqli, int $existingId, array $payload, string $table = 'externalized_contents'): bool
{
    if (!array_key_exists('title', $payload)) {
        return false;
    }
    if (!is_string($payload['title'])) {
        throw new InvalidArgumentException('title must be a string');
    }
    $updateStmt = $mysqli->prepare(import_kf_update_title_sql($table));
    if (!$updateStmt) {
        throw new RuntimeException('Failed to prepare title update: ' . $mysqli->error);
    }
    $title = $payload['title'];
    $updateStmt->bind_param('ssi', $title, $title, $existingId);
    if (!$updateStmt->execute()) {
        $error = $updateStmt->error;
        $updateStmt->close();
        throw new RuntimeException('Title update failed: ' . $error);
    }
    $updateStmt->close();
    return true;
}
