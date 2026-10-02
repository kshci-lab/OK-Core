/*
 * seta-lab migration: forest_platform -> ok_core
 * START TRANSACTIONするがCOMMITはしない。
 * 実行結果を確認後、手動でCOMMITまたはROLLBACKする。
 */

USE ok_core;

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_mig_nodes;
DROP TEMPORARY TABLE IF EXISTS tmp_exp_ids;
DROP TEMPORARY TABLE IF EXISTS tmp_disc_ids;
DROP TEMPORARY TABLE IF EXISTS tmp_relevant_users;
DROP TEMPORARY TABLE IF EXISTS tmp_user_map;

CREATE TEMPORARY TABLE tmp_mig_nodes (
    knowledge_node_id INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

CREATE TEMPORARY TABLE tmp_exp_ids (
    fragment_id INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

CREATE TEMPORARY TABLE tmp_disc_ids (
    fragment_id INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

CREATE TEMPORARY TABLE tmp_relevant_users (
    forest_user_id INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

CREATE TEMPORARY TABLE tmp_user_map (
    forest_user_id INT NOT NULL PRIMARY KEY,
    ok_core_user_id INT NOT NULL,
    mapping_type VARCHAR(32) NOT NULL
) ENGINE=InnoDB;


/* 移行対象組織知 */
INSERT INTO tmp_mig_nodes
SELECT knowledge_node_id
FROM forest_platform.knowledge_explorer
WHERE knowledge_group_id = 100
  AND deleted = 0;

INSERT INTO tmp_mig_nodes VALUES (333);


/* experience KF */
INSERT INTO tmp_exp_ids
SELECT DISTINCT l.fragment_source_id
FROM forest_platform.knowledge_explorer_fragment_links l
JOIN forest_platform.knowledge_explorer k
  ON k.knowledge_node_id = l.knowledge_node_id
WHERE k.knowledge_group_id = 100
  AND k.deleted = 0
  AND l.fragment_source_type = 'experience';


/* discussion KF */
INSERT INTO tmp_disc_ids
SELECT DISTINCT l.fragment_source_id
FROM forest_platform.knowledge_explorer_fragment_links l
JOIN forest_platform.knowledge_explorer k
  ON k.knowledge_node_id = l.knowledge_node_id
WHERE k.knowledge_group_id = 100
  AND k.deleted = 0
  AND l.fragment_source_type = 'discussion';


/* 必要ユーザー */
INSERT IGNORE INTO tmp_relevant_users
SELECT user_id
FROM forest_platform.kgroup_user_link
WHERE group_id = 100
  AND deleted = 0;

INSERT IGNORE INTO tmp_relevant_users
SELECT k.updated_by
FROM forest_platform.knowledge_explorer k
JOIN tmp_mig_nodes n
  ON n.knowledge_node_id = k.knowledge_node_id;

INSERT IGNORE INTO tmp_relevant_users
SELECT e.user_id
FROM forest_platform.experience_knowledges e
JOIN tmp_exp_ids x
  ON x.fragment_id = e.experience_knowledge_id;

INSERT IGNORE INTO tmp_relevant_users
SELECT d.user_id
FROM forest_platform.externalized_contents d
JOIN tmp_disc_ids x
  ON x.fragment_id = d.externalized_contents_id;

INSERT IGNORE INTO tmp_relevant_users VALUES (0);


/* 既存SSOユーザー */
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


/* SSOあり・OK-Core未登録 */
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


/* SSOなし旧ユーザー */
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

INSERT IGNORE INTO tmp_user_map
VALUES (0, 0, 'legacy_unknown');


/* 未登録ユーザーをOK-Coreへ作成 */
INSERT INTO ok_core.users (
    user_id,
    sso_sub,
    name,
    login_time,
    sso_user_id,
    sso_username,
    email,
    display_name,
    role,
    is_active,
    sso_claims_json,
    sso_updated_at
)
SELECT
    m.ok_core_user_id,
    fu.sso_sub,
    fu.name,
    fu.login_time,
    fu.sso_user_id,
    fu.sso_username,
    fu.email,
    fu.display_name,
    fu.role,
    fu.is_active,
    fu.sso_claims_json,
    fu.sso_updated_at
FROM tmp_user_map m
JOIN forest_platform.users fu
  ON fu.user_id = m.forest_user_id
WHERE m.mapping_type IN ('new_sso', 'legacy');


/* user_id=0 placeholder */
INSERT INTO ok_core.users (
    user_id,
    sso_sub,
    name,
    role,
    is_active
)
VALUES (
    0,
    NULL,
    '旧データ・作成者不明',
    'legacy',
    1
);


/* seta-lab */
INSERT INTO ok_core.knowledge_groups (
    group_id,
    name,
    creater,
    created_at,
    deleted
)
SELECT
    g.group_id,
    g.name,
    m.ok_core_user_id,
    g.created_at,
    g.deleted
FROM forest_platform.knowledge_groups g
JOIN tmp_user_map m
  ON m.forest_user_id = g.creater
WHERE g.group_id = 100;


/* seta-lab所属 */
INSERT INTO ok_core.kgroup_user_link (
    group_id,
    user_id,
    role,
    created_at,
    deleted
)
SELECT
    l.group_id,
    m.ok_core_user_id,
    l.role,
    l.created_at,
    l.deleted
FROM forest_platform.kgroup_user_link l
JOIN tmp_user_map m
  ON m.forest_user_id = l.user_id
WHERE l.group_id = 100
  AND l.deleted = 0;


/* experience KF 12件 */
INSERT INTO ok_core.experience_knowledges (
    experience_knowledge_id,
    remarked_utterance_id,
    thought_experience_node_id,
    experience_type,
    used_remarked_utterance,
    selected_contents,
    knowledge_fragment_content,
    user_id,
    stage1,
    stage2,
    stage3,
    created_at,
    updated_at,
    deleted,
    discussed
)
SELECT
    e.experience_knowledge_id,
    e.remarked_utterance_id,
    e.thought_experience_node_id,
    e.experience_type,
    e.used_remarked_utterance,
    e.selected_contents,
    e.knowledge_fragment_content,
    m.ok_core_user_id,
    e.stage1,
    e.stage2,
    e.stage3,
    e.created_at,
    e.updated_at,
    e.deleted,
    e.discussed
FROM forest_platform.experience_knowledges e
JOIN tmp_exp_ids x
  ON x.fragment_id = e.experience_knowledge_id
JOIN tmp_user_map m
  ON m.forest_user_id = e.user_id;


/* 元々共有されていたexperience KFだけ */
INSERT INTO ok_core.shared_nodes (
    experience_knowledge_id,
    knowledge_group_id,
    created_at,
    updated_at,
    deleted
)
SELECT
    s.experience_knowledge_id,
    s.knowledge_group_id,
    s.created_at,
    s.updated_at,
    s.deleted
FROM forest_platform.shared_nodes s
JOIN tmp_exp_ids x
  ON x.fragment_id = s.experience_knowledge_id
WHERE s.knowledge_group_id = 100
  AND s.deleted = 0;


/* discussion KF 9件 */
INSERT INTO ok_core.externalized_contents (
    externalized_contents_id,
    remarked_utterance_id,
    thought_experience_node_id,
    externalized_type,
    used_remarked_utterance,
    selected_contents,
    knowledge_fragment_content,
    user_id,
    group_id,
    source_system,
    source_type,
    source_id,
    source_user_ref,
    raw_payload,
    stage1,
    stage2,
    stage3,
    created_at,
    updated_at,
    deleted,
    discussed
)
SELECT
    d.externalized_contents_id,
    d.remarked_utterance_id,
    d.thought_experience_node_id,
    d.externalized_type,
    d.used_remarked_utterance,
    d.selected_contents,
    d.knowledge_fragment_content,
    m.ok_core_user_id,
    100,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    d.stage1,
    d.stage2,
    d.stage3,
    d.created_at,
    d.updated_at,
    d.deleted,
    d.discussed
FROM forest_platform.externalized_contents d
JOIN tmp_disc_ids x
  ON x.fragment_id = d.externalized_contents_id
JOIN tmp_user_map m
  ON m.forest_user_id = d.user_id;


/* 組織知19件 */
INSERT INTO ok_core.knowledge_explorer (
    knowledge_node_id,
    parent_node_id,
    node_title,
    knowledge_fragment_id,
    comment,
    tacto_when,
    tacto_what,
    tacto_why,
    organizational_basis,
    node_type,
    created_at,
    updated_at,
    updated_by,
    deleted,
    sort_order,
    knowledge_group_id
)
SELECT
    k.knowledge_node_id,
    CASE
        WHEN k.knowledge_node_id = 333 THEN NULL
        WHEN k.parent_node_id = 111 THEN 412
        WHEN k.parent_node_id = 222 THEN 413
        WHEN k.parent_node_id = 333 THEN 333
        ELSE k.parent_node_id
    END,
    k.node_title,
    k.knowledge_fragment_id,
    k.comment,
    k.tacto_when,
    k.tacto_what,
    k.tacto_why,
    k.organizational_basis,
    k.node_type,
    k.created_at,
    k.updated_at,
    m.ok_core_user_id,
    CASE
        WHEN k.knowledge_node_id = 333 THEN 0
        ELSE k.deleted
    END,
    k.sort_order,
    100
FROM forest_platform.knowledge_explorer k
JOIN tmp_mig_nodes n
  ON n.knowledge_node_id = k.knowledge_node_id
JOIN tmp_user_map m
  ON m.forest_user_id = k.updated_by;


/* 組織知-KFリンク25件 */
INSERT INTO ok_core.knowledge_explorer_fragment_links (
    knowledge_node_id,
    fragment_source_type,
    fragment_source_id,
    display_order,
    created_at
)
SELECT
    l.knowledge_node_id,
    l.fragment_source_type,
    l.fragment_source_id,
    l.display_order,
    l.created_at
FROM forest_platform.knowledge_explorer_fragment_links l
JOIN forest_platform.knowledge_explorer k
  ON k.knowledge_node_id = l.knowledge_node_id
WHERE k.knowledge_group_id = 100
  AND k.deleted = 0;


/* =====================
 * 検証
 * ===================== */

SELECT '=== MIGRATION RESULT ===' AS section;

SELECT
    (SELECT COUNT(*)
       FROM ok_core.knowledge_groups
      WHERE group_id = 100
        AND deleted = 0) AS seta_lab,

    (SELECT COUNT(*)
       FROM ok_core.kgroup_user_link
      WHERE group_id = 100
        AND deleted = 0) AS members,

    (SELECT COUNT(*)
       FROM ok_core.experience_knowledges e
       JOIN tmp_exp_ids x
         ON x.fragment_id = e.experience_knowledge_id) AS experience_kfs,

    (SELECT COUNT(*)
       FROM ok_core.externalized_contents d
       JOIN tmp_disc_ids x
         ON x.fragment_id = d.externalized_contents_id) AS discussion_kfs,

    (SELECT COUNT(*)
       FROM ok_core.shared_nodes s
       JOIN tmp_exp_ids x
         ON x.fragment_id = s.experience_knowledge_id
      WHERE s.knowledge_group_id = 100
        AND s.deleted = 0) AS shared_nodes,

    (SELECT COUNT(*)
       FROM ok_core.knowledge_explorer
      WHERE knowledge_group_id = 100
        AND deleted = 0) AS knowledge_nodes,

    (SELECT COUNT(*)
       FROM ok_core.knowledge_explorer_fragment_links l
       JOIN ok_core.knowledge_explorer k
         ON k.knowledge_node_id = l.knowledge_node_id
      WHERE k.knowledge_group_id = 100
        AND k.deleted = 0) AS fragment_links;


SELECT '=== ORPHAN PARENTS ===' AS section;

SELECT
    c.knowledge_node_id,
    c.parent_node_id,
    c.node_title
FROM ok_core.knowledge_explorer c
LEFT JOIN ok_core.knowledge_explorer p
  ON p.knowledge_node_id = c.parent_node_id
WHERE c.knowledge_group_id = 100
  AND c.deleted = 0
  AND c.parent_node_id IS NOT NULL
  AND p.knowledge_node_id IS NULL;


SELECT '=== BROKEN FRAGMENT LINKS ===' AS section;

SELECT
    l.knowledge_node_id,
    l.fragment_source_type,
    l.fragment_source_id
FROM ok_core.knowledge_explorer_fragment_links l
JOIN ok_core.knowledge_explorer k
  ON k.knowledge_node_id = l.knowledge_node_id
LEFT JOIN ok_core.experience_knowledges e
  ON l.fragment_source_type = 'experience'
 AND e.experience_knowledge_id = l.fragment_source_id
LEFT JOIN ok_core.externalized_contents d
  ON l.fragment_source_type = 'discussion'
 AND d.externalized_contents_id = l.fragment_source_id
WHERE k.knowledge_group_id = 100
  AND k.deleted = 0
  AND (
      (l.fragment_source_type = 'experience'
       AND e.experience_knowledge_id IS NULL)
      OR
      (l.fragment_source_type = 'discussion'
       AND d.externalized_contents_id IS NULL)
  );


SELECT '=== BROKEN KF USERS ===' AS section;

SELECT
    'experience' AS type,
    e.experience_knowledge_id AS fragment_id,
    e.user_id
FROM ok_core.experience_knowledges e
JOIN tmp_exp_ids x
  ON x.fragment_id = e.experience_knowledge_id
LEFT JOIN ok_core.users u
  ON u.user_id = e.user_id
WHERE u.user_id IS NULL

UNION ALL

SELECT
    'discussion',
    d.externalized_contents_id,
    d.user_id
FROM ok_core.externalized_contents d
JOIN tmp_disc_ids x
  ON x.fragment_id = d.externalized_contents_id
LEFT JOIN ok_core.users u
  ON u.user_id = d.user_id
WHERE u.user_id IS NULL;


SELECT '=== BROKEN KNOWLEDGE USERS ===' AS section;

SELECT
    k.knowledge_node_id,
    k.updated_by
FROM ok_core.knowledge_explorer k
LEFT JOIN ok_core.users u
  ON u.user_id = k.updated_by
WHERE k.knowledge_group_id = 100
  AND k.deleted = 0
  AND u.user_id IS NULL;


SELECT '=== UNSHARED KFS CHECK ===' AS section;

SELECT
    e.experience_knowledge_id,
    e.knowledge_fragment_content,
    COUNT(s.id) AS shared_count
FROM ok_core.experience_knowledges e
LEFT JOIN ok_core.shared_nodes s
  ON s.experience_knowledge_id = e.experience_knowledge_id
 AND s.knowledge_group_id = 100
 AND s.deleted = 0
WHERE e.experience_knowledge_id IN (11142,11145)
GROUP BY
    e.experience_knowledge_id,
    e.knowledge_fragment_content;


/*
 * ここではCOMMITしない。
 * 正しければ手動で:
 * COMMIT;
 *
 * 問題があれば:
 * ROLLBACK;
 */
SELECT 'TRANSACTION IS STILL OPEN - DO NOT EXIT BEFORE COMMIT OR ROLLBACK' AS result;
