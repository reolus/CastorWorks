USE rockbluffs_exterior;

SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workflow_rules' AND COLUMN_NAME='description');
SET @q=IF(@c=0,'ALTER TABLE workflow_rules ADD COLUMN description TEXT NULL AFTER name','SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workflow_rules' AND COLUMN_NAME='version');
SET @q=IF(@c=0,'ALTER TABLE workflow_rules ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1 AFTER active','SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workflow_runs' AND COLUMN_NAME='current_step');
SET @q=IF(@c=0,'ALTER TABLE workflow_runs ADD COLUMN current_step INT UNSIGNED NOT NULL DEFAULT 1 AFTER status','SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workflow_runs' AND COLUMN_NAME='next_attempt_at');
SET @q=IF(@c=0,'ALTER TABLE workflow_runs ADD COLUMN next_attempt_at DATETIME NULL AFTER queued_at','SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workflow_runs' AND COLUMN_NAME='attempt_count');
SET @q=IF(@c=0,'ALTER TABLE workflow_runs ADD COLUMN attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER current_step','SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS workflow_steps (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 workflow_rule_id BIGINT UNSIGNED NOT NULL,
 sort_order INT UNSIGNED NOT NULL DEFAULT 1,
 action_key VARCHAR(120) NOT NULL,
 condition_key VARCHAR(120) NOT NULL DEFAULT 'always',
 condition_field VARCHAR(190) NULL,
 condition_value TEXT NULL,
 config_json JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_workflow_steps_rule(workflow_rule_id,sort_order),
 CONSTRAINT fk_workflow_steps_rule FOREIGN KEY(workflow_rule_id) REFERENCES workflow_rules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflow_versions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 workflow_rule_id BIGINT UNSIGNED NOT NULL,
 version INT UNSIGNED NOT NULL,
 snapshot_json JSON NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_workflow_version(workflow_rule_id,version),
 CONSTRAINT fk_workflow_versions_rule FOREIGN KEY(workflow_rule_id) REFERENCES workflow_rules(id) ON DELETE CASCADE,
 CONSTRAINT fk_workflow_versions_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(migration,checksum,applied_at) VALUES('migrate_phase28.sql',NULL,NOW()) ON DUPLICATE KEY UPDATE applied_at=applied_at;
