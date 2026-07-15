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
