USE rockbluffs_exterior;

ALTER TABLE jobs
 ADD COLUMN graph_event_id VARCHAR(255) NULL AFTER customer_signed_at,
 ADD COLUMN graph_change_key VARCHAR(255) NULL AFTER graph_event_id,
 ADD COLUMN graph_last_synced_at DATETIME NULL AFTER graph_change_key,
 ADD COLUMN assigned_vehicle_id BIGINT UNSIGNED NULL AFTER assigned_user_id,
 ADD CONSTRAINT fk_jobs_vehicle FOREIGN KEY (assigned_vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL;

CREATE TABLE maintenance_work_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 work_order_number VARCHAR(60) NOT NULL UNIQUE,
 asset_type ENUM('vehicle','equipment') NOT NULL,
 vehicle_id BIGINT UNSIGNED NULL,
 inventory_item_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL,
 description TEXT NULL,
 priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
 status ENUM('open','scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
 scheduled_date DATE NULL,
 completed_date DATE NULL,
 estimated_cost DECIMAL(12,2) NULL,
 actual_cost DECIMAL(12,2) NULL,
 odometer INT UNSIGNED NULL,
 assigned_user_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
 FOREIGN KEY(inventory_item_id) REFERENCES inventory_items(id) ON DELETE SET NULL,
 FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_mwo_status(status,scheduled_date)
) ENGINE=InnoDB;

CREATE TABLE purchase_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 po_number VARCHAR(60) NOT NULL UNIQUE,
 vendor_name VARCHAR(190) NOT NULL,
 vendor_email VARCHAR(190) NULL,
 status ENUM('draft','submitted','approved','ordered','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
 order_date DATE NULL,
 expected_date DATE NULL,
 subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
 tax DECIMAL(12,2) NOT NULL DEFAULT 0,
 shipping DECIMAL(12,2) NOT NULL DEFAULT 0,
 total DECIMAL(12,2) NOT NULL DEFAULT 0,
 notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 approved_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE purchase_order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 purchase_order_id BIGINT UNSIGNED NOT NULL,
 inventory_item_id BIGINT UNSIGNED NULL,
 description VARCHAR(255) NOT NULL,
 quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
 quantity_received DECIMAL(10,2) NOT NULL DEFAULT 0,
 unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
 line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
 FOREIGN KEY(purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
 FOREIGN KEY(inventory_item_id) REFERENCES inventory_items(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE job_inventory_usage (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 inventory_item_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(10,2) NOT NULL,
 unit_cost DECIMAL(12,2) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 FOREIGN KEY(inventory_item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_job_usage(job_id)
) ENGINE=InnoDB;

CREATE TABLE sms_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 template_key VARCHAR(100) NOT NULL UNIQUE,
 name VARCHAR(150) NOT NULL,
 body TEXT NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE sms_messages
 ADD COLUMN provider_status VARCHAR(80) NULL AFTER status,
 ADD COLUMN delivered_at DATETIME NULL AFTER sent_at,
 ADD COLUMN status_callback_at DATETIME NULL AFTER delivered_at;

CREATE TABLE time_entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 job_id BIGINT UNSIGNED NULL,
 vehicle_id BIGINT UNSIGNED NULL,
 clock_in DATETIME NOT NULL,
 clock_out DATETIME NULL,
 break_minutes INT UNSIGNED NOT NULL DEFAULT 0,
 entry_type ENUM('job','travel','shop','training','administrative') NOT NULL DEFAULT 'job',
 hourly_cost DECIMAL(10,2) NULL,
 notes VARCHAR(500) NULL,
 approved_by BIGINT UNSIGNED NULL,
 approved_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL,
 FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
 FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_time_user_date(user_id,clock_in),
 INDEX idx_time_job(job_id)
) ENGINE=InnoDB;

INSERT INTO sms_templates(template_key,name,body) VALUES
('appointment_confirmed','Appointment Confirmed','Rock Bluffs Exterior Services: Your appointment is scheduled for {{scheduled_start}} at {{service_address}}.'),
('technician_en_route','Technician En Route','Rock Bluffs Exterior Services: {{technician_name}} is on the way to {{service_address}}.'),
('job_complete','Job Complete','Rock Bluffs Exterior Services: Your service is complete. Thank you for choosing Rock Bluffs!'),
('payment_reminder','Payment Reminder','Rock Bluffs Exterior Services: Invoice {{invoice_number}} has a balance of {{balance_due}}. Please use your customer portal to pay.')
ON DUPLICATE KEY UPDATE name=VALUES(name),body=VALUES(body);
