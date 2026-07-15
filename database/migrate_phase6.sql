USE rockbluffs_exterior;

ALTER TABLE jobs
 ADD COLUMN check_in_at DATETIME NULL AFTER completed_at,
 ADD COLUMN check_out_at DATETIME NULL AFTER check_in_at,
 ADD COLUMN check_in_lat DECIMAL(10,7) NULL AFTER check_out_at,
 ADD COLUMN check_in_lng DECIMAL(10,7) NULL AFTER check_in_lat,
 ADD COLUMN customer_signature_name VARCHAR(190) NULL AFTER check_in_lng,
 ADD COLUMN customer_signature_data MEDIUMTEXT NULL AFTER customer_signature_name,
 ADD COLUMN customer_signed_at DATETIME NULL AFTER customer_signature_data;

CREATE TABLE vehicles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 unit_number VARCHAR(50) NOT NULL UNIQUE,
 year SMALLINT UNSIGNED NULL,
 make VARCHAR(100) NULL,
 model VARCHAR(100) NULL,
 vin VARCHAR(50) NULL,
 plate VARCHAR(50) NULL,
 status ENUM('active','maintenance','out_of_service','retired') NOT NULL DEFAULT 'active',
 odometer INT UNSIGNED NULL,
 next_service_date DATE NULL,
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE inventory_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sku VARCHAR(80) NULL UNIQUE,
 name VARCHAR(180) NOT NULL,
 category VARCHAR(100) NULL,
 quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
 reorder_level DECIMAL(10,2) NOT NULL DEFAULT 0,
 unit VARCHAR(50) NOT NULL DEFAULT 'each',
 unit_cost DECIMAL(12,2) NULL,
 storage_location VARCHAR(150) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE inventory_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 inventory_item_id BIGINT UNSIGNED NOT NULL,
 transaction_type ENUM('receive','use','adjust') NOT NULL,
 quantity DECIMAL(10,2) NOT NULL,
 reference_type VARCHAR(100) NULL,
 reference_id BIGINT UNSIGNED NULL,
 notes VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(inventory_item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE service_packages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL,
 description TEXT NULL,
 package_price DECIMAL(12,2) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE service_package_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 service_package_id BIGINT UNSIGNED NOT NULL,
 service_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
 FOREIGN KEY(service_package_id) REFERENCES service_packages(id) ON DELETE CASCADE,
 FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE technician_locations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 job_id BIGINT UNSIGNED NULL,
 latitude DECIMAL(10,7) NOT NULL,
 longitude DECIMAL(10,7) NOT NULL,
 accuracy_meters DECIMAL(10,2) NULL,
 captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL,
 INDEX idx_tech_location(user_id,captured_at)
) ENGINE=InnoDB;

CREATE TABLE sms_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NULL,
 job_id BIGINT UNSIGNED NULL,
 phone VARCHAR(50) NOT NULL,
 body TEXT NOT NULL,
 provider VARCHAR(50) NOT NULL DEFAULT 'twilio',
 provider_message_id VARCHAR(255) NULL,
 status ENUM('queued','sent','delivered','failed') NOT NULL DEFAULT 'queued',
 error_message TEXT NULL,
 sent_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE system_health_checks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 check_name VARCHAR(100) NOT NULL,
 status ENUM('ok','warning','failed') NOT NULL,
 detail TEXT NULL,
 checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_health_name_time(check_name,checked_at)
) ENGINE=InnoDB;

INSERT INTO email_templates(template_key,name,subject,html_body) VALUES
('payment_receipt','Payment Receipt','Payment receipt for invoice {{invoice_number}}','<p>Hello {{customer_name}},</p><p>Thank you. We received your payment of <strong>{{payment_amount}}</strong> for invoice <strong>{{invoice_number}}</strong>.</p><p>Remaining balance: <strong>{{balance_due}}</strong></p>'),
('technician_en_route','Technician En Route','Your Rock Bluffs technician is on the way','<p>Hello {{customer_name}},</p><p>Your Rock Bluffs Exterior Services technician is on the way to {{service_address}}.</p>')
ON DUPLICATE KEY UPDATE name=VALUES(name),subject=VALUES(subject),html_body=VALUES(html_body);
