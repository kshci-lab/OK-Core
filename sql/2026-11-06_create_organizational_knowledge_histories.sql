-- 変更側: OK-Core DB / 変更種別: テーブル追加1件。既存テーブル・データは変更しない。
-- phpMyAdminで対象のOK-Core DBを選択して実行する（information_schemaには実行しない）。
-- MariaDB 10.6 / MySQL 8.0対応。利用者が適用し、アプリはDDLを実行しない。
CREATE TABLE IF NOT EXISTS organizational_knowledge_histories (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_table VARCHAR(64) NOT NULL COMMENT 'organizational_knowledges または knowledge_explorer',
    organizational_knowledge_id BIGINT UNSIGNED NOT NULL COMMENT 'source_table内の元レコードID',
    knowledge_group_id INT NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    operation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    snapshot_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    snapshot_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    saved_by_user_id INT NULL DEFAULT NULL,
    created_at DATETIME(3) NULL DEFAULT NULL COMMENT '保存した版の組織知本体の作成日時',
    updated_at DATETIME(3) NULL DEFAULT NULL COMMENT '保存した版の組織知本体の更新日時。自動更新しない',
    saved_at DATETIME(3) NOT NULL,
    PRIMARY KEY (history_id),
    KEY idx_org_history_source (source_table, organizational_knowledge_id, history_id),
    KEY idx_org_history_group (knowledge_group_id, saved_at, history_id),
    KEY idx_org_history_operation (operation_id, history_id),
    CONSTRAINT chk_org_history_json CHECK (JSON_VALID(snapshot_json)),
    CONSTRAINT chk_org_history_source CHECK (source_table IN ('organizational_knowledges', 'knowledge_explorer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='組織知の追記型バックアップ。元レコード削除後も保持し、CASCADE削除しない';

-- 作成確認（読み取りのみ）
SHOW COLUMNS FROM organizational_knowledge_histories;
