-- CastorWorks 0.32.5 - ETA and route progress intelligence

CREATE TABLE IF NOT EXISTS eta_notification_settings (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 send_on_the_way TINYINT(1) NOT NULL DEFAULT 1,
 send_delay_notices TINYINT(1) NOT NULL DEFAULT 1,
 on_the_way_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
 late_threshold_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
 minimum_recalculation_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5,
 average_speed_mph SMALLINT UNSIGNED NOT NULL DEFAULT 32,
 on_the_way_template TEXT NOT NULL,
 delay_template TEXT NOT NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_eta_settings_user FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO eta_notification_settings(id,on_the_way_template,delay_template)
VALUES(1,'Rock Bluffs Exterior Services is approximately {{eta_minutes}} minutes away from {{customer_name}}.','Rock Bluffs Exterior Services is running about {{late_minutes}} minutes late. Updated arrival: {{eta_time}}.')
ON DUPLICATE KEY UPDATE id=id;

CREATE TABLE IF NOT EXISTS route_progress_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NULL,
 event_type VARCHAR(80) NOT NULL,
 detail TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_route_progress_job(job_id,created_at),
 CONSTRAINT fk_route_progress_job FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eta_worker_runs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 run_date DATE NOT NULL,
 status VARCHAR(20) NOT NULL,
 jobs_updated INT UNSIGNED NOT NULL DEFAULT 0,
 notifications_sent INT UNSIGNED NOT NULL DEFAULT 0,
 late_jobs INT UNSIGNED NOT NULL DEFAULT 0,
 detail VARCHAR(1000) NULL,
 completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_eta_worker_completed(completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='estimated_arrival');
SET @sql=IF(@exists=0,'ALTER TABLE jobs ADD COLUMN estimated_arrival DATETIME NULL AFTER actual_departure','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='eta_calculated_at');
SET @sql=IF(@exists=0,'ALTER TABLE jobs ADD COLUMN eta_calculated_at DATETIME NULL AFTER estimated_arrival','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='eta_status');
SET @sql=IF(@exists=0,"ALTER TABLE jobs ADD COLUMN eta_status ENUM('unknown','on_time','late') NOT NULL DEFAULT 'unknown' AFTER eta_calculated_at",'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='eta_late_minutes');
SET @sql=IF(@exists=0,'ALTER TABLE jobs ADD COLUMN eta_late_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER eta_status','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='eta_notified_at');
SET @sql=IF(@exists=0,'ALTER TABLE jobs ADD COLUMN eta_notified_at DATETIME NULL AFTER eta_late_minutes','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='delay_notified_at');
SET @sql=IF(@exists=0,'ALTER TABLE jobs ADD COLUMN delay_notified_at DATETIME NULL AFTER eta_notified_at','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND INDEX_NAME='idx_jobs_eta');
SET @sql=IF(@idx=0,'ALTER TABLE jobs ADD INDEX idx_jobs_eta(eta_status,estimated_arrival)','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
