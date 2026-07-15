-- ServiceOS 0.25.0 - Technician mobile portal

SET @column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='mobile_completed_by');
SET @sql = IF(@column_exists=0,'ALTER TABLE jobs ADD COLUMN mobile_completed_by BIGINT UNSIGNED NULL AFTER completed_at','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='mobile_completion_summary');
SET @sql = IF(@column_exists=0,'ALTER TABLE jobs ADD COLUMN mobile_completion_summary TEXT NULL AFTER mobile_completed_by','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS mobile_push_subscriptions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id BIGINT UNSIGNED NOT NULL,
 endpoint TEXT NOT NULL,
 endpoint_hash CHAR(64) GENERATED ALWAYS AS (SHA2(endpoint,256)) STORED,
 p256dh VARCHAR(255) NULL,
 auth_token VARCHAR(255) NULL,
 user_agent VARCHAR(500) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 last_seen_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_mobile_push_endpoint(endpoint_hash),
 KEY idx_mobile_push_user(user_id,active),
 CONSTRAINT fk_mobile_push_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
