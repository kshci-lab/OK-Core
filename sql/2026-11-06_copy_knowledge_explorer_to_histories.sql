-- 変更側: OK-Core DB / 履歴データ追加。構造変更は下記の適用SQLで先に行う。
-- knowledge_explorer と knowledge_explorer_fragment_links は変更しない。
-- 対応: MariaDB 10.6 / MySQL 5.7.22以降（JSON_ARRAYAGGを使用）。
-- 先に 2026-11-06_create_organizational_knowledge_histories.sql を適用する。
-- 作成済み履歴表には先に 2026-11-06_add_organizational_history_source_timestamps.sql を適用する。
-- phpMyAdminで対象のOK-Core DBを選び、ファイル全体を実行する。利用者IDの手動設定は不要。
-- コピー中は組織知の編集を止め、このSQLは一つの接続から実行する。

SET @org_history_operation_id := LOWER(REPLACE(UUID(), '-', ''));
SET @org_history_previous_time_zone := @@SESSION.time_zone;
SET @org_history_previous_group_concat_max_len := @@SESSION.group_concat_max_len;
SET SESSION time_zone = '+09:00';
-- MariaDBのJSON_ARRAYAGGの上限も拡張する。実行後に元へ戻す。
SET SESSION group_concat_max_len = 67108864;
SET @org_history_saved_at := NOW(3);

-- 適用前の確認。group未設定行・論理削除行も除外せず保存する。
SELECT
    'READY' AS copy_status,
    COUNT(*) AS source_rows,
    COALESCE(SUM(ke.deleted <> 0), 0) AS logically_deleted_rows,
    COALESCE(SUM(ke.knowledge_group_id IS NULL OR ke.knowledge_group_id <= 0), 0) AS unassigned_group_rows,
    COALESCE(SUM(ke.updated_by IS NULL), 0) AS null_saved_by_user_rows,
    COALESCE(SUM(NOT EXISTS (
        SELECT 1 FROM organizational_knowledge_histories h
        WHERE h.source_table = 'knowledge_explorer'
          AND h.organizational_knowledge_id = ke.knowledge_node_id
          AND h.event_type = 'baseline'
    )), 0) AS rows_without_initial_copy
FROM knowledge_explorer ke;

START TRANSACTION WITH CONSISTENT SNAPSHOT;

-- BEGIN INITIAL COPY
INSERT INTO organizational_knowledge_histories (
    source_table, organizational_knowledge_id, knowledge_group_id,
    event_type, operation_id, snapshot_version, snapshot_json,
    snapshot_sha256, saved_by_user_id, created_at, updated_at, saved_at
)
SELECT
    'knowledge_explorer', s.knowledge_node_id, s.history_group_id,
    'baseline', @org_history_operation_id, 1, s.snapshot_json,
    SHA2(s.snapshot_json, 256), s.history_saved_by_user_id,
    s.history_created_at, s.history_updated_at, @org_history_saved_at
FROM (
    SELECT
        ke.knowledge_node_id,
        ke.updated_by AS history_saved_by_user_id,
        ke.created_at AS history_created_at,
        ke.updated_at AS history_updated_at,
        CASE WHEN ke.knowledge_group_id > 0 THEN ke.knowledge_group_id ELSE 0 END AS history_group_id,
        JSON_OBJECT(
            'schema_version', 1,
            'source_table', 'knowledge_explorer',
            'knowledge', JSON_OBJECT(
                'knowledge_node_id', ke.knowledge_node_id,
                'parent_node_id', ke.parent_node_id,
                'node_title', ke.node_title,
                'knowledge_fragment_id', ke.knowledge_fragment_id,
                'comment', ke.comment,
                'tacto_when', ke.tacto_when,
                'tacto_what', ke.tacto_what,
                'tacto_why', ke.tacto_why,
                'organizational_basis', ke.organizational_basis,
                'node_type', ke.node_type,
                'created_at', ke.created_at,
                'updated_at', ke.updated_at,
                'updated_by', ke.updated_by,
                'deleted', ke.deleted,
                'sort_order', ke.sort_order,
                'knowledge_group_id', ke.knowledge_group_id
            ),
            'category', NULL,
            'fragment_links', JSON_EXTRACT(COALESCE((
                SELECT JSON_ARRAYAGG(JSON_OBJECT(
                    'id', fl.id,
                    'knowledge_node_id', fl.knowledge_node_id,
                    'fragment_source_type', fl.fragment_source_type,
                    'fragment_source_id', fl.fragment_source_id,
                    'display_order', fl.display_order,
                    'created_at', fl.created_at
                ))
                FROM knowledge_explorer_fragment_links fl
                WHERE fl.knowledge_node_id = ke.knowledge_node_id
            ), JSON_ARRAY()), '$'),
            'discussion_posts', NULL
        ) AS snapshot_json
    FROM knowledge_explorer ke
) s
WHERE NOT EXISTS (
      SELECT 1 FROM organizational_knowledge_histories h
      WHERE h.source_table = 'knowledge_explorer'
        AND h.organizational_knowledge_id = s.knowledge_node_id
        AND h.event_type = 'baseline'
  );
-- END INITIAL COPY

SET @org_history_inserted_rows := ROW_COUNT();
COMMIT;

SET SESSION time_zone = @org_history_previous_time_zone;
SET SESSION group_concat_max_len = @org_history_previous_group_concat_max_len;

-- 今回追加した件数と、初回コピーがまだ存在しない元レコードの件数を確認する。
SELECT
    'COMPLETED' AS copy_status,
    @org_history_operation_id AS operation_id,
    @org_history_inserted_rows AS inserted_rows,
    (SELECT COUNT(*) FROM knowledge_explorer ke
     WHERE NOT EXISTS (
         SELECT 1 FROM organizational_knowledge_histories h
         WHERE h.source_table = 'knowledge_explorer'
           AND h.organizational_knowledge_id = ke.knowledge_node_id
           AND h.event_type = 'baseline'
     )) AS remaining_rows;

-- 注意:
-- ・baselineは各元IDの初回コピー専用。既存のmanual/updated等の履歴は保持する。
-- ・saved_by_user_idは各元レコードのupdated_byをそのまま継承する（NULLも維持）。
-- ・created_at/updated_atは元の組織知の各日時。saved_atは今回コピーした日時。
--   usersの現時点の存在・有効状態による除外や、別IDへの置換は行わない。
-- ・再実行時は既にbaselineがある元IDをスキップする（最新状態への置換はしない）。
-- ・所属未設定の履歴はknowledge_group_id=0で保管する。元のNULL等はJSON内に保持する。
--   0は通常グループへの割当・閲覧許可を意味しない。元データの補正も行わない。
-- ・fragment_linksは配列で保存する。配列順に依存せずdisplay_order/idで順序を判断する。
-- ・現行の本体16カラム・リンク6カラムを保存する。別環境の構造が異なる場合は照合する。
-- ・JSON_ARRAYAGGの上限を超えるような大量リンクがある場合は、実行前に上限を調整する。
