<?php
// 議論内省マップのデータを読み出すための処理群

session_start();
require("connect_db.php");

// POSTデータの受け取り
$user_id = $_SESSION['USERID'];      //ユーザID
$map_id = $_SESSION['MAPID'];    //マップID

//all...初期読み込み
// group...選択されたグループへ再表示
$mode = $_POST['mode']; 
$group_id_latest =  $_POST['group_id'];

$return_data = []; // DBアクセスの結果として返すキー・バリューのペア

// ユーザーが所属するグループ情報を取得
$groups = [];
$group_ids = [];

if($mode === "all" || $mode === "allRE" ){
    // kgroup_user_linkからgroup_idを取得
    $sql = "SELECT group_id, created_at FROM kgroup_user_link WHERE user_id = ? ORDER BY created_at DESC";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('s', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $group_ids[] = $row['group_id'];
            if (empty($group_id_latest)) {
                $group_id_latest = $row['group_id']; // created_atが最新のgroup_id
            }
        }
        $stmt->close();

        if (!empty($group_ids)) {
            // knowledge_groupsからgroup_idに合致する情報を取得
            $in = implode(',', array_fill(0, count($group_ids), '?'));
            $sql2 = "SELECT * FROM knowledge_groups WHERE group_id IN ($in) ORDER BY created_at DESC";
            if ($stmt2 = $mysqli->prepare($sql2)) {
                $types = str_repeat('s', count($group_ids));
                $stmt2->bind_param($types, ...$group_ids);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                while ($row2 = $result2->fetch_assoc()) {
                    $groups[] = $row2;
                }
                $stmt2->close();
            }
        }
    }
    $return_data['groups'] = $groups;
    
    
}else if($mode === "group"){
    
}

// 最新のgroup_idに所属するuser_id一覧を取得
$user_ids_in_latest_group = [];
if (!empty($group_id_latest)) {
    $sql3 = "SELECT user_id FROM kgroup_user_link WHERE group_id = ? ORDER BY created_at DESC";
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
        FROM experience_knowledges ec
        LEFT JOIN shared_nodes sn
          ON sn.experience_knowledge_id = ec.experience_knowledge_id
          AND sn.knowledge_group_id = $selected_group_id_sql
          AND sn.deleted = 0
        WHERE ec.user_id IN ($user_ids_in_sql)
            AND ec.deleted = 0
            AND ec.knowledge_fragment_content IS NOT NULL
            AND LENGTH(TRIM(ec.knowledge_fragment_content)) > 0
        ORDER BY COALESCE(sn.created_at, ec.updated_at) DESC, ec.experience_knowledge_id DESC;";
    
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
            $contentCol = 'knowledge_fragment_content';
            if ($resCol = $mysqli->query("SHOW COLUMNS FROM `externalized_contents` LIKE 'knowledge_fragments_content'")) {
                if ($resCol->num_rows > 0) { $contentCol = 'knowledge_fragments_content'; }
                $resCol->free();
            }
            $sql_externalized = "SELECT ec.externalized_contents_id, ec.user_id, ec.selected_contents, ec.`{$contentCol}` AS knowledge_fragment_content, ec.stage1, ec.stage2, ec.stage3, ec.updated_at
                FROM externalized_contents ec
                WHERE ec.user_id IN ($user_ids_in_sql)
                    AND ec.deleted = 0
                    AND ec.`{$contentCol}` IS NOT NULL
                    AND LENGTH(TRIM(ec.`{$contentCol}`)) > 0
                ORDER BY ec.updated_at DESC, ec.externalized_contents_id DESC";
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
}

if (empty($return_data)) {
    echo json_encode(["error" => "not"]);
    return;
} else {
    $return_data['current_user_id'] = $user_id;
    echo json_encode($return_data);
    return;
}

?>
