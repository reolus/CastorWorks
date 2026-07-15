-- Rock Bluffs Exterior Services - Phase 15
CREATE TABLE IF NOT EXISTS inspection_attachments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 inspection_id BIGINT UNSIGNED NOT NULL,
 inspection_template_item_id BIGINT UNSIGNED NULL,
 attachment_type ENUM('photo','signature') NOT NULL,
 filename VARCHAR(255) NULL,
 storage_path VARCHAR(500) NOT NULL,
 mime_type VARCHAR(100) NOT NULL,
 caption VARCHAR(255) NULL,
 uploaded_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
 FOREIGN KEY(inspection_template_item_id) REFERENCES inspection_template_items(id) ON DELETE SET NULL,
 FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_inspection_attachments(inspection_id,attachment_type)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS corrective_actions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 inspection_id BIGINT UNSIGNED NULL,
 job_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL,
 description TEXT NULL,
 severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
 status ENUM('open','assigned','in_progress','verified','closed') NOT NULL DEFAULT 'open',
 assigned_user_id BIGINT UNSIGNED NULL,
 due_at DATETIME NULL,
 resolution_notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 verified_by BIGINT UNSIGNED NULL,
 verified_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(inspection_id) REFERENCES inspections(id) ON DELETE SET NULL,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL,
 FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(verified_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_corrective_status(status,severity), INDEX idx_corrective_due(due_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS asset_activity (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 asset_type ENUM('vehicle','inventory') NOT NULL,
 asset_id BIGINT UNSIGNED NOT NULL,
 activity_type VARCHAR(80) NOT NULL,
 summary VARCHAR(255) NOT NULL,
 details_json JSON NULL,
 performed_by BIGINT UNSIGNED NULL,
 occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(performed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_asset_activity(asset_type,asset_id,occurred_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS certification_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_certification_id BIGINT UNSIGNED NOT NULL,
 filename VARCHAR(255) NOT NULL,
 storage_path VARCHAR(500) NOT NULL,
 mime_type VARCHAR(100) NOT NULL,
 file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
 uploaded_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(employee_certification_id) REFERENCES employee_certifications(id) ON DELETE CASCADE,
 FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_cert_documents(employee_certification_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS api_resource_versions (
 resource_type VARCHAR(80) NOT NULL,
 resource_id BIGINT UNSIGNED NOT NULL,
 version_no BIGINT UNSIGNED NOT NULL DEFAULT 1,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(resource_type,resource_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS api_usage_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 api_token_id BIGINT UNSIGNED NULL,
 request_method VARCHAR(10) NOT NULL,
 request_path VARCHAR(255) NOT NULL,
 response_status INT NOT NULL,
 duration_ms INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(api_token_id) REFERENCES api_tokens(id) ON DELETE SET NULL,
 INDEX idx_api_usage_token_time(api_token_id,created_at)
) ENGINE=InnoDB;
