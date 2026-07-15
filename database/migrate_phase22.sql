-- Rock Bluffs Exterior Services Phase 22 (MySQL 8 compatible)
CREATE TABLE IF NOT EXISTS entra_group_role_mappings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 entra_group_id VARCHAR(100) NOT NULL,
 entra_group_name VARCHAR(190) NOT NULL,
 portal_role ENUM('administrator','owner','office','estimator','crew_leader','technician') NOT NULL DEFAULT 'technician',
 priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_entra_group_role_group(entra_group_id),
 INDEX idx_entra_group_role_active(active,priority)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS entra_sync_settings (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
 department_filter VARCHAR(190) NULL,
 group_filter_id VARCHAR(100) NULL,
 import_enabled_only TINYINT(1) NOT NULL DEFAULT 1,
 disable_missing TINYINT(1) NOT NULL DEFAULT 0,
 sync_managers TINYINT(1) NOT NULL DEFAULT 1,
 sync_group_roles TINYINT(1) NOT NULL DEFAULT 1,
 schedule_enabled TINYINT(1) NOT NULL DEFAULT 0,
 schedule_time TIME NOT NULL DEFAULT '02:00:00',
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO entra_sync_settings(id) VALUES(1);

CREATE TABLE IF NOT EXISTS entra_sync_previews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 requested_by BIGINT UNSIGNED NULL,
 preview_json LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 INDEX idx_entra_preview_expiry(expires_at)
) ENGINE=InnoDB;

SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='entra_manager_object_id');
SET @s=IF(@c=0,'ALTER TABLE users ADD COLUMN entra_manager_object_id VARCHAR(100) NULL AFTER last_synced_at','SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='entra_manager_name');
SET @s=IF(@c=0,'ALTER TABLE users ADD COLUMN entra_manager_name VARCHAR(190) NULL AFTER entra_manager_object_id','SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='entra_manager_email');
SET @s=IF(@c=0,'ALTER TABLE users ADD COLUMN entra_manager_email VARCHAR(190) NULL AFTER entra_manager_name','SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='assigned_vehicle_id');
SET @s=IF(@c=0,'ALTER TABLE users ADD COLUMN assigned_vehicle_id BIGINT UNSIGNED NULL AFTER entra_manager_email','SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='service_territory_id');
SET @s=IF(@c=0,'ALTER TABLE users ADD COLUMN service_territory_id BIGINT UNSIGNED NULL AFTER assigned_vehicle_id','SELECT 1'); PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

INSERT INTO schema_migrations(migration,checksum) VALUES('migrate_phase22.sql',NULL)
ON DUPLICATE KEY UPDATE applied_at=applied_at;
