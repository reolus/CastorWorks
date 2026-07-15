-- Rock Bluffs Exterior Services Phase 21 (MySQL 8 compatible)
CREATE TABLE IF NOT EXISTS entra_sync_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sync_type ENUM('import','full_sync','scheduled_sync') NOT NULL,
 created_count INT UNSIGNED NOT NULL DEFAULT 0,
 updated_count INT UNSIGNED NOT NULL DEFAULT 0,
 disabled_count INT UNSIGNED NOT NULL DEFAULT 0,
 error_count INT UNSIGNED NOT NULL DEFAULT 0,
 error_detail TEXT NULL,
 completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_entra_sync_completed(completed_at)
) ENGINE=InnoDB;

SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='entra_upn');
SET @s=IF(@c=0,'ALTER TABLE users ADD COLUMN entra_upn VARCHAR(190) NULL AFTER entra_email','SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='department');
SET @s=IF(@c=0,'ALTER TABLE users ADD COLUMN department VARCHAR(150) NULL AFTER entra_upn','SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='job_title');
SET @s=IF(@c=0,'ALTER TABLE users ADD COLUMN job_title VARCHAR(150) NULL AFTER department','SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='office_location');
SET @s=IF(@c=0,'ALTER TABLE users ADD COLUMN office_location VARCHAR(150) NULL AFTER job_title','SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='business_phone');
SET @s=IF(@c=0,'ALTER TABLE users ADD COLUMN business_phone VARCHAR(60) NULL AFTER office_location','SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='mobile_phone');
SET @s=IF(@c=0,'ALTER TABLE users ADD COLUMN mobile_phone VARCHAR(60) NULL AFTER business_phone','SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='identity_source');
SET @s=IF(@c=0,"ALTER TABLE users ADD COLUMN identity_source ENUM('local','entra') NOT NULL DEFAULT 'local' AFTER mobile_phone",'SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='last_synced_at');
SET @s=IF(@c=0,'ALTER TABLE users ADD COLUMN last_synced_at DATETIME NULL AFTER identity_source','SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

INSERT INTO schema_migrations(migration,checksum) VALUES('migrate_phase21.sql',NULL)
ON DUPLICATE KEY UPDATE applied_at=applied_at;
