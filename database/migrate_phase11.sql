USE rockbluffs_exterior;

CREATE TABLE IF NOT EXISTS customer_accounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL UNIQUE,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 status ENUM('active','locked','disabled') NOT NULL DEFAULT 'active',
 email_verified_at DATETIME NULL,
 last_login_at DATETIME NULL,
 failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
 locked_until DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tax_rules (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 rule_type ENUM('tax','discount') NOT NULL,
 calculation_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
 amount DECIMAL(12,4) NOT NULL DEFAULT 0,
 applies_to ENUM('all','residential','commercial','service','territory') NOT NULL DEFAULT 'all',
 reference_id BIGINT UNSIGNED NULL,
 priority INT NOT NULL DEFAULT 100,
 active TINYINT(1) NOT NULL DEFAULT 1,
 starts_at DATETIME NULL,
 ends_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS document_versions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 document_id BIGINT UNSIGNED NOT NULL,
 version_number INT UNSIGNED NOT NULL,
 storage_path VARCHAR(500) NOT NULL,
 sha256 CHAR(64) NULL,
 mime_type VARCHAR(120) NULL,
 file_size BIGINT UNSIGNED NULL,
 change_notes VARCHAR(500) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uq_document_version(document_id,version_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calendar_conflicts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 conflicting_job_id BIGINT UNSIGNED NULL,
 conflict_type ENUM('technician','vehicle','property','external_calendar') NOT NULL,
 detail VARCHAR(500) NULL,
 detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 resolved_at DATETIME NULL,
 resolved_by BIGINT UNSIGNED NULL,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 FOREIGN KEY(conflicting_job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 FOREIGN KEY(resolved_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_conflict_job(job_id,resolved_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quickbooks_mappings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 mapping_type ENUM('income_account','asset_account','payment_method','tax_code','service_item','discount_item') NOT NULL,
 local_reference_type VARCHAR(80) NULL,
 local_reference_id BIGINT UNSIGNED NULL,
 local_key VARCHAR(150) NULL,
 quickbooks_id VARCHAR(120) NOT NULL,
 quickbooks_name VARCHAR(190) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_qb_mapping(mapping_type,local_reference_type,local_reference_id,local_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS schema_migrations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 migration VARCHAR(190) NOT NULL UNIQUE,
 applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE properties ADD COLUMN service_territory_id BIGINT UNSIGNED NULL;
ALTER TABLE properties ADD CONSTRAINT fk_properties_territory FOREIGN KEY(service_territory_id) REFERENCES service_territories(id) ON DELETE SET NULL;
ALTER TABLE estimates ADD COLUMN discount_total DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE estimates ADD COLUMN tax_rule_summary TEXT NULL;
ALTER TABLE documents ADD COLUMN current_version INT UNSIGNED NOT NULL DEFAULT 1;

INSERT IGNORE INTO schema_migrations(migration) VALUES('migrate_phase11.sql');
