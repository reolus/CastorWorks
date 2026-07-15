CREATE TABLE IF NOT EXISTS customer_service_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 property_id BIGINT UNSIGNED NULL,
 service_id BIGINT UNSIGNED NULL,
 subject VARCHAR(190) NOT NULL,
 details TEXT NULL,
 preferred_date DATE NULL,
 alternate_date DATE NULL,
 preferred_window ENUM('morning','afternoon','evening','any') NOT NULL DEFAULT 'any',
 status ENUM('new','reviewing','quoted','scheduled','completed','declined','cancelled') NOT NULL DEFAULT 'new',
 lead_id BIGINT UNSIGNED NULL,
 estimate_id BIGINT UNSIGNED NULL,
 job_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 FOREIGN KEY(property_id) REFERENCES properties(id) ON DELETE SET NULL,
 FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE SET NULL,
 FOREIGN KEY(lead_id) REFERENCES leads(id) ON DELETE SET NULL,
 FOREIGN KEY(estimate_id) REFERENCES estimates(id) ON DELETE SET NULL,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL,
 INDEX idx_customer_service_requests(customer_id,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_service_request_photos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 service_request_id BIGINT UNSIGNED NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 stored_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(120) NOT NULL,
 size_bytes BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(service_request_id) REFERENCES customer_service_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_referrals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 referring_customer_id BIGINT UNSIGNED NOT NULL,
 referred_name VARCHAR(180) NOT NULL,
 referred_email VARCHAR(190) NULL,
 referred_phone VARCHAR(50) NULL,
 notes TEXT NULL,
 status ENUM('submitted','contacted','converted','credited','declined') NOT NULL DEFAULT 'submitted',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(referring_customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 INDEX idx_customer_referrals(referring_customer_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_satisfaction_surveys (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 job_id BIGINT UNSIGNED NULL,
 rating TINYINT UNSIGNED NOT NULL,
 recommend_score TINYINT UNSIGNED NULL,
 comments TEXT NULL,
 permission_to_contact TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL,
 INDEX idx_customer_surveys(customer_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_sms = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notification_preferences' AND COLUMN_NAME='sms_appointments');
SET @sql = IF(@has_sms=0, 'ALTER TABLE notification_preferences ADD COLUMN sms_appointments TINYINT(1) NOT NULL DEFAULT 0 AFTER email_marketing, ADD COLUMN sms_invoices TINYINT(1) NOT NULL DEFAULT 0 AFTER sms_appointments, ADD COLUMN sms_marketing TINYINT(1) NOT NULL DEFAULT 0 AFTER sms_invoices', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
