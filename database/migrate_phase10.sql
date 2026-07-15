USE rockbluffs_exterior;

ALTER TABLE users
 ADD COLUMN notification_email TINYINT(1) NOT NULL DEFAULT 1,
 ADD COLUMN notification_sms TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN notification_teams TINYINT(1) NOT NULL DEFAULT 1,
 ADD COLUMN mobile_phone VARCHAR(50) NULL;

ALTER TABLE customers
 ADD COLUMN quickbooks_customer_id VARCHAR(100) NULL,
 ADD COLUMN quickbooks_synced_at DATETIME NULL;

ALTER TABLE invoices
 ADD COLUMN quickbooks_invoice_id VARCHAR(100) NULL,
 ADD COLUMN quickbooks_synced_at DATETIME NULL;

ALTER TABLE payments
 ADD COLUMN quickbooks_payment_id VARCHAR(100) NULL,
 ADD COLUMN quickbooks_synced_at DATETIME NULL;

ALTER TABLE service_agreements
 ADD COLUMN document_id BIGINT UNSIGNED NULL,
 ADD COLUMN delivered_at DATETIME NULL,
 ADD COLUMN graph_message_id VARCHAR(255) NULL,
 ADD CONSTRAINT fk_agreement_document FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE SET NULL;

CREATE TABLE service_territories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 territory_type ENUM('postal_code','city','radius') NOT NULL DEFAULT 'postal_code',
 city VARCHAR(100) NULL,
 state CHAR(2) NULL,
 postal_code VARCHAR(20) NULL,
 center_lat DECIMAL(10,7) NULL,
 center_lng DECIMAL(10,7) NULL,
 radius_miles DECIMAL(8,2) NULL,
 travel_surcharge DECIMAL(10,2) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE properties ADD COLUMN service_territory_id BIGINT UNSIGNED NULL,
 ADD CONSTRAINT fk_property_territory FOREIGN KEY(service_territory_id) REFERENCES service_territories(id) ON DELETE SET NULL;

CREATE TABLE customer_schedule_changes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 public_token CHAR(64) NOT NULL UNIQUE,
 request_type ENUM('reschedule','cancel') NOT NULL,
 requested_start DATETIME NULL,
 reason TEXT NULL,
 status ENUM('pending','approved','declined','completed') NOT NULL DEFAULT 'pending',
 reviewed_by BIGINT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_schedule_change_status(status,created_at)
) ENGINE=InnoDB;

CREATE TABLE collection_escalation_rules (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 days_overdue INT UNSIGNED NOT NULL,
 action_type ENUM('email','sms','task','final_notice','hold') NOT NULL,
 template_key VARCHAR(100) NULL,
 description VARCHAR(255) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO collection_escalation_rules(days_overdue,action_type,template_key,description) VALUES
(1,'email','invoice_overdue','Friendly email reminder'),
(15,'sms','payment_reminder','SMS payment reminder'),
(30,'final_notice','invoice_overdue','Final written notice'),
(45,'hold',NULL,'Place customer account on service hold')
ON DUPLICATE KEY UPDATE description=VALUES(description);

CREATE TABLE collection_escalation_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_id BIGINT UNSIGNED NOT NULL,
 rule_id BIGINT UNSIGNED NULL,
 action_type VARCHAR(50) NOT NULL,
 status ENUM('queued','sent','completed','failed','skipped') NOT NULL DEFAULT 'queued',
 detail TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
 FOREIGN KEY(rule_id) REFERENCES collection_escalation_rules(id) ON DELETE SET NULL,
 INDEX idx_escalation_invoice(invoice_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE backup_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 backup_type ENUM('database','files','full','restore_test') NOT NULL,
 status ENUM('running','completed','failed') NOT NULL DEFAULT 'running',
 filename VARCHAR(255) NULL,
 size_bytes BIGINT UNSIGNED NULL,
 checksum_sha256 CHAR(64) NULL,
 detail TEXT NULL,
 started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 completed_at DATETIME NULL
) ENGINE=InnoDB;

INSERT INTO email_templates(template_key,name,subject,html_body) VALUES
('agreement_delivery','Service Agreement Ready','Your Rock Bluffs service agreement {{agreement_number}}','<p>Hello {{customer_name}},</p><p>Your service agreement is ready for review and signature.</p><p><a href="{{public_url}}">Review and sign the agreement</a></p>'),
('schedule_change_received','Schedule Change Request Received','We received your scheduling request','<p>Hello {{customer_name}},</p><p>We received your request concerning job {{job_number}}. Our office will review it and contact you shortly.</p>'),
('collections_final_notice','Final Payment Notice','Final notice for invoice {{invoice_number}}','<p>Hello {{customer_name}},</p><p>This is a final notice regarding invoice <strong>{{invoice_number}}</strong>, with a balance of <strong>{{balance_due}}</strong>.</p><p>Please contact our office or use your customer portal to make payment.</p>')
ON DUPLICATE KEY UPDATE name=VALUES(name),subject=VALUES(subject),html_body=VALUES(html_body);
