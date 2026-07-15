-- Rock Bluffs Exterior Services - Phase 18
-- MySQL 8 compatible and safe to rerun.

CREATE TABLE IF NOT EXISTS notification_delivery_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 notification_route_id BIGINT UNSIGNED NULL,
 event_key VARCHAR(120) NOT NULL,
 severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
 channel ENUM('email','teams','sms','portal') NOT NULL,
 recipient VARCHAR(255) NOT NULL,
 subject VARCHAR(255) NULL,
 message TEXT NULL,
 status ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
 error_message TEXT NULL,
 metadata_json JSON NULL,
 sent_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(notification_route_id) REFERENCES notification_routes(id) ON DELETE SET NULL,
 INDEX idx_notification_delivery_event(event_key,created_at),
 INDEX idx_notification_delivery_status(status,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portal_user_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 event_key VARCHAR(120) NOT NULL,
 severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
 title VARCHAR(255) NOT NULL,
 message TEXT NULL,
 action_url VARCHAR(500) NULL,
 read_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_portal_user_notification(user_id,read_at,created_at)
) ENGINE=InnoDB;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='certification_renewals' AND COLUMN_NAME='decision_notes')=0,
 'ALTER TABLE certification_renewals ADD COLUMN decision_notes TEXT NULL AFTER notes','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='certification_renewals' AND COLUMN_NAME='approval_requested_at')=0,
 'ALTER TABLE certification_renewals ADD COLUMN approval_requested_at DATETIME NULL AFTER submitted_at','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS inspection_packet_archives (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 inspection_id BIGINT UNSIGNED NOT NULL,
 document_id BIGINT UNSIGNED NULL,
 filename VARCHAR(255) NOT NULL,
 local_path VARCHAR(500) NOT NULL,
 sharepoint_item_id VARCHAR(255) NULL,
 sharepoint_url TEXT NULL,
 storage_status ENUM('local','uploaded','failed') NOT NULL DEFAULT 'local',
 upload_error TEXT NULL,
 generated_by BIGINT UNSIGNED NULL,
 generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
 FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE SET NULL,
 FOREIGN KEY(generated_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_inspection_packet(inspection_id,generated_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS asset_replacement_alerts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 asset_replacement_plan_id BIGINT UNSIGNED NOT NULL,
 alert_type ENUM('approaching','due','overdue','budget_gap') NOT NULL,
 severity ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
 message VARCHAR(500) NOT NULL,
 status ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
 acknowledged_by BIGINT UNSIGNED NULL,
 acknowledged_at DATETIME NULL,
 resolved_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(asset_replacement_plan_id) REFERENCES asset_replacement_plans(id) ON DELETE CASCADE,
 FOREIGN KEY(acknowledged_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uq_asset_replacement_alert(asset_replacement_plan_id,alert_type,status),
 INDEX idx_asset_replacement_alert_status(status,severity,created_at)
) ENGINE=InnoDB;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_tokens' AND COLUMN_NAME='quota_warning_percent')=0,
 'ALTER TABLE api_tokens ADD COLUMN quota_warning_percent TINYINT UNSIGNED NOT NULL DEFAULT 80 AFTER monthly_quota','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS schema_migrations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 migration VARCHAR(190) NOT NULL UNIQUE,
 applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO schema_migrations(migration) VALUES('migrate_phase18.sql');
