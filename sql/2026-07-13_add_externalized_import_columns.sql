ALTER TABLE `externalized_contents`
  ADD COLUMN `group_id` INT(11) NOT NULL DEFAULT 0 AFTER `user_id`,
  ADD COLUMN `source_system` VARCHAR(191) DEFAULT NULL AFTER `group_id`,
  ADD COLUMN `source_type` VARCHAR(32) DEFAULT NULL AFTER `source_system`,
  ADD COLUMN `source_id` VARCHAR(191) DEFAULT NULL AFTER `source_type`,
  ADD COLUMN `source_user_ref` VARCHAR(191) DEFAULT NULL AFTER `source_id`,
  ADD COLUMN `raw_payload` LONGTEXT DEFAULT NULL AFTER `source_user_ref`;

ALTER TABLE `externalized_contents`
  ADD UNIQUE KEY `ux_externalized_source_fragment` (`source_system`, `source_type`, `source_id`),
  ADD KEY `idx_externalized_group_id` (`group_id`);
