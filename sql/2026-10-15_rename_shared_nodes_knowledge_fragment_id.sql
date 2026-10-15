-- Rename only shared_nodes' reference column; keep all values and indexes.
-- MySQL 5.7 / MariaDB 10.6 compatible. Back up shared_nodes before applying.
USE `ok_core`;
SET NAMES utf8mb4;

SET @shared_old_column = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'shared_nodes'
      AND column_name = 'experience_knowledge_id'
);
SET @shared_new_column = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'shared_nodes'
      AND column_name = 'knowledge_fragment_id'
);
SET @shared_column_definition = (
    SELECT CONCAT(COLUMN_TYPE,
        IF(IS_NULLABLE = 'YES', ' NULL', ' NOT NULL'),
        IF(COLUMN_DEFAULT IS NOT NULL, CONCAT(' DEFAULT ', QUOTE(COLUMN_DEFAULT)),
            IF(IS_NULLABLE = 'YES', ' DEFAULT NULL', '')),
        IF(COLUMN_COMMENT = '', '', CONCAT(' COMMENT ', QUOTE(COLUMN_COMMENT))))
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'shared_nodes'
      AND column_name = 'experience_knowledge_id'
);
SET @shared_rename_sql = CASE
    WHEN @shared_old_column = 1 AND @shared_new_column = 0 THEN
        CONCAT('ALTER TABLE shared_nodes CHANGE COLUMN experience_knowledge_id knowledge_fragment_id ',
            @shared_column_definition)
    WHEN @shared_old_column = 0 AND @shared_new_column = 1 THEN
        'SELECT ''shared_nodes is already migrated'' AS migration_status'
    ELSE
        'SELECT ''ERROR: expected exactly one of the old/new reference columns; inspect shared_nodes'' AS migration_status'
END;
PREPARE shared_rename_stmt FROM @shared_rename_sql;
EXECUTE shared_rename_stmt;
DEALLOCATE PREPARE shared_rename_stmt;

SHOW COLUMNS FROM shared_nodes;
