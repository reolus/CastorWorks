CREATE DATABASE IF NOT EXISTS rockbluffs_exterior CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rockbluffs_exterior;

CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('administrator','owner','office','estimator','crew_leader','technician') NOT NULL DEFAULT 'technician',
 status ENUM('active','disabled') NOT NULL DEFAULT 'active',
 microsoft_object_id VARCHAR(100) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE leads (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL,
 phone VARCHAR(50) NOT NULL,
 address VARCHAR(255) NULL,
 service_requested VARCHAR(150) NOT NULL,
 details TEXT NULL,
 status ENUM('new','contacted','quoted','won','lost') NOT NULL DEFAULT 'new',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_leads_status(status), INDEX idx_leads_created(created_at)
) ENGINE=InnoDB;

CREATE TABLE customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_type ENUM('residential','commercial') NOT NULL DEFAULT 'residential',
 display_name VARCHAR(180) NOT NULL,
 contact_name VARCHAR(150) NULL,
 email VARCHAR(190) NULL,
 phone VARCHAR(50) NULL,
 billing_address VARCHAR(255) NULL,
 status ENUM('lead','active','inactive') NOT NULL DEFAULT 'active',
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE properties (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 label VARCHAR(100) NOT NULL DEFAULT 'Primary Property',
 address1 VARCHAR(190) NOT NULL,
 address2 VARCHAR(190) NULL,
 city VARCHAR(100) NOT NULL,
 state CHAR(2) NOT NULL DEFAULT 'NE',
 postal_code VARCHAR(20) NOT NULL,
 gate_code VARCHAR(100) NULL,
 access_notes TEXT NULL,
 pet_notes TEXT NULL,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE services (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 category VARCHAR(100) NOT NULL,
 description TEXT NULL,
 default_price DECIMAL(10,2) NULL,
 unit_label VARCHAR(50) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE estimates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 estimate_number VARCHAR(30) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 property_id BIGINT UNSIGNED NULL,
 status ENUM('draft','sent','accepted','declined','expired') NOT NULL DEFAULT 'draft',
 subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
 tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 total DECIMAL(12,2) NOT NULL DEFAULT 0,
 valid_until DATE NULL,
 notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id), FOREIGN KEY(property_id) REFERENCES properties(id), FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE estimate_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 estimate_id BIGINT UNSIGNED NOT NULL,
 service_id BIGINT UNSIGNED NULL,
 description VARCHAR(255) NOT NULL,
 quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
 unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
 line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
 FOREIGN KEY(estimate_id) REFERENCES estimates(id) ON DELETE CASCADE, FOREIGN KEY(service_id) REFERENCES services(id)
) ENGINE=InnoDB;

