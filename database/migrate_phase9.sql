-- Rock Bluffs Exterior Services - Phase 9
CREATE TABLE employee_availability (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 availability_date DATE NOT NULL,
 start_time TIME NULL,
 end_time TIME NULL,
 status ENUM('available','unavailable','limited','time_off') NOT NULL DEFAULT 'available',
 notes VARCHAR(500) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uq_user_availability(user_id,availability_date)
) ENGINE=InnoDB;

CREATE TABLE scheduling_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 public_token CHAR(64) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 property_id BIGINT UNSIGNED NULL,
 service_id BIGINT UNSIGNED NULL,
 preferred_date DATE NOT NULL,
 alternate_date DATE NULL,
 preferred_window ENUM('morning','afternoon','evening','any') NOT NULL DEFAULT 'any',
 notes TEXT NULL,
 status ENUM('requested','approved','declined','scheduled','cancelled') NOT NULL DEFAULT 'requested',
 job_id BIGINT UNSIGNED NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 FOREIGN KEY(property_id) REFERENCES properties(id) ON DELETE SET NULL,
 FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE SET NULL,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL,
 FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_sched_status_date(status,preferred_date)
) ENGINE=InnoDB;

CREATE TABLE service_agreements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 agreement_number VARCHAR(50) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 property_id BIGINT UNSIGNED NULL,
 title VARCHAR(200) NOT NULL,
 agreement_type ENUM('residential','commercial','recurring','maintenance','custom') NOT NULL DEFAULT 'residential',
 body LONGTEXT NOT NULL,
 status ENUM('draft','sent','viewed','signed','expired','cancelled') NOT NULL DEFAULT 'draft',
 public_token CHAR(64) NOT NULL UNIQUE,
 effective_date DATE NULL,
 expiration_date DATE NULL,
 signed_name VARCHAR(200) NULL,
 signed_email VARCHAR(255) NULL,
 signature_data LONGTEXT NULL,
 signed_ip VARCHAR(64) NULL,
 signed_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 FOREIGN KEY(property_id) REFERENCES properties(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_agreement_customer_status(customer_id,status)
) ENGINE=InnoDB;

CREATE TABLE collection_actions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_id BIGINT UNSIGNED NOT NULL,
 action_type ENUM('email','sms','phone','letter','note','promise_to_pay','hold','escalation') NOT NULL,
 action_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 amount_promised DECIMAL(12,2) NULL,
 promise_date DATE NULL,
 notes TEXT NULL,
 status ENUM('open','completed','cancelled') NOT NULL DEFAULT 'completed',
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_collection_invoice_date(invoice_id,action_date)
) ENGINE=InnoDB;

CREATE TABLE accounting_connections (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider ENUM('quickbooks') NOT NULL,
 realm_id VARCHAR(150) NULL,
 access_token_enc LONGTEXT NULL,
 refresh_token_enc LONGTEXT NULL,
 token_expires_at DATETIME NULL,
 refresh_expires_at DATETIME NULL,
 status ENUM('disconnected','connected','expired','error') NOT NULL DEFAULT 'disconnected',
 last_sync_at DATETIME NULL,
 last_error TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_accounting_provider(provider)
) ENGINE=InnoDB;

CREATE TABLE accounting_sync_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider ENUM('quickbooks') NOT NULL,
 entity_type VARCHAR(80) NOT NULL,
 entity_id BIGINT UNSIGNED NULL,
 external_id VARCHAR(150) NULL,
 direction ENUM('push','pull') NOT NULL DEFAULT 'push',
 status ENUM('success','failed','skipped') NOT NULL,
 message TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_accounting_sync(provider,entity_type,created_at)
) ENGINE=InnoDB;

CREATE TABLE system_metrics (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 metric_key VARCHAR(120) NOT NULL,
 metric_value DECIMAL(18,4) NULL,
 metric_text VARCHAR(500) NULL,
 status ENUM('ok','warning','critical','unknown') NOT NULL DEFAULT 'ok',
 measured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_metric_key_time(metric_key,measured_at)
) ENGINE=InnoDB;

CREATE TABLE route_optimization_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 route_date DATE NOT NULL,
 provider VARCHAR(80) NOT NULL DEFAULT 'local_nearest_neighbor',
 job_count INT UNSIGNED NOT NULL DEFAULT 0,
 original_distance_miles DECIMAL(10,2) NULL,
 optimized_distance_miles DECIMAL(10,2) NULL,
 duration_ms INT UNSIGNED NULL,
 status ENUM('success','partial','failed') NOT NULL,
 details TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_route_run_date(route_date,created_at)
) ENGINE=InnoDB;

INSERT INTO accounting_connections(provider,status) VALUES('quickbooks','disconnected')
ON DUPLICATE KEY UPDATE provider=VALUES(provider);
