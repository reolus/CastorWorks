USE rockbluffs_exterior;

CREATE TABLE IF NOT EXISTS communication_campaigns (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL,
 channel ENUM('email','sms') NOT NULL,
 template_key VARCHAR(100) NOT NULL,
 audience_type ENUM('all_customers','recurring_customers','overdue_invoices','upcoming_jobs','manual') NOT NULL DEFAULT 'manual',
 scheduled_at DATETIME NULL,
 status ENUM('draft','scheduled','processing','completed','cancelled') NOT NULL DEFAULT 'draft',
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_campaign_status(status,scheduled_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS communication_campaign_recipients (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 campaign_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 destination VARCHAR(190) NOT NULL,
 status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
 error_message TEXT NULL,
 processed_at DATETIME NULL,
 FOREIGN KEY(campaign_id) REFERENCES communication_campaigns(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 UNIQUE KEY uq_campaign_customer(campaign_id,customer_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recurring_billing_profiles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 recurring_service_id BIGINT UNSIGNED NULL,
 description VARCHAR(255) NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 frequency ENUM('weekly','biweekly','monthly','quarterly','semiannual','annual','custom') NOT NULL DEFAULT 'monthly',
 interval_days INT UNSIGNED NULL,
 next_bill_date DATE NOT NULL,
 payment_terms_days INT UNSIGNED NOT NULL DEFAULT 15,
 auto_email TINYINT(1) NOT NULL DEFAULT 1,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 FOREIGN KEY(recurring_service_id) REFERENCES recurring_services(id) ON DELETE SET NULL,
 INDEX idx_recurring_bill_due(active,next_bill_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS document_approvals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 document_id BIGINT UNSIGNED NOT NULL,
 requested_by BIGINT UNSIGNED NULL,
 assigned_to BIGINT UNSIGNED NULL,
 status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
 comments TEXT NULL,
 requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 decided_at DATETIME NULL,
 FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE CASCADE,
 FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_document_approval(status,assigned_to)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS graph_sync_diagnostics (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 operation VARCHAR(120) NOT NULL,
 resource_type VARCHAR(80) NULL,
 resource_id VARCHAR(255) NULL,
 status ENUM('ok','warning','failed') NOT NULL,
 http_status INT NULL,
 duration_ms INT NULL,
 detail TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_graph_diag(created_at,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS route_plans (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 route_date DATE NOT NULL,
 assigned_user_id BIGINT UNSIGNED NULL,
 assigned_vehicle_id BIGINT UNSIGNED NULL,
 start_address VARCHAR(255) NULL,
 optimized_distance_miles DECIMAL(10,2) NULL,
 optimized_duration_minutes INT UNSIGNED NULL,
 optimization_method VARCHAR(80) NOT NULL DEFAULT 'nearest_neighbor',
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(assigned_vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uq_route_plan(route_date,assigned_user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS route_plan_stops (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 route_plan_id BIGINT UNSIGNED NOT NULL,
 job_id BIGINT UNSIGNED NOT NULL,
 stop_order INT UNSIGNED NOT NULL,
 estimated_arrival DATETIME NULL,
 distance_from_previous_miles DECIMAL(10,2) NULL,
 duration_from_previous_minutes INT UNSIGNED NULL,
 FOREIGN KEY(route_plan_id) REFERENCES route_plans(id) ON DELETE CASCADE,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 UNIQUE KEY uq_route_job(route_plan_id,job_id)
) ENGINE=InnoDB;
