-- 変更側: OK-Core DB。knowledge_explorerとForest-platformは変更しない。
-- カラム追加2件: organizational_knowledge_histories.created_at / updated_at (DATETIME(3) NULL)
-- カラム変更1件: saved_by_user_idをNULL可にする（前版で適用済みの場合は同じ定義）。
-- 既存履歴の日時をその履歴自身のsnapshot_jsonから補完する。現在のexplorerから上書きしない。
-- 過去のbefore_updateは削除しない。新しい保存処理で以後の生成を停止する。
-- phpMyAdminで対象のOK-Core DBを選び、利用者が実行する。MySQL 5.7 / MariaDB 10.6対応。
-- DDLは暗黙COMMITを伴うため、バックアップ取得後・コード配備前に適用する。

SET @org_history_add_created_at := IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organizational_knowledge_histories' AND COLUMN_NAME = 'created_at'),
    'SELECT ''created_at already exists'' AS schema_status',
    'ALTER TABLE organizational_knowledge_histories ADD COLUMN created_at DATETIME(3) NULL DEFAULT NULL COMMENT ''保存した版の組織知本体の作成日時'' AFTER saved_by_user_id'
);
PREPARE org_history_add_created_stmt FROM @org_history_add_created_at;
EXECUTE org_history_add_created_stmt;
DEALLOCATE PREPARE org_history_add_created_stmt;

SET @org_history_add_updated_at := IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organizational_knowledge_histories' AND COLUMN_NAME = 'updated_at'),
    'SELECT ''updated_at already exists'' AS schema_status',
    'ALTER TABLE organizational_knowledge_histories ADD COLUMN updated_at DATETIME(3) NULL DEFAULT NULL COMMENT ''保存した版の組織知本体の更新日時。自動更新しない'' AFTER created_at'
);
PREPARE org_history_add_updated_stmt FROM @org_history_add_updated_at;
EXECUTE org_history_add_updated_stmt;
DEALLOCATE PREPARE org_history_add_updated_stmt;

ALTER TABLE organizational_knowledge_histories
    MODIFY COLUMN saved_by_user_id INT NULL DEFAULT NULL;

-- JSONに日時がない場合はNULLのまま。既に埋まっている日時・保存日時・JSON・ハッシュは変更しない。
START TRANSACTION;
-- BEGIN HISTORY DATE BACKFILL
UPDATE organizational_knowledge_histories
SET created_at = COALESCE(created_at,
        CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(snapshot_json, '$.knowledge.created_at')), 'null') AS DATETIME(3))),
    updated_at = COALESCE(updated_at,
        CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(snapshot_json, '$.knowledge.updated_at')), 'null') AS DATETIME(3)))
WHERE (created_at IS NULL OR updated_at IS NULL) AND JSON_VALID(snapshot_json);
-- END HISTORY DATE BACKFILL
COMMIT;

SHOW COLUMNS FROM organizational_knowledge_histories;
SELECT COUNT(*) AS history_rows,
       COALESCE(SUM(created_at IS NULL), 0) AS missing_source_created_at,
       COALESCE(SUM(updated_at IS NULL), 0) AS missing_source_updated_at
FROM organizational_knowledge_histories;
