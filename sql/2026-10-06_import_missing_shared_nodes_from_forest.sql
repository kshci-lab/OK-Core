-- Forest-platform の KF 共有関係を OK-Core へ追加する。
-- 同一 MySQL サーバー上の forest_platform / ok_core を想定する。
-- 前提: 対象 KF は先に experience_knowledges へ移行済みであること。
-- 本SQLは既存行を更新せず、Forest の shared_nodes.id も引き継がない。
-- OK-Core 側の id は AUTO_INCREMENT で採番する。
-- グループIDと名前が両DBで一致する有効グループだけを対象とし、
-- 参照先 KF が OK-Core にない行は移さない。
-- グループ1をグループ101へ自動的に対応付けない。

SET NAMES utf8mb4;

-- 1. 移行対象外のグループを確認する。現行DBでは Forest の group_id=1 が該当。
SELECT f.knowledge_group_id, fg.name AS forest_group_name,
       COUNT(*) AS forest_shared_rows
FROM forest_platform.shared_nodes AS f
JOIN forest_platform.knowledge_groups AS fg
  ON fg.group_id = f.knowledge_group_id
LEFT JOIN ok_core.knowledge_groups AS og
  ON og.group_id = fg.group_id AND og.name = fg.name AND og.deleted = 0
WHERE og.group_id IS NULL
GROUP BY f.knowledge_group_id, fg.name;

-- 2. OK-Core に KF 本体がない共有関係を確認する。
-- 前の experience_knowledges 移行SQLを実行後、再確認すること。
SELECT f.id AS forest_shared_node_id, f.knowledge_group_id,
       f.experience_knowledge_id
FROM forest_platform.shared_nodes AS f
JOIN forest_platform.knowledge_groups AS fg
  ON fg.group_id = f.knowledge_group_id AND fg.deleted = 0
JOIN ok_core.knowledge_groups AS og
  ON og.group_id = fg.group_id AND og.name = fg.name AND og.deleted = 0
LEFT JOIN ok_core.experience_knowledges AS ek
  ON ek.experience_knowledge_id = f.experience_knowledge_id
WHERE ek.experience_knowledge_id IS NULL
ORDER BY f.knowledge_group_id, f.experience_knowledge_id;

-- 3. Forest 側に同一 KF・グループの重複行があれば確認する。
-- 現行DBでは group_id=100 / KF=11149 に2行ある。
-- 下の INSERT は updated_at が新しい1行を選び、同日時なら id が大きい行を選ぶ。
SELECT f.knowledge_group_id, f.experience_knowledge_id,
       COUNT(*) AS forest_duplicate_count
FROM forest_platform.shared_nodes AS f
GROUP BY f.knowledge_group_id, f.experience_knowledge_id
HAVING COUNT(*) > 1;

-- 4. 追加予定件数。現在のローカルDBでは8組。
-- 前のKF移行SQLによって group_id=100 / KF=11139 が入ると9組になる。
SELECT COUNT(*) AS insertable_pairs
FROM forest_platform.shared_nodes AS f
JOIN forest_platform.knowledge_groups AS fg
  ON fg.group_id = f.knowledge_group_id AND fg.deleted = 0
JOIN ok_core.knowledge_groups AS og
  ON og.group_id = fg.group_id AND og.name = fg.name AND og.deleted = 0
JOIN ok_core.experience_knowledges AS ek
  ON ek.experience_knowledge_id = f.experience_knowledge_id
WHERE NOT EXISTS (
    SELECT 1 FROM ok_core.shared_nodes AS existing
    WHERE existing.experience_knowledge_id = f.experience_knowledge_id
      AND existing.knowledge_group_id = f.knowledge_group_id
)
AND NOT EXISTS (
    SELECT 1 FROM forest_platform.shared_nodes AS newer
    WHERE newer.experience_knowledge_id = f.experience_knowledge_id
      AND newer.knowledge_group_id = f.knowledge_group_id
      AND (newer.updated_at > f.updated_at
           OR (newer.updated_at = f.updated_at AND newer.id > f.id))
);

START TRANSACTION;

INSERT INTO ok_core.shared_nodes (
    experience_knowledge_id, knowledge_group_id,
    created_at, updated_at, deleted
)
SELECT f.experience_knowledge_id, f.knowledge_group_id,
       f.created_at, f.updated_at, f.deleted
FROM forest_platform.shared_nodes AS f
JOIN forest_platform.knowledge_groups AS fg
  ON fg.group_id = f.knowledge_group_id AND fg.deleted = 0
JOIN ok_core.knowledge_groups AS og
  ON og.group_id = fg.group_id AND og.name = fg.name AND og.deleted = 0
JOIN ok_core.experience_knowledges AS ek
  ON ek.experience_knowledge_id = f.experience_knowledge_id
WHERE NOT EXISTS (
    SELECT 1 FROM ok_core.shared_nodes AS existing
    WHERE existing.experience_knowledge_id = f.experience_knowledge_id
      AND existing.knowledge_group_id = f.knowledge_group_id
)
AND NOT EXISTS (
    SELECT 1 FROM forest_platform.shared_nodes AS newer
    WHERE newer.experience_knowledge_id = f.experience_knowledge_id
      AND newer.knowledge_group_id = f.knowledge_group_id
      AND (newer.updated_at > f.updated_at
           OR (newer.updated_at = f.updated_at AND newer.id > f.id))
);

SELECT ROW_COUNT() AS inserted_count;

COMMIT;

-- 再実行時の insertable_pairs は0件になる（移行対象のグループ・KFについて）。
-- 表示件数は deleted=0、KF本文あり、KF本体も deleted=0 の条件で確認する。
SELECT sn.knowledge_group_id, COUNT(DISTINCT sn.experience_knowledge_id) AS visible_experience_kfs
FROM ok_core.shared_nodes AS sn
JOIN ok_core.experience_knowledges AS ek
  ON ek.experience_knowledge_id = sn.experience_knowledge_id
WHERE sn.deleted = 0 AND ek.deleted = 0
  AND ek.knowledge_fragment_content IS NOT NULL
  AND LENGTH(TRIM(ek.knowledge_fragment_content)) > 0
GROUP BY sn.knowledge_group_id
ORDER BY sn.knowledge_group_id;
