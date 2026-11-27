-- Production setup only; apply this migration through the normal change process.
-- This migration is intentionally not executed against production by Codex.
-- Run it after selecting the intended database (for example: USE ok_core;).
-- The information_schema guard makes repeated execution a no-op.
SET @okdiag_title_exists := (
  SELECT COUNT(*)
    FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name = 'externalized_contents'
     AND column_name = 'title'
);
SET @okdiag_title_sql := IF(
  @okdiag_title_exists = 0,
  'ALTER TABLE `externalized_contents` ADD COLUMN `title` VARCHAR(255) DEFAULT NULL AFTER `source_user_ref`',
  'SELECT 1'
);
PREPARE okdiag_title_stmt FROM @okdiag_title_sql;
EXECUTE okdiag_title_stmt;
DEALLOCATE PREPARE okdiag_title_stmt;

-- AI-generated KF bodies are not limited to 255 characters.  MODIFY is
-- intentionally idempotent and preserves the existing NOT NULL contract.
ALTER TABLE `externalized_contents`
  MODIFY COLUMN `knowledge_fragment_content` LONGTEXT NOT NULL;

-- Provision the account through an operational change procedure,
-- using the fields described below
-- using a site-approved user_id. Do not use user_id=0 or an existing human user.
-- Required identity values:
--   sso_sub / sso_user_id / sso_username = NULL (non-interactive local identity)
--   name / display_name = OKDiag（AI生成）
--   role = system, password = SSO_LOGIN_DISABLED, is_active = 1
