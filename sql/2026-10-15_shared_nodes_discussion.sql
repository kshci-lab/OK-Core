-- Apply the shared_nodes column rename migration first. Back up shared_nodes.
-- MySQL 5.7 / MariaDB 10.6 compatible. Existing shares are preserved.
USE ok_core;
SET NAMES utf8mb4;

SET @shared_type_added = (
  SELECT IF(COUNT(*) = 0, 1, 0) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'shared_nodes'
    AND column_name = 'fragment_source_type'
);
SET @shared_sql = IF(@shared_type_added = 1,
  'ALTER TABLE shared_nodes ADD COLUMN fragment_source_type VARCHAR(32) NOT NULL DEFAULT ''experience'' AFTER id',
  'SELECT ''fragment_source_type already exists'' AS migration_status');
PREPARE shared_stmt FROM @shared_sql;
EXECUTE shared_stmt;
DEALLOCATE PREPARE shared_stmt;

-- Detect every old two-column unique key, regardless of its name.
SET @shared_drop_indexes = (
  SELECT GROUP_CONCAT(CONCAT('DROP INDEX `', REPLACE(index_name, '`', '``'), '`') SEPARATOR ', ')
  FROM (
    SELECT index_name FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'shared_nodes'
      AND non_unique = 0 AND index_name <> 'PRIMARY'
    GROUP BY index_name
    HAVING COUNT(*) = 2
       AND SUM(column_name = 'knowledge_fragment_id') = 1
       AND SUM(column_name = 'knowledge_group_id') = 1
  ) old_indexes
);
SET @shared_typed_index_exists = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'shared_nodes'
    AND index_name = 'ux_shared_nodes_typed_group'
);
SET @shared_add_index = IF(@shared_typed_index_exists = 0,
  'ADD UNIQUE KEY ux_shared_nodes_typed_group (fragment_source_type, knowledge_fragment_id, knowledge_group_id)', NULL);
SET @shared_index_changes = CONCAT_WS(', ', @shared_add_index, @shared_drop_indexes);
SET @shared_sql = IF(@shared_index_changes <> '',
  CONCAT('ALTER TABLE shared_nodes ', @shared_index_changes),
  'SELECT ''typed shared index already exists'' AS migration_status');
PREPARE shared_stmt FROM @shared_sql;
EXECUTE shared_stmt;
DEALLOCATE PREPARE shared_stmt;

-- On the first migration only, recover manually registered discussion shares
-- whose ID exists only in externalized_contents. If both tables contain the ID,
-- preserve the legacy experience interpretation and backfill discussion below.
UPDATE shared_nodes sn
JOIN externalized_contents ec ON ec.externalized_contents_id = sn.knowledge_fragment_id
LEFT JOIN experience_knowledges ek ON ek.experience_knowledge_id = sn.knowledge_fragment_id
SET sn.fragment_source_type = 'discussion', sn.updated_at = sn.updated_at
WHERE @shared_type_added = 1 AND ek.experience_knowledge_id IS NULL;

-- Copy known legacy destinations only. Do not infer a destination for group_id=0.
-- Do not revive an explicitly deleted shared_nodes row when this SQL is rerun.
INSERT INTO shared_nodes
  (fragment_source_type, knowledge_fragment_id, knowledge_group_id, deleted, created_at, updated_at)
SELECT 'discussion', ec.externalized_contents_id, ec.group_id, ec.deleted, ec.created_at, ec.updated_at
FROM externalized_contents ec
JOIN knowledge_groups kg ON kg.group_id = ec.group_id AND kg.deleted = 0
WHERE ec.group_id > 0 AND NOT EXISTS (
  SELECT 1 FROM shared_nodes sn
  WHERE sn.fragment_source_type = 'discussion'
    AND sn.knowledge_fragment_id = ec.externalized_contents_id
    AND sn.knowledge_group_id = ec.group_id
);

SELECT fragment_source_type, knowledge_group_id, deleted, COUNT(*) AS shared_count
FROM shared_nodes GROUP BY fragment_source_type, knowledge_group_id, deleted;
