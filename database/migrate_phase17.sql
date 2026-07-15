-- Rock Bluffs Exterior Services - Phase 17

CREATE TABLE notification_routes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(120) NOT NULL,
 channel ENUM('email','teams','sms','portal') NOT NULL DEFAULT 'portal',
 recipient_type ENUM('role','user','email','phone','teams_webhook') NOT NULL DEFAULT 'role',
 recipient_value VARCHAR(255) NOT NULL,
 severity_min ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_notification_route(event_key,channel,recipient_type,recipient_value),
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE certification_approval_attachments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 certification_renewal_id BIGINT UNSIGNED NOT NULL,
 filename VARCHAR(255) NOT NULL,
 storage_path VARCHAR(500) NOT NULL,
 mime_type VARCHAR(120) NOT NULL,
 file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
 uploaded_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(certification_renewal_id) REFERENCES certification_renewals(id) ON DELETE CASCADE,
 FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE asset_replacement_plans (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 asset_type ENUM('vehicle','equipment') NOT NULL,
 asset_id BIGINT UNSIGNED NOT NULL,
 acquisition_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
 acquisition_date DATE NULL,
 useful_life_months INT UNSIGNED NOT NULL DEFAULT 60,
 salvage_value DECIMAL(12,2) NOT NULL DEFAULT 0,
 replacement_target_date DATE NULL,
 replacement_budget DECIMAL(12,2) NULL,
 notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_asset_replacement(asset_type,asset_id),
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE api_tokens
 ADD COLUMN daily_quota INT UNSIGNED NOT NULL DEFAULT 600 AFTER abilities_json,
 ADD COLUMN monthly_quota INT UNSIGNED NOT NULL DEFAULT 10000 AFTER daily_quota;

CREATE INDEX idx_corrective_sla ON corrective_actions(status,due_at,severity);
