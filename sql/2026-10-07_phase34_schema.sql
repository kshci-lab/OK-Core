-- Run once on the current OK-Core schema, after backing up the two tables.
-- Do not import Forest's position rows here: their group and source identity
-- require separate reconciliation.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ok_core.kf_source_mappings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  source_system VARCHAR(64) NOT NULL,
  external_kf_id VARCHAR(191) NOT NULL,
  experience_knowledge_id INT NULL,
  source_revision BIGINT NOT NULL DEFAULT 0,
  source_deleted_at DATETIME NULL,
  snapshot_json LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_source_fragment (source_system, external_kf_id),
  UNIQUE KEY ux_local_experience (experience_knowledge_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Existing position rows use externalized_contents_id for experience KF IDs.
-- Check collisions before applying the ALTER statements.
SELECT group_id, externalized_contents_id, COUNT(*) AS duplicate_count
FROM ok_core.knowledge_fragment_positions
GROUP BY group_id, externalized_contents_id
HAVING COUNT(*) > 1;

ALTER TABLE ok_core.knowledge_fragment_positions
  ADD COLUMN fragment_source_type VARCHAR(32) NOT NULL DEFAULT 'experience' AFTER group_id,
  ADD COLUMN fragment_source_id INT NULL AFTER fragment_source_type;

UPDATE ok_core.knowledge_fragment_positions
SET fragment_source_id = externalized_contents_id
WHERE fragment_source_id IS NULL;

-- Legacy group_id=0 coordinates were global for experience KF. Copy them to
-- each active group where the KF is shared, without replacing group-specific rows.
INSERT INTO ok_core.knowledge_fragment_positions
  (group_id, fragment_source_type, fragment_source_id,
   externalized_contents_id, pos_x, pos_y)
SELECT DISTINCT sn.knowledge_group_id, 'experience', p.fragment_source_id,
       p.externalized_contents_id, p.pos_x, p.pos_y
FROM ok_core.knowledge_fragment_positions AS p
JOIN ok_core.shared_nodes AS sn
  ON sn.experience_knowledge_id = p.fragment_source_id AND sn.deleted = 0
WHERE p.group_id = 0
  AND NOT EXISTS (
    SELECT 1 FROM ok_core.knowledge_fragment_positions AS existing
    WHERE existing.group_id = sn.knowledge_group_id
      AND existing.fragment_source_type = 'experience'
      AND existing.fragment_source_id = p.fragment_source_id
  );

ALTER TABLE ok_core.knowledge_fragment_positions
  MODIFY COLUMN fragment_source_id INT NOT NULL,
  ADD UNIQUE KEY ux_group_typed_fragment (group_id, fragment_source_type, fragment_source_id);

-- Keep the legacy externalized_contents_id column for rollback compatibility.
-- Drop only its old unique index, so the same numeric ID may be saved for
-- experience and discussion independently.
SET @drop_legacy_position_index = (
  SELECT IF(COUNT(*) > 0,
    'ALTER TABLE ok_core.knowledge_fragment_positions DROP INDEX ux_group_externalized',
    'SELECT 1')
  FROM information_schema.statistics
  WHERE table_schema = 'ok_core'
    AND table_name = 'knowledge_fragment_positions'
    AND index_name = 'ux_group_externalized'
);
PREPARE drop_legacy_position_index FROM @drop_legacy_position_index;
EXECUTE drop_legacy_position_index;
DEALLOCATE PREPARE drop_legacy_position_index;

SET @drop_legacy_position_index = (
  SELECT IF(COUNT(*) > 0,
    'ALTER TABLE ok_core.knowledge_fragment_positions DROP INDEX ux_externalized',
    'SELECT 1')
  FROM information_schema.statistics
  WHERE table_schema = 'ok_core'
    AND table_name = 'knowledge_fragment_positions'
    AND index_name = 'ux_externalized'
);
PREPARE drop_legacy_position_index FROM @drop_legacy_position_index;
EXECUTE drop_legacy_position_index;
DEALLOCATE PREPARE drop_legacy_position_index;
