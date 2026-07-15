-- Rock Bluffs Exterior Services - Phase 16
CREATE TABLE IF NOT EXISTS corrective_action_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 corrective_action_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(80) NOT NULL,
 message TEXT NULL,
 actor_user_id BIGINT UNSIGNED NULL,
 metadata_json JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(corrective_action_id) REFERENCES corrective_actions(id) ON DELETE CASCADE,
 FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_ca_events_action_time(corrective_action_id,created_at)
) ENGINE=InnoDB;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='corrective_actions' AND COLUMN_NAME='escalation_level')=0,'ALTER TABLE corrective_actions ADD COLUMN escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER due_at','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='corrective_actions' AND COLUMN_NAME='last_notified_at')=0,'ALTER TABLE corrective_actions ADD COLUMN last_notified_at DATETIME NULL AFTER escalation_level','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='corrective_actions' AND COLUMN_NAME='escalated_at')=0,'ALTER TABLE corrective_actions ADD COLUMN escalated_at DATETIME NULL AFTER last_notified_at','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='corrective_actions' AND COLUMN_NAME='closed_at')=0,'ALTER TABLE corrective_actions ADD COLUMN closed_at DATETIME NULL AFTER verified_at','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS inspection_attachment_policies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 attachment_type ENUM('photo','signature','all') NOT NULL DEFAULT 'all',
 retain_days INT UNSIGNED NULL,
 create_thumbnail TINYINT(1) NOT NULL DEFAULT 1,
 thumbnail_width INT UNSIGNED NOT NULL DEFAULT 480,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inspection_attachments' AND COLUMN_NAME='thumbnail_path')=0,'ALTER TABLE inspection_attachments ADD COLUMN thumbnail_path VARCHAR(500) NULL AFTER storage_path','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inspection_attachments' AND COLUMN_NAME='expires_at')=0,'ALTER TABLE inspection_attachments ADD COLUMN expires_at DATETIME NULL AFTER caption','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inspection_attachments' AND COLUMN_NAME='deleted_at')=0,'ALTER TABLE inspection_attachments ADD COLUMN deleted_at DATETIME NULL AFTER expires_at','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO inspection_attachment_policies(name,attachment_type,retain_days,create_thumbnail,thumbnail_width,active,created_by)
SELECT 'Default inspection media policy','all',2555,1,480,1,NULL
WHERE NOT EXISTS (SELECT 1 FROM inspection_attachment_policies);

CREATE TABLE IF NOT EXISTS certification_renewals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_certification_id BIGINT UNSIGNED NOT NULL,
 status ENUM('requested','in_progress','submitted','approved','rejected','cancelled') NOT NULL DEFAULT 'requested',
 requested_by BIGINT UNSIGNED NULL,
 assigned_to BIGINT UNSIGNED NULL,
 target_date DATE NULL,
 submitted_at DATETIME NULL,
 decided_at DATETIME NULL,
 decided_by BIGINT UNSIGNED NULL,
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(employee_certification_id) REFERENCES employee_certifications(id) ON DELETE CASCADE,
 FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(decided_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_cert_renewal_status(status,target_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS api_daily_metrics (
 metric_date DATE NOT NULL,
 api_token_id BIGINT UNSIGNED NOT NULL,
 request_count INT UNSIGNED NOT NULL DEFAULT 0,
 error_count INT UNSIGNED NOT NULL DEFAULT 0,
 avg_duration_ms DECIMAL(10,2) NOT NULL DEFAULT 0,
 max_duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
 last_request_at DATETIME NULL,
 PRIMARY KEY(metric_date,api_token_id),
 FOREIGN KEY(api_token_id) REFERENCES api_tokens(id) ON DELETE CASCADE
) ENGINE=InnoDB;
