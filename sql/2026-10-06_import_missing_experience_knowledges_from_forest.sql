-- Forest-platform の experience_knowledges 全行を、同じIDが存在しない場合だけ OK-Core に追加する。
-- forest_platform と ok_core が同じ MySQL サーバーにある現行環境向け。
-- 実行前に両DBをバックアップすること。既存のOK-Core行は更新しない。
-- deleted=1 の行も deleted フラグを保持して移す（画面には表示されない）。
-- Forest固有の context_package_id / stage_payload_json / source_revision は
-- OK-Coreの現行テーブルに列がないため、このSQLでは移さない。
-- 著者は sso_sub が一致する OK-Core ユーザーへ対応付ける。
-- SSOで対応できないユーザーは元の user_id を保持する（users 行は作らない）。
-- 実行前の照合で同じ数値IDに別人がいる場合は、このSQLを実行しない。
-- shared_nodes、users、組織知リンクはこのSQLの対象外。

SET NAMES utf8mb4;

-- 実行前の確認。2026-10-06のローカルDBでは source=24、既存=8、追加候補=16。
SELECT COUNT(*) AS forest_total
FROM forest_platform.experience_knowledges;

SELECT COUNT(*) AS missing_before
FROM forest_platform.experience_knowledges AS f
WHERE NOT EXISTS (
    SELECT 1
    FROM ok_core.experience_knowledges AS o
    WHERE o.experience_knowledge_id = f.experience_knowledge_id
);

-- 著者をOK-Coreのusersへ対応付けられない追加候補を確認する。
-- 2026-10-06のローカルDBでは1件（KF 11151）。
SELECT f.experience_knowledge_id, f.user_id AS forest_user_id
FROM forest_platform.experience_knowledges AS f
LEFT JOIN forest_platform.users AS forest_user
    ON forest_user.user_id = f.user_id
LEFT JOIN ok_core.users AS ok_user
    ON ok_user.sso_sub = forest_user.sso_sub
   AND forest_user.sso_sub IS NOT NULL
   AND forest_user.sso_sub <> ''
LEFT JOIN ok_core.users AS same_id_user
    ON same_id_user.user_id = f.user_id
WHERE NOT EXISTS (
    SELECT 1
    FROM ok_core.experience_knowledges AS o
    WHERE o.experience_knowledge_id = f.experience_knowledge_id
)
AND ok_user.user_id IS NULL
AND same_id_user.user_id IS NULL;

-- 既存の同じ数値IDに別人がいる場合は行が出る。その場合は停止してユーザー対応を決める。
-- 2026-10-06のローカルDBでは0件。
SELECT f.experience_knowledge_id, f.user_id AS forest_user_id
FROM forest_platform.experience_knowledges AS f
INNER JOIN forest_platform.users AS forest_user
    ON forest_user.user_id = f.user_id
INNER JOIN ok_core.users AS same_id_user
    ON same_id_user.user_id = f.user_id
LEFT JOIN ok_core.users AS ok_user
    ON ok_user.sso_sub = forest_user.sso_sub
   AND forest_user.sso_sub IS NOT NULL
   AND forest_user.sso_sub <> ''
WHERE NOT EXISTS (
    SELECT 1
    FROM ok_core.experience_knowledges AS o
    WHERE o.experience_knowledge_id = f.experience_knowledge_id
)
AND ok_user.user_id IS NULL
AND (NOT (forest_user.sso_sub <=> same_id_user.sso_sub)
     OR forest_user.name <> same_id_user.name);

START TRANSACTION;

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
    f.experience_knowledge_id,
    f.remarked_utterance_id,
    f.thought_experience_node_id,
    f.experience_type,
    f.used_remarked_utterance,
    f.selected_contents,
    f.knowledge_fragment_content,
    COALESCE(ok_user.user_id, f.user_id),
    f.stage1,
    f.stage2,
    f.stage3,
    f.created_at,
    f.updated_at,
    f.deleted,
    f.discussed
FROM forest_platform.experience_knowledges AS f
LEFT JOIN forest_platform.users AS forest_user
    ON forest_user.user_id = f.user_id
LEFT JOIN ok_core.users AS ok_user
    ON ok_user.sso_sub = forest_user.sso_sub
   AND forest_user.sso_sub IS NOT NULL
   AND forest_user.sso_sub <> ''
WHERE NOT EXISTS (
    SELECT 1
    FROM ok_core.experience_knowledges AS o
    WHERE o.experience_knowledge_id = f.experience_knowledge_id
);

SELECT ROW_COUNT() AS inserted_count;

COMMIT;

-- 追加候補が0件になったことを確認。再実行時の inserted_count は0件になる。
SELECT COUNT(*) AS missing_after
FROM forest_platform.experience_knowledges AS f
WHERE NOT EXISTS (
    SELECT 1
    FROM ok_core.experience_knowledges AS o
    WHERE o.experience_knowledge_id = f.experience_knowledge_id
);
