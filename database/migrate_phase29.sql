USE rockbluffs_exterior;

CREATE TABLE IF NOT EXISTS marketing_campaigns (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL,
 campaign_type ENUM('digital','email','sms','flyer','door_hanger','referral','seasonal','other') NOT NULL DEFAULT 'other',
 channel VARCHAR(100) NULL,
 status ENUM('draft','planned','active','paused','completed','cancelled') NOT NULL DEFAULT 'planned',
 start_date DATE NULL,
 end_date DATE NULL,
 budget DECIMAL(12,2) NOT NULL DEFAULT 0,
 actual_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
 target_postal_codes TEXT NULL,
 tracking_code VARCHAR(100) NULL,
 notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uq_marketing_tracking_code(tracking_code),
 INDEX idx_marketing_campaign_status(status,start_date,end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_coupons (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(80) NOT NULL,
 name VARCHAR(180) NOT NULL,
 discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
 discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
 start_date DATE NULL,
 end_date DATE NULL,
 max_redemptions INT UNSIGNED NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uq_marketing_coupon_code(code),
 INDEX idx_marketing_coupon_active(active,start_date,end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_coupon_redemptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 coupon_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NULL,
 estimate_id BIGINT UNSIGNED NULL,
 invoice_id BIGINT UNSIGNED NULL,
 discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(coupon_id) REFERENCES marketing_coupons(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 FOREIGN KEY(estimate_id) REFERENCES estimates(id) ON DELETE SET NULL,
 FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
 INDEX idx_coupon_redemption(coupon_id,redeemed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_suppressions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 destination VARCHAR(190) NOT NULL,
 channel ENUM('email','sms','all') NOT NULL DEFAULT 'all',
 reason VARCHAR(255) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_marketing_suppression(destination,channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='leads' AND COLUMN_NAME='lead_source');
SET @sql=IF(@exists=0,'ALTER TABLE leads ADD COLUMN lead_source VARCHAR(100) NULL AFTER status','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='leads' AND COLUMN_NAME='campaign_id');
SET @sql=IF(@exists=0,'ALTER TABLE leads ADD COLUMN campaign_id BIGINT UNSIGNED NULL AFTER lead_source','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='leads' AND COLUMN_NAME='referral_code');
SET @sql=IF(@exists=0,'ALTER TABLE leads ADD COLUMN referral_code VARCHAR(100) NULL AFTER campaign_id','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='leads' AND COLUMN_NAME='utm_source');
SET @sql=IF(@exists=0,'ALTER TABLE leads ADD COLUMN utm_source VARCHAR(150) NULL AFTER referral_code','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='leads' AND COLUMN_NAME='utm_medium');
SET @sql=IF(@exists=0,'ALTER TABLE leads ADD COLUMN utm_medium VARCHAR(150) NULL AFTER utm_source','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='leads' AND COLUMN_NAME='utm_campaign');
SET @sql=IF(@exists=0,'ALTER TABLE leads ADD COLUMN utm_campaign VARCHAR(150) NULL AFTER utm_medium','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='acquisition_source');
SET @sql=IF(@exists=0,'ALTER TABLE customers ADD COLUMN acquisition_source VARCHAR(100) NULL AFTER notes','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='acquisition_campaign_id');
SET @sql=IF(@exists=0,'ALTER TABLE customers ADD COLUMN acquisition_campaign_id BIGINT UNSIGNED NULL AFTER acquisition_source','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='communication_campaigns' AND COLUMN_NAME='marketing_campaign_id');
SET @sql=IF(@exists=0,'ALTER TABLE communication_campaigns ADD COLUMN marketing_campaign_id BIGINT UNSIGNED NULL AFTER name','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='leads' AND INDEX_NAME='idx_leads_marketing');
SET @sql=IF(@exists=0,'ALTER TABLE leads ADD INDEX idx_leads_marketing(campaign_id,lead_source,created_at)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND INDEX_NAME='idx_customers_acquisition');
SET @sql=IF(@exists=0,'ALTER TABLE customers ADD INDEX idx_customers_acquisition(acquisition_campaign_id,acquisition_source)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
