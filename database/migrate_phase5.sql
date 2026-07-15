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
