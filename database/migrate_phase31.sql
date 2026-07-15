SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='communication_providers' AND COLUMN_NAME='allow_transactional');
SET @sql=IF(@exists=0,'ALTER TABLE communication_providers ADD COLUMN allow_transactional TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_fallback','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='communication_providers' AND COLUMN_NAME='allow_marketing');
SET @sql=IF(@exists=0,'ALTER TABLE communication_providers ADD COLUMN allow_marketing TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_transactional','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='communication_providers' AND COLUMN_NAME='daily_limit');
SET @sql=IF(@exists=0,'ALTER TABLE communication_providers ADD COLUMN daily_limit INT UNSIGNED NOT NULL DEFAULT 0 AFTER allow_marketing','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='communication_providers' AND COLUMN_NAME='monthly_limit');
SET @sql=IF(@exists=0,'ALTER TABLE communication_providers ADD COLUMN monthly_limit INT UNSIGNED NOT NULL DEFAULT 0 AFTER daily_limit','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='communication_delivery_attempts' AND COLUMN_NAME='message_class');
SET @sql=IF(@exists=0,"ALTER TABLE communication_delivery_attempts ADD COLUMN message_class ENUM('transactional','marketing') NOT NULL DEFAULT 'transactional' AFTER subject",'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='communication_delivery_attempts' AND COLUMN_NAME='delivery_status');
SET @sql=IF(@exists=0,"ALTER TABLE communication_delivery_attempts ADD COLUMN delivery_status ENUM('accepted','delivered','failed','rejected','opted_out','unknown') NOT NULL DEFAULT 'accepted' AFTER status",'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='communication_delivery_attempts' AND COLUMN_NAME='status_updated_at');
SET @sql=IF(@exists=0,'ALTER TABLE communication_delivery_attempts ADD COLUMN status_updated_at DATETIME NULL AFTER delivery_status','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='communication_delivery_attempts' AND COLUMN_NAME='sms_parts');
SET @sql=IF(@exists=0,'ALTER TABLE communication_delivery_attempts ADD COLUMN sms_parts SMALLINT UNSIGNED NULL AFTER duration_ms','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='communication_delivery_attempts' AND COLUMN_NAME='sms_encoding');
SET @sql=IF(@exists=0,'ALTER TABLE communication_delivery_attempts ADD COLUMN sms_encoding VARCHAR(20) NULL AFTER sms_parts','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS communication_receipts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider_key VARCHAR(100) NOT NULL,
 provider_message_id VARCHAR(500) NULL,
 normalized_status ENUM('accepted','delivered','failed','rejected','opted_out','unknown') NOT NULL DEFAULT 'unknown',
 destination VARCHAR(190) NULL,
 payload_json JSON NULL,
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_communication_receipt_message(provider_key,provider_message_id),
 INDEX idx_communication_receipt_status(normalized_status,received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_inbound_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider_key VARCHAR(100) NOT NULL,
 sender VARCHAR(190) NOT NULL,
 recipient VARCHAR(190) NULL,
 body TEXT NOT NULL,
 payload_json JSON NULL,
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_communication_inbound_sender(sender,received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE communication_providers SET allow_transactional=1 WHERE allow_transactional IS NULL;
UPDATE communication_providers SET allow_marketing=0 WHERE allow_marketing IS NULL;
