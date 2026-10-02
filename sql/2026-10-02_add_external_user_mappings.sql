CREATE TABLE IF NOT EXISTS `external_user_mappings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `source_system` VARCHAR(191) NOT NULL,
  `source_user_ref` VARCHAR(191) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_external_user_mapping` (`source_system`, `source_user_ref`),
  KEY `idx_external_user_mapping_user_id` (`user_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;