CREATE TABLE jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_number VARCHAR(30) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 property_id BIGINT UNSIGNED NULL,
 estimate_id BIGINT UNSIGNED NULL,
 service_summary VARCHAR(255) NOT NULL,
 scheduled_start DATETIME NULL,
 scheduled_end DATETIME NULL,
 status ENUM('unscheduled','scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'unscheduled',
 assigned_user_id BIGINT UNSIGNED NULL,
 graph_event_id VARCHAR(255) NULL,
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id), FOREIGN KEY(property_id) REFERENCES properties(id), FOREIGN KEY(estimate_id) REFERENCES estimates(id), FOREIGN KEY(assigned_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE invoices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_number VARCHAR(30) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 job_id BIGINT UNSIGNED NULL,
 status ENUM('draft','sent','paid','overdue','void') NOT NULL DEFAULT 'draft',
 issue_date DATE NOT NULL,
 due_date DATE NULL,
 subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
 tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 total DECIMAL(12,2) NOT NULL DEFAULT 0,
 balance_due DECIMAL(12,2) NOT NULL DEFAULT 0,
 sharepoint_url TEXT NULL,
 graph_message_id VARCHAR(255) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id), FOREIGN KEY(job_id) REFERENCES jobs(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_id BIGINT UNSIGNED NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 method ENUM('cash','check','card','ach','other') NOT NULL,
 reference VARCHAR(150) NULL,
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 action VARCHAR(150) NOT NULL,
 entity_type VARCHAR(100) NULL,
 entity_id BIGINT UNSIGNED NULL,
 metadata JSON NULL,
 ip_address VARCHAR(45) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_audit_created(created_at), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO services(name,category,description,default_price,unit_label) VALUES
('Exterior Window Cleaning','Window Cleaning','Exterior glass cleaning',8.00,'pane'),
('Interior Window Cleaning','Window Cleaning','Interior glass cleaning',6.00,'pane'),
('Screen Cleaning','Window Cleaning','Screen wash and reinstall',4.00,'screen'),
('Track Cleaning','Window Cleaning','Vacuum and detail tracks',4.00,'track'),
('House Washing','Exterior Cleaning','Low-pressure siding wash',NULL,'project'),
('Pressure Washing','Exterior Cleaning','Hard surface pressure washing',NULL,'sq ft'),
('Concrete Cleaning','Exterior Cleaning','Driveways, walks, and pads',NULL,'sq ft'),
('Gutter Cleaning','Exterior Cleaning','Debris removal and flow check',NULL,'linear ft');

-- Phase 3 additions
ALTER TABLE estimates ADD COLUMN public_token CHAR(64) NULL UNIQUE AFTER estimate_number,
 ADD COLUMN accepted_at DATETIME NULL AFTER status,
 ADD COLUMN declined_at DATETIME NULL AFTER accepted_at;
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
USE rockbluffs_exterior;

ALTER TABLE customers ADD COLUMN portal_token CHAR(64) NULL UNIQUE AFTER status,
 ADD COLUMN portal_token_expires_at DATETIME NULL AFTER portal_token;
ALTER TABLE estimates ADD COLUMN customer_signature_name VARCHAR(190) NULL AFTER declined_at,
 ADD COLUMN customer_signature_data MEDIUMTEXT NULL AFTER customer_signature_name,
 ADD COLUMN signed_at DATETIME NULL AFTER customer_signature_data;
ALTER TABLE invoices ADD COLUMN public_token CHAR(64) NULL UNIQUE AFTER invoice_number;

CREATE TABLE email_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 template_key VARCHAR(100) NOT NULL UNIQUE,
 name VARCHAR(150) NOT NULL,
 subject VARCHAR(255) NOT NULL,
 html_body MEDIUMTEXT NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE portal_access_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(100) NOT NULL,
 ip_address VARCHAR(45) NULL,
 user_agent VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 INDEX idx_portal_access(customer_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE notification_preferences (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL UNIQUE,
 email_estimates TINYINT(1) NOT NULL DEFAULT 1,
 email_invoices TINYINT(1) NOT NULL DEFAULT 1,
 email_appointments TINYINT(1) NOT NULL DEFAULT 1,
 email_marketing TINYINT(1) NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO email_templates(template_key,name,subject,html_body) VALUES
('estimate_sent','Estimate Sent','Your estimate {{estimate_number}} from Rock Bluffs Exterior Services','<p>Hello {{customer_name}},</p><p>Your estimate <strong>{{estimate_number}}</strong> is ready.</p><p><a href="{{public_url}}">Review and approve your estimate</a></p><p>Total: <strong>{{total}}</strong></p>'),
('invoice_sent','Invoice Sent','Invoice {{invoice_number}} from Rock Bluffs Exterior Services','<p>Hello {{customer_name}},</p><p>Your invoice <strong>{{invoice_number}}</strong> is ready.</p><p><a href="{{public_url}}">View your invoice</a></p><p>Balance due: <strong>{{balance_due}}</strong></p>'),
('appointment_confirmation','Appointment Confirmation','Your Rock Bluffs appointment is scheduled','<p>Hello {{customer_name}},</p><p>Your service is scheduled for <strong>{{scheduled_start}}</strong>.</p><p>Service: {{service_summary}}</p>')
ON DUPLICATE KEY UPDATE name=VALUES(name);
USE rockbluffs_exterior;

ALTER TABLE jobs ADD COLUMN route_order INT UNSIGNED NULL AFTER scheduled_end,
 ADD COLUMN route_date DATE NULL AFTER route_order;

ALTER TABLE documents ADD COLUMN storage_status ENUM('local','queued','uploaded','failed') NOT NULL DEFAULT 'local' AFTER local_path,
 ADD COLUMN upload_error TEXT NULL AFTER sharepoint_url,
 ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE TABLE IF NOT EXISTS payment_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_id BIGINT UNSIGNED NOT NULL,
 provider VARCHAR(50) NOT NULL DEFAULT 'stripe',
 provider_session_id VARCHAR(255) NOT NULL UNIQUE,
 checkout_url TEXT NULL,
 amount DECIMAL(12,2) NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'usd',
 status ENUM('created','open','paid','expired','failed') NOT NULL DEFAULT 'created',
 expires_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
 INDEX idx_payment_sessions_invoice(invoice_id),
 INDEX idx_payment_sessions_status(status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notification_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NULL,
 entity_type VARCHAR(100) NULL,
 entity_id BIGINT UNSIGNED NULL,
 channel ENUM('email','teams','system') NOT NULL,
 template_key VARCHAR(100) NULL,
 recipient VARCHAR(255) NULL,
 subject VARCHAR(255) NULL,
 status ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
 provider_id VARCHAR(255) NULL,
 error_message TEXT NULL,
 sent_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 INDEX idx_notification_entity(entity_type,entity_id),
 INDEX idx_notification_status(status,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS webhook_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider VARCHAR(50) NOT NULL,
 provider_event_id VARCHAR(255) NOT NULL,
 event_type VARCHAR(150) NOT NULL,
 payload JSON NOT NULL,
 processed_at DATETIME NULL,
 status ENUM('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
 error_message TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_webhook_provider_event(provider,provider_event_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(190) NOT NULL,
 ip_address VARCHAR(45) NOT NULL,
 succeeded TINYINT(1) NOT NULL DEFAULT 0,
 attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_login_attempts(email,ip_address,attempted_at)
) ENGINE=InnoDB;

INSERT INTO email_templates(template_key,name,subject,html_body) VALUES
('appointment_reminder','Appointment Reminder','Reminder: Rock Bluffs service on {{scheduled_start}}','<p>Hello {{customer_name}},</p><p>This is a reminder that your Rock Bluffs Exterior Services appointment is scheduled for <strong>{{scheduled_start}}</strong>.</p><p>{{service_summary}}</p>'),
('invoice_overdue','Overdue Invoice Reminder','Reminder: invoice {{invoice_number}} is overdue','<p>Hello {{customer_name}},</p><p>Invoice <strong>{{invoice_number}}</strong> has a remaining balance of <strong>{{balance_due}}</strong>.</p><p><a href="{{public_url}}">View and pay your invoice</a></p>'),
('recurring_service_due','Recurring Service Reminder','It may be time for your next Rock Bluffs service','<p>Hello {{customer_name}},</p><p>Your recurring {{service_name}} service is due soon.</p><p>Please contact us or use your customer portal to schedule.</p>')
ON DUPLICATE KEY UPDATE name=VALUES(name),subject=VALUES(subject),html_body=VALUES(html_body);
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
USE rockbluffs_exterior;

ALTER TABLE jobs
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

-- Phase 8 additions
SOURCE database/migrate_phase8.sql;

-- Phase 9 additions
SOURCE database/migrate_phase9.sql;

-- Phase 10 additions
SOURCE database/migrate_phase10.sql;

-- Phase 11 additions
SOURCE database/migrate_phase11.sql;

-- Phase 12 additions
SOURCE database/migrate_phase12.sql;

-- Phase 13 additions
SOURCE database/migrate_phase13.sql;

-- Phase 14 additions
SOURCE database/migrate_phase14.sql;

-- Phase 15 final-schema additions
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


-- Phase 16 additions
SOURCE database/migrate_phase16.sql;

-- Phase 17 additions
CREATE TABLE IF NOT EXISTS notification_routes (
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
CREATE TABLE IF NOT EXISTS certification_approval_attachments (
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
CREATE TABLE IF NOT EXISTS asset_replacement_plans (
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
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_tokens' AND COLUMN_NAME='daily_quota')=0,'ALTER TABLE api_tokens ADD COLUMN daily_quota INT UNSIGNED NOT NULL DEFAULT 600 AFTER abilities_json','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_tokens' AND COLUMN_NAME='monthly_quota')=0,'ALTER TABLE api_tokens ADD COLUMN monthly_quota INT UNSIGNED NOT NULL DEFAULT 10000 AFTER daily_quota','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- Phase 18 additions
SOURCE database/migrate_phase18.sql;

-- Phase 19 additions
SOURCE database/migrate_phase19.sql;

-- Phase 20 additions
SOURCE database/migrate_phase20.sql;

-- Phase 21: Microsoft Entra user synchronization
ALTER TABLE users ADD COLUMN entra_upn VARCHAR(190) NULL AFTER entra_email;
ALTER TABLE users ADD COLUMN department VARCHAR(150) NULL AFTER entra_upn;
ALTER TABLE users ADD COLUMN job_title VARCHAR(150) NULL AFTER department;
ALTER TABLE users ADD COLUMN office_location VARCHAR(150) NULL AFTER job_title;
ALTER TABLE users ADD COLUMN business_phone VARCHAR(60) NULL AFTER office_location;
ALTER TABLE users ADD COLUMN mobile_phone VARCHAR(60) NULL AFTER business_phone;
ALTER TABLE users ADD COLUMN identity_source ENUM('local','entra') NOT NULL DEFAULT 'local' AFTER mobile_phone;
ALTER TABLE users ADD COLUMN last_synced_at DATETIME NULL AFTER identity_source;
CREATE TABLE entra_sync_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sync_type ENUM('import','full_sync','scheduled_sync') NOT NULL,
 created_count INT UNSIGNED NOT NULL DEFAULT 0,
 updated_count INT UNSIGNED NOT NULL DEFAULT 0,
 disabled_count INT UNSIGNED NOT NULL DEFAULT 0,
 error_count INT UNSIGNED NOT NULL DEFAULT 0,
 error_detail TEXT NULL,
 completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_entra_sync_completed(completed_at)
) ENGINE=InnoDB;

-- Phase 22: Entra group access, sync policy, manager and assignment fields
SOURCE database/migrate_phase22.sql;
