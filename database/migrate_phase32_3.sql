-- CastorWorks 0.32.3 - Route optimization plans

CREATE TABLE IF NOT EXISTS route_plans (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 route_date DATE NOT NULL,
 assigned_user_id BIGINT UNSIGNED NULL,
 assigned_vehicle_id BIGINT UNSIGNED NULL,
 crew_id BIGINT UNSIGNED NULL,
 status ENUM('proposed','accepted','rejected','superseded') NOT NULL DEFAULT 'proposed',
 version_no INT UNSIGNED NOT NULL DEFAULT 1,
 start_address VARCHAR(255) NULL,
 current_distance_miles DECIMAL(10,2) NULL,
 optimized_distance_miles DECIMAL(10,2) NULL,
 distance_savings_miles DECIMAL(10,2) NULL,
 current_duration_minutes INT UNSIGNED NULL,
 optimized_duration_minutes INT UNSIGNED NULL,
 duration_savings_minutes INT UNSIGNED NULL,
 optimization_method VARCHAR(80) NOT NULL DEFAULT 'nearest_neighbor',
 provider VARCHAR(80) NOT NULL DEFAULT 'local',
 accepted_by BIGINT UNSIGNED NULL,
 accepted_at DATETIME NULL,
 rejected_by BIGINT UNSIGNED NULL,
 rejected_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_route_plans_lookup(route_date,crew_id,status,version_no),
 FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(assigned_vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
 FOREIGN KEY(crew_id) REFERENCES crews(id) ON DELETE SET NULL,
 FOREIGN KEY(accepted_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(rejected_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS route_plan_stops (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 route_plan_id BIGINT UNSIGNED NOT NULL,
 job_id BIGINT UNSIGNED NOT NULL,
 stop_order INT UNSIGNED NOT NULL,
 original_order INT UNSIGNED NULL,
 is_locked TINYINT(1) NOT NULL DEFAULT 0,
 estimated_arrival DATETIME NULL,
 distance_from_previous_miles DECIMAL(10,2) NULL,
 duration_from_previous_minutes INT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(route_plan_id) REFERENCES route_plans(id) ON DELETE CASCADE,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 UNIQUE KEY uq_route_job(route_plan_id,job_id),
 INDEX idx_route_plan_stops_order(route_plan_id,stop_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS route_plan_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 route_plan_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(80) NOT NULL,
 details JSON NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(route_plan_id) REFERENCES route_plans(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_route_plan_history(route_plan_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remove the old one-plan-per-user constraint so route plans can be versioned.
SET @idx_exists = (
 SELECT COUNT(*) FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND INDEX_NAME='uq_route_plan'
);
SET @sql = IF(@idx_exists>0,'ALTER TABLE route_plans DROP INDEX uq_route_plan','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add missing columns to legacy route_plans installations.
SET @columns = 'crew_id,status,version_no,current_distance_miles,distance_savings_miles,current_duration_minutes,duration_savings_minutes,provider,accepted_by,accepted_at,rejected_by,rejected_at';

SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND COLUMN_NAME='crew_id');
SET @sql=IF(@exists=0,'ALTER TABLE route_plans ADD COLUMN crew_id BIGINT UNSIGNED NULL AFTER assigned_vehicle_id','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND COLUMN_NAME='status');
SET @sql=IF(@exists=0,"ALTER TABLE route_plans ADD COLUMN status ENUM('proposed','accepted','rejected','superseded') NOT NULL DEFAULT 'proposed' AFTER crew_id",'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND COLUMN_NAME='version_no');
SET @sql=IF(@exists=0,'ALTER TABLE route_plans ADD COLUMN version_no INT UNSIGNED NOT NULL DEFAULT 1 AFTER status','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND COLUMN_NAME='current_distance_miles');
SET @sql=IF(@exists=0,'ALTER TABLE route_plans ADD COLUMN current_distance_miles DECIMAL(10,2) NULL AFTER start_address','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND COLUMN_NAME='distance_savings_miles');
SET @sql=IF(@exists=0,'ALTER TABLE route_plans ADD COLUMN distance_savings_miles DECIMAL(10,2) NULL AFTER optimized_distance_miles','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND COLUMN_NAME='current_duration_minutes');
SET @sql=IF(@exists=0,'ALTER TABLE route_plans ADD COLUMN current_duration_minutes INT UNSIGNED NULL AFTER distance_savings_miles','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND COLUMN_NAME='duration_savings_minutes');
SET @sql=IF(@exists=0,'ALTER TABLE route_plans ADD COLUMN duration_savings_minutes INT UNSIGNED NULL AFTER optimized_duration_minutes','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND COLUMN_NAME='provider');
SET @sql=IF(@exists=0,"ALTER TABLE route_plans ADD COLUMN provider VARCHAR(80) NOT NULL DEFAULT 'local' AFTER optimization_method",'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND COLUMN_NAME='accepted_by');
SET @sql=IF(@exists=0,'ALTER TABLE route_plans ADD COLUMN accepted_by BIGINT UNSIGNED NULL AFTER provider','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND COLUMN_NAME='accepted_at');
SET @sql=IF(@exists=0,'ALTER TABLE route_plans ADD COLUMN accepted_at DATETIME NULL AFTER accepted_by','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND COLUMN_NAME='rejected_by');
SET @sql=IF(@exists=0,'ALTER TABLE route_plans ADD COLUMN rejected_by BIGINT UNSIGNED NULL AFTER accepted_at','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plans' AND COLUMN_NAME='rejected_at');
SET @sql=IF(@exists=0,'ALTER TABLE route_plans ADD COLUMN rejected_at DATETIME NULL AFTER rejected_by','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plan_stops' AND COLUMN_NAME='original_order');
SET @sql=IF(@exists=0,'ALTER TABLE route_plan_stops ADD COLUMN original_order INT UNSIGNED NULL AFTER stop_order','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='route_plan_stops' AND COLUMN_NAME='is_locked');
SET @sql=IF(@exists=0,'ALTER TABLE route_plan_stops ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER original_order','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
