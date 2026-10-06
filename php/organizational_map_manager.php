<?php
// 議論内省マップのデータを読み出すための処理群

require_once __DIR__ . '/session_bootstrap.php';
hcimlab_start_session();
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/knowledge_group_access.php';

header('Content-Type: application/json; charset=utf-8');

// POSTデータの受け取り
$user_id = isset($_SESSION['USERID']) ? $_SESSION['USERID'] : null;      //ユーザID
$map_id = isset($_SESSION['MAPID']) ? $_SESSION['MAPID'] : null;    //マップID

//all...初期読み込み
// group...選択されたグループへ再表示
$mode = isset($_POST['mode']) ? (string)$_POST['mode'] : '';
$group_id_latest = isset($_POST['group_id']) ? (string)$_POST['group_id'] : '';

if ($user_id === null) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['error' => 'database_unavailable'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($group_id_latest !== '' && (!ctype_digit($group_id_latest) || (int)$group_id_latest <= 0)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_group'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$groupAccess = ok_core_resolve_active_group($mysqli, $group_id_latest, (string)$user_id);
if ($groupAccess['status'] !== 200) {
    http_response_code($groupAccess['status']);
    echo json_encode(['error' => $groupAccess['status'] === 403 ? 'forbidden_group' : 'group_lookup_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$group_id_latest = $groupAccess['group_id'];

$return_data = []; // DBアクセスの結果として返すキー・バリューのペア

// ユーザーが所属するグループ情報を取得
$groups = [];
$group_ids = [];

if($mode === "all" || $mode === "allRE" || $mode === "group"){
    // kgroup_user_linkからgroup_idを取得
    $sql = "SELECT kg.group_id, kg.name
            FROM kgroup_user_link kul
            INNER JOIN knowledge_groups kg ON kg.group_id = kul.group_id
            WHERE kul.user_id = ? AND kul.deleted = 0 AND kg.deleted = 0
            ORDER BY kul.created_at DESC, kg.group_id DESC";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('s', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $groupId = (string)$row['group_id'];
            if (!in_array($groupId, $group_ids, true)) {
                $group_ids[] = $groupId;
                $groups[] = $row;
            }
        }
        $stmt->close();
    }
    if ($group_id_latest !== '' && !in_array($group_id_latest, $group_ids, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden_group'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($group_id_latest === '') {
        $group_id_latest = $group_ids[0] ?? '';
    }
    $return_data['groups'] = $groups;
    
    
}

// 最新のgroup_idに所属するuser_id一覧を取得
$user_ids_in_latest_group = [];
if (!empty($group_id_latest)) {
    $sql3 = "SELECT user_id FROM kgroup_user_link WHERE group_id = ? AND deleted = 0 ORDER BY created_at DESC";
    if ($stmt3 = $mysqli->prepare($sql3)) {
        $stmt3->bind_param('s', $group_id_latest);
        $stmt3->execute();
        $result3 = $stmt3->get_result();
        while ($row3 = $result3->fetch_assoc()) {
            $user_ids_in_latest_group[] = $row3['user_id'];
        }
        $stmt3->close();
    }
}
$return_data['user_ids_in_latest_group'] = $user_ids_in_latest_group;
$return_data['selected_group_id'] = $group_id_latest;


if (!empty($user_ids_in_latest_group)) {
    // ユーザーの情報を取得
    $users = [];
    if (!empty($user_ids_in_latest_group)) {
        // プレースホルダを作成
        $in = implode(',', array_fill(0, count($user_ids_in_latest_group), '?'));
        $sql_users = "SELECT user_id, name FROM users WHERE user_id IN ($in)";
        if ($stmt_users = $mysqli->prepare($sql_users)) {
            $types = str_repeat('s', count($user_ids_in_latest_group));
            $stmt_users->bind_param($types, ...$user_ids_in_latest_group);
            $stmt_users->execute();
            $result_users = $stmt_users->get_result();
            while ($row = $result_users->fetch_assoc()) {
                $users[] = $row;
            }
            $stmt_users->close();
        }
    }
    $return_data['users'] = $users;

    // ユーザーごとに共有したprocessノードや knowledge fragment を取得（user_idも含める）
    $user_ids_in_sql = implode(",", array_map('intval', $user_ids_in_latest_group));
    $selected_group_id_sql = intval($group_id_latest);
    $sql_nodes = "SELECT sn.id AS shared_node_id, sn.knowledge_group_id, ec.experience_knowledge_id, ec.experience_type, ec.thought_experience_node_id, ec.user_id, ec.selected_contents, ec.knowledge_fragment_content, ec.stage1, ec.stage2, ec.stage3
        FROM shared_nodes sn
        INNER JOIN experience_knowledges ec
          ON ec.experience_knowledge_id = sn.experience_knowledge_id
        WHERE sn.knowledge_group_id = $selected_group_id_sql
            AND sn.deleted = 0
            AND ec.deleted = 0
            AND ec.knowledge_fragment_content IS NOT NULL
            AND LENGTH(TRIM(ec.knowledge_fragment_content)) > 0
        ORDER BY sn.created_at DESC, ec.experience_knowledge_id DESC;";
    
    $result_organi_map_node = $mysqli->query($sql_nodes);
    $organi_map_node = [];
    if ($result_organi_map_node) {
        while ($row = $result_organi_map_node->fetch_assoc()) {
            $row['source_type'] = 'experience';
            $row['source_id'] = isset($row['experience_knowledge_id']) ? (int)$row['experience_knowledge_id'] : null;
            $row['display_node_id'] = 'experience:' . (string)$row['source_id'];
            $organi_map_node[] = $row;
        }
    }

    if ($resT = $mysqli->query("SHOW TABLES LIKE 'externalized_contents'")) {
        $hasExternalized = ($resT->num_rows > 0);
        $resT->free();
        if ($hasExternalized) {
            $hasGroupColumn = false;
            if ($resCol = $mysqli->query("SHOW COLUMNS FROM `externalized_contents` LIKE 'group_id'")) {
                $hasGroupColumn = ($resCol->num_rows > 0);
                $resCol->free();
            }
            $contentCol = 'knowledge_fragment_content';
            if ($resCol = $mysqli->query("SHOW COLUMNS FROM `externalized_contents` LIKE 'knowledge_fragments_content'")) {
                if ($resCol->num_rows > 0) { $contentCol = 'knowledge_fragments_content'; }
                $resCol->free();
            }
            $sql_externalized = "SELECT ec.externalized_contents_id, ec.user_id, ec.selected_contents, ec.`{$contentCol}` AS knowledge_fragment_content, ec.stage1, ec.stage2, ec.stage3, ec.updated_at
                FROM externalized_contents ec
                WHERE ec.deleted = 0
                    AND ec.`{$contentCol}` IS NOT NULL
                    AND LENGTH(TRIM(ec.`{$contentCol}`)) > 0";
            $sql_externalized .= $hasGroupColumn
                ? " AND ec.group_id = $selected_group_id_sql"
                : " AND 1 = 0";
            $sql_externalized .= " ORDER BY ec.updated_at DESC, ec.externalized_contents_id DESC";
            if ($result_externalized = $mysqli->query($sql_externalized)) {
                while ($row = $result_externalized->fetch_assoc()) {
                    $row['source_type'] = 'discussion';
                    $row['source_id'] = isset($row['externalized_contents_id']) ? (int)$row['externalized_contents_id'] : null;
                    $row['display_node_id'] = 'discussion:' . (string)$row['source_id'];
                    $row['experience_type'] = 'discussion';
                    $row['thought_experience_node_id'] = null;
                    $organi_map_node[] = $row;
                }
                $result_externalized->free();
            }
        }
    }
    $return_data = array_merge($return_data, ['enode' => $organi_map_node]);
    $knownUserIds = array_map('strval', array_column($users, 'user_id'));
    $authorIds = array_values(array_unique(array_map('intval', array_column($organi_map_node, 'user_id'))));
    $missingAuthorIds = array_values(array_filter($authorIds, static function ($id) use ($knownUserIds) {
        return $id > 0 && !in_array((string)$id, $knownUserIds, true);
    }));
    if ($missingAuthorIds) {
        $authorSql = implode(',', $missingAuthorIds);
        if ($authorResult = $mysqli->query("SELECT user_id, name FROM users WHERE user_id IN ($authorSql)")) {
            while ($author = $authorResult->fetch_assoc()) {
                $users[] = $author;
            }
            $authorResult->free();
        }
    }
    $return_data['users'] = $users;
}

$return_data['users'] = $return_data['users'] ?? [];
$return_data['enode'] = $return_data['enode'] ?? [];

if (empty($return_data)) {
    echo json_encode(["error" => "not"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
} else {
    $return_data['current_user_id'] = $user_id;
    echo json_encode($return_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

?>
