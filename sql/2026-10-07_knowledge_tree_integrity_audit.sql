-- Read-only audit before assigning or deleting legacy organizational knowledge.
SET NAMES utf8mb4;

-- Roots repeated within the same group.
SELECT knowledge_group_id, node_title, COUNT(*) AS node_count,
       GROUP_CONCAT(knowledge_node_id ORDER BY knowledge_node_id) AS node_ids
FROM ok_core.knowledge_explorer
WHERE parent_node_id IS NULL AND deleted = 0
GROUP BY knowledge_group_id, node_title
HAVING COUNT(*) > 1;

-- Active nodes without a valid active group. Do not assign them automatically.
SELECT ke.knowledge_node_id, ke.parent_node_id, ke.node_title, ke.knowledge_group_id
FROM ok_core.knowledge_explorer AS ke
LEFT JOIN ok_core.knowledge_groups AS kg
  ON kg.group_id = ke.knowledge_group_id AND kg.deleted = 0
WHERE ke.deleted = 0 AND kg.group_id IS NULL
ORDER BY ke.knowledge_group_id, ke.knowledge_node_id;

-- Parent references across group boundaries or to missing/deleted nodes.
SELECT child.knowledge_node_id, child.knowledge_group_id,
       child.parent_node_id, parent.knowledge_group_id AS parent_group_id
FROM ok_core.knowledge_explorer AS child
LEFT JOIN ok_core.knowledge_explorer AS parent
  ON parent.knowledge_node_id = child.parent_node_id AND parent.deleted = 0
WHERE child.deleted = 0 AND child.parent_node_id IS NOT NULL
  AND (parent.knowledge_node_id IS NULL
       OR NOT (child.knowledge_group_id <=> parent.knowledge_group_id));

-- Link rows with no active node.
SELECT link.id, link.knowledge_node_id, link.fragment_source_type,
       link.fragment_source_id
FROM ok_core.knowledge_explorer_fragment_links AS link
LEFT JOIN ok_core.knowledge_explorer AS ke
  ON ke.knowledge_node_id = link.knowledge_node_id AND ke.deleted = 0
WHERE ke.knowledge_node_id IS NULL;

-- Active experience links whose KF is not currently shared with the node's group.
SELECT link.id, link.knowledge_node_id, link.fragment_source_id,
       ke.knowledge_group_id
FROM ok_core.knowledge_explorer_fragment_links AS link
JOIN ok_core.knowledge_explorer AS ke
  ON ke.knowledge_node_id = link.knowledge_node_id AND ke.deleted = 0
LEFT JOIN ok_core.shared_nodes AS sn
  ON sn.experience_knowledge_id = link.fragment_source_id
 AND sn.knowledge_group_id = ke.knowledge_group_id AND sn.deleted = 0
LEFT JOIN ok_core.experience_knowledges AS ek
  ON ek.experience_knowledge_id = link.fragment_source_id AND ek.deleted = 0
WHERE link.fragment_source_type = 'experience'
  AND (sn.id IS NULL OR ek.experience_knowledge_id IS NULL);
