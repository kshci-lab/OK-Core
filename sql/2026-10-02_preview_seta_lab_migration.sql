/*
 * seta-lab Forest -> OK-Core migration preview
 *
 * 永続テーブルへの INSERT / UPDATE / DELETE は行わない。
 * TEMPORARY TABLE のみを使って移行対象と対応関係を検証する。
 */

USE ok_core;

DROP TEMPORARY TABLE IF EXISTS tmp_mig_nodes;
DROP TEMPORARY TABLE IF EXISTS tmp_exp_ids;
DROP TEMPORARY TABLE IF EXISTS tmp_disc_ids;
DROP TEMPORARY TABLE IF EXISTS tmp_relevant_users;
DROP TEMPORARY TABLE IF EXISTS tmp_user_map;
DROP TEMPORARY TABLE IF EXISTS tmp_node_preview;

/* ---------------------------------------------------------
 * 1. 移行対象組織知
 *    seta-lab(group_id=100) の有効ノード + 議論方略カテゴリ333
 * --------------------------------------------------------- */

CREATE TEMPORARY TABLE tmp_mig_nodes (
    knowledge_node_id INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO tmp_mig_nodes
SELECT knowledge_node_id
FROM forest_platform.knowledge_explorer
WHERE knowledge_group_id = 100
  AND deleted = 0;

INSERT IGNORE INTO tmp_mig_nodes
VALUES (333);


/* ---------------------------------------------------------
 * 2. 移行対象KF
 * --------------------------------------------------------- */

CREATE TEMPORARY TABLE tmp_exp_ids (
    fragment_id INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO tmp_exp_ids
SELECT DISTINCT l.fragment_source_id
FROM forest_platform.knowledge_explorer_fragment_links l
JOIN forest_platform.knowledge_explorer k
  ON k.knowledge_node_id = l.knowledge_node_id
WHERE k.knowledge_group_id = 100
  AND k.deleted = 0
  AND l.fragment_source_type = 'experience';


CREATE TEMPORARY TABLE tmp_disc_ids (
    fragment_id INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO tmp_disc_ids
SELECT DISTINCT l.fragment_source_id
FROM forest_platform.knowledge_explorer_fragment_links l
JOIN forest_platform.knowledge_explorer k
  ON k.knowledge_node_id = l.knowledge_node_id
WHERE k.knowledge_group_id = 100
  AND k.deleted = 0
  AND l.fragment_source_type = 'discussion';


/* ---------------------------------------------------------
 * 3. 移行に必要なForestユーザー
 * --------------------------------------------------------- */

CREATE TEMPORARY TABLE tmp_relevant_users (
    forest_user_id INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

/* seta-lab所属者 */
INSERT IGNORE INTO tmp_relevant_users
SELECT user_id
FROM forest_platform.kgroup_user_link
WHERE group_id = 100
  AND deleted = 0;

/* 組織知更新者 */
INSERT IGNORE INTO tmp_relevant_users
SELECT k.updated_by
FROM forest_platform.knowledge_explorer k
JOIN tmp_mig_nodes n
  ON n.knowledge_node_id = k.knowledge_node_id;

/* experience KF作成者 */
INSERT IGNORE INTO tmp_relevant_users
SELECT e.user_id
FROM forest_platform.experience_knowledges e
JOIN tmp_exp_ids x
  ON x.fragment_id = e.experience_knowledge_id;

/* discussion KF作成者 */
INSERT IGNORE INTO tmp_relevant_users
SELECT d.user_id
FROM forest_platform.externalized_contents d
JOIN tmp_disc_ids x
  ON x.fragment_id = d.externalized_contents_id;

/* user_id=0 の旧データ用 */
INSERT IGNORE INTO tmp_relevant_users VALUES (0);


/* ---------------------------------------------------------
 * 4. Forest user_id -> OK-Core user_id の予定対応
 * --------------------------------------------------------- */

CREATE TEMPORARY TABLE tmp_user_map (
    forest_user_id INT NOT NULL PRIMARY KEY,
    ok_core_user_id INT NOT NULL,
    mapping_type VARCHAR(32) NOT NULL
) ENGINE=InnoDB;

/* 既にOK-CoreへSSO登録済み */
INSERT INTO tmp_user_map
SELECT
    fu.user_id,
    ou.user_id,
    'existing_sso'
FROM forest_platform.users fu
JOIN tmp_relevant_users r
  ON r.forest_user_id = fu.user_id
JOIN ok_core.users ou
  ON ou.sso_sub =
     CONVERT(fu.sso_sub USING utf8mb4) COLLATE utf8mb4_general_ci
WHERE fu.sso_sub IS NOT NULL;

/* SSOあり・OK-Core未登録
   Forest user_idをそのまま新規OK-Core user_idとして使用予定 */
INSERT IGNORE INTO tmp_user_map
SELECT
    fu.user_id,
    fu.user_id,
    'new_sso'
FROM forest_platform.users fu
JOIN tmp_relevant_users r
  ON r.forest_user_id = fu.user_id
LEFT JOIN ok_core.users ous
  ON ous.sso_sub =
     CONVERT(fu.sso_sub USING utf8mb4) COLLATE utf8mb4_general_ci
LEFT JOIN ok_core.users oui
  ON oui.user_id = fu.user_id
WHERE fu.sso_sub IS NOT NULL
  AND ous.user_id IS NULL
  AND oui.user_id IS NULL;

/* SSOなし旧ユーザー
   Forest user_idを保持してlegacyユーザーとして作成予定 */
INSERT IGNORE INTO tmp_user_map
SELECT
    fu.user_id,
    fu.user_id,
    'legacy'
FROM forest_platform.users fu
JOIN tmp_relevant_users r
  ON r.forest_user_id = fu.user_id
LEFT JOIN ok_core.users ou
  ON ou.user_id = fu.user_id
WHERE fu.sso_sub IS NULL
  AND ou.user_id IS NULL;

/* Forestにusersレコードが存在しない user_id=0 */
INSERT IGNORE INTO tmp_user_map
VALUES (0, 0, 'legacy_unknown');


/* ---------------------------------------------------------
 * 5. 組織知親子関係の移行予定
 *
 * 111 知識関連       -> 412
 * 222 研究方略関連   -> 413
 * 333 議論方略関連   -> 333（seta-lab用として復元）
 * --------------------------------------------------------- */

CREATE TEMPORARY TABLE tmp_node_preview (
    knowledge_node_id INT NOT NULL PRIMARY KEY,
    target_parent_node_id INT DEFAULT NULL,
    target_group_id INT NOT NULL,
    target_updated_by INT DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_node_preview
SELECT
    k.knowledge_node_id,
    CASE
        WHEN k.knowledge_node_id = 333 THEN NULL
        WHEN k.parent_node_id = 111 THEN 412
        WHEN k.parent_node_id = 222 THEN 413
        WHEN k.parent_node_id = 333 THEN 333
        ELSE k.parent_node_id
    END,
    100,
    m.ok_core_user_id
FROM forest_platform.knowledge_explorer k
JOIN tmp_mig_nodes n
  ON n.knowledge_node_id = k.knowledge_node_id
LEFT JOIN tmp_user_map m
  ON m.forest_user_id = k.updated_by;


/* =========================================================
 * PREVIEW RESULTS
 * ========================================================= */

SELECT '=== MIGRATION SUMMARY ===' AS section;

SELECT
    (SELECT COUNT(*) FROM tmp_mig_nodes) AS knowledge_nodes,
    (SELECT COUNT(*) FROM tmp_exp_ids) AS experience_kfs,
    (SELECT COUNT(*) FROM tmp_disc_ids) AS discussion_kfs,
    (
        SELECT COUNT(*)
        FROM forest_platform.knowledge_explorer_fragment_links l
        JOIN forest_platform.knowledge_explorer k
          ON k.knowledge_node_id = l.knowledge_node_id
        WHERE k.knowledge_group_id = 100
          AND k.deleted = 0
    ) AS fragment_links,
    (
        SELECT COUNT(*)
        FROM forest_platform.shared_nodes s
        JOIN tmp_exp_ids x
          ON x.fragment_id = s.experience_knowledge_id
        WHERE s.knowledge_group_id = 100
          AND s.deleted = 0
    ) AS shared_nodes;


/* ユーザー対応一覧 */
SELECT '=== USER MAP ===' AS section;

SELECT
    r.forest_user_id,
    fu.name AS forest_name,
    fu.sso_sub,
    m.ok_core_user_id,
    m.mapping_type,
    ou.name AS current_ok_core_name
FROM tmp_relevant_users r
LEFT JOIN forest_platform.users fu
  ON fu.user_id = r.forest_user_id
LEFT JOIN tmp_user_map m
  ON m.forest_user_id = r.forest_user_id
LEFT JOIN ok_core.users ou
  ON ou.user_id = m.ok_core_user_id
ORDER BY r.forest_user_id;


/* 対応できていないユーザー */
SELECT '=== MISSING USER MAP ===' AS section;

SELECT
    r.forest_user_id,
    fu.name,
    fu.sso_sub
FROM tmp_relevant_users r
LEFT JOIN forest_platform.users fu
  ON fu.user_id = r.forest_user_id
LEFT JOIN tmp_user_map m
  ON m.forest_user_id = r.forest_user_id
WHERE m.ok_core_user_id IS NULL;


/* target user_id衝突確認 */
SELECT '=== PLANNED USER ID COLLISIONS ===' AS section;

SELECT
    m.forest_user_id,
    m.ok_core_user_id,
    m.mapping_type,
    ou.name AS occupied_by,
    ou.sso_sub
FROM tmp_user_map m
JOIN ok_core.users ou
  ON ou.user_id = m.ok_core_user_id
WHERE m.mapping_type IN ('new_sso', 'legacy', 'legacy_unknown');


/* KF ID衝突 */
SELECT '=== KF ID COLLISIONS ===' AS section;

SELECT
    'experience' AS type,
    COUNT(*) AS collisions
FROM tmp_exp_ids x
JOIN ok_core.experience_knowledges e
  ON e.experience_knowledge_id = x.fragment_id

UNION ALL

SELECT
    'discussion',
    COUNT(*)
FROM tmp_disc_ids x
JOIN ok_core.externalized_contents d
  ON d.externalized_contents_id = x.fragment_id;


/* 組織知ID衝突 */
SELECT '=== KNOWLEDGE NODE ID COLLISIONS ===' AS section;

SELECT
    COUNT(*) AS collisions
FROM tmp_mig_nodes n
JOIN ok_core.knowledge_explorer o
  ON o.knowledge_node_id = n.knowledge_node_id;


/* 移行後の組織知構造 */
SELECT '=== KNOWLEDGE NODE PREVIEW ===' AS section;

SELECT
    k.knowledge_node_id,
    p.target_parent_node_id,
    k.node_title,
    p.target_group_id,
    k.updated_by AS forest_updated_by,
    p.target_updated_by,
    CASE
        WHEN k.knowledge_node_id = 333
            THEN 'restore discussion category'
        ELSE 'copy'
    END AS migration_action
FROM forest_platform.knowledge_explorer k
JOIN tmp_node_preview p
  ON p.knowledge_node_id = k.knowledge_node_id
ORDER BY k.knowledge_node_id;


/* 親ノード参照切れ */
SELECT '=== ORPHAN PARENTS ===' AS section;

SELECT
    c.knowledge_node_id,
    c.target_parent_node_id
FROM tmp_node_preview c
LEFT JOIN tmp_node_preview p
  ON p.knowledge_node_id = c.target_parent_node_id
WHERE c.target_parent_node_id IS NOT NULL
  AND p.knowledge_node_id IS NULL;


/* KFリンク参照切れ */
SELECT '=== BROKEN FRAGMENT LINKS ===' AS section;

SELECT
    l.knowledge_node_id,
    l.fragment_source_type,
    l.fragment_source_id
FROM forest_platform.knowledge_explorer_fragment_links l
JOIN forest_platform.knowledge_explorer k
  ON k.knowledge_node_id = l.knowledge_node_id
LEFT JOIN tmp_exp_ids e
  ON l.fragment_source_type = 'experience'
 AND e.fragment_id = l.fragment_source_id
LEFT JOIN tmp_disc_ids d
  ON l.fragment_source_type = 'discussion'
 AND d.fragment_id = l.fragment_source_id
WHERE k.knowledge_group_id = 100
  AND k.deleted = 0
  AND (
      (l.fragment_source_type = 'experience' AND e.fragment_id IS NULL)
      OR
      (l.fragment_source_type = 'discussion' AND d.fragment_id IS NULL)
  );


/* shared_nodesに元々存在するexperience KF */
SELECT '=== SHARED EXPERIENCE KFS ===' AS section;

SELECT
    x.fragment_id,
    CASE WHEN s.id IS NULL THEN 'not shared'
         ELSE 'shared'
    END AS source_share_state
FROM tmp_exp_ids x
LEFT JOIN forest_platform.shared_nodes s
  ON s.experience_knowledge_id = x.fragment_id
 AND s.knowledge_group_id = 100
 AND s.deleted = 0
ORDER BY x.fragment_id;


/* 永続DBは一切変更していない */
SELECT 'PREVIEW ONLY - NO PERMANENT DATA WAS MODIFIED' AS result;
