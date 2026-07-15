USE rockbluffs_exterior;

ALTER TABLE estimates ADD COLUMN public_token CHAR(64) NULL UNIQUE AFTER estimate_number,
 ADD COLUMN accepted_at DATETIME NULL AFTER status,
 ADD COLUMN declined_at DATETIME NULL AFTER accepted_at;
UPDATE estimates SET public_token=SHA2(CONCAT(UUID(),id,RAND()),256) WHERE public_token IS NULL;

ALTER TABLE jobs ADD COLUMN started_at DATETIME NULL AFTER status,
 ADD COLUMN completed_at DATETIME NULL AFTER started_at;
ALTER TABLE users ADD COLUMN entra_email VARCHAR(190) NULL AFTER microsoft_object_id;

CREATE TABLE property_contacts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 property_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NULL,
 phone VARCHAR(50) NULL,
 role_label VARCHAR(100) NULL,
 FOREIGN KEY(property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE recurring_services (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 property_id BIGINT UNSIGNED NULL,
 service_id BIGINT UNSIGNED NULL,
 name VARCHAR(180) NOT NULL,
 frequency ENUM('weekly','biweekly','monthly','quarterly','semiannual','annual','custom') NOT NULL,
 interval_days INT UNSIGNED NULL,
 next_service_date DATE NOT NULL,
 preferred_start_time TIME NULL,
 assigned_user_id BIGINT UNSIGNED NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id),
 FOREIGN KEY(property_id) REFERENCES properties(id),
 FOREIGN KEY(service_id) REFERENCES services(id),
 FOREIGN KEY(assigned_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE job_checklist_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 label VARCHAR(255) NOT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 completed TINYINT(1) NOT NULL DEFAULT 0,
 completed_by BIGINT UNSIGNED NULL,
 completed_at DATETIME NULL,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 FOREIGN KEY(completed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE job_photos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 photo_type ENUM('before','during','after','damage','other') NOT NULL DEFAULT 'other',
 filename VARCHAR(255) NOT NULL,
 storage_path VARCHAR(500) NOT NULL,
 sharepoint_url TEXT NULL,
 caption VARCHAR(255) NULL,
 uploaded_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE communication_queue (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 channel ENUM('email','calendar','sharepoint','teams') NOT NULL,
 action VARCHAR(100) NOT NULL,
 payload JSON NOT NULL,
 status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_error TEXT NULL,
 completed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_queue_ready(status,available_at)
) ENGINE=InnoDB;

CREATE TABLE graph_subscriptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 subscription_id VARCHAR(255) NOT NULL UNIQUE,
 resource VARCHAR(500) NOT NULL,
 change_type VARCHAR(100) NOT NULL,
 expiration_at DATETIME NOT NULL,
 client_state VARCHAR(255) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 entity_type VARCHAR(100) NOT NULL,
 entity_id BIGINT UNSIGNED NOT NULL,
 document_type VARCHAR(100) NOT NULL,
 filename VARCHAR(255) NOT NULL,
 local_path VARCHAR(500) NULL,
 sharepoint_item_id VARCHAR(255) NULL,
 sharepoint_url TEXT NULL,
 uploaded_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_documents_entity(entity_type,entity_id)
) ENGINE=InnoDB;
