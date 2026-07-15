SET @sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='last_location_update')=0,'ALTER TABLE users ADD COLUMN last_location_update DATETIME NULL','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='actual_arrival')=0,'ALTER TABLE jobs ADD COLUMN actual_arrival DATETIME NULL, ADD COLUMN actual_departure DATETIME NULL','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS gps_tracking_policies (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 clocked_in_only TINYINT(1) NOT NULL DEFAULT 1,
 update_interval_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 60,
 arrival_radius_meters SMALLINT UNSIGNED NOT NULL DEFAULT 125,
 stale_after_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10,
 retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 90,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_gps_policy_user FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO gps_tracking_policies(id,enabled,clocked_in_only,update_interval_seconds,arrival_radius_meters,stale_after_minutes,retention_days)
VALUES(1,0,1,60,125,10,90) ON DUPLICATE KEY UPDATE id=id;

SET @sql=IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gps_location_history' AND INDEX_NAME='idx_gps_captured_at')=0,'ALTER TABLE gps_location_history ADD INDEX idx_gps_captured_at(captured_at)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
