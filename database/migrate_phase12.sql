USE rockbluffs_exterior;

CREATE TABLE IF NOT EXISTS workflow_rules (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(190) NOT NULL,
 event_key VARCHAR(120) NOT NULL,
 conditions_json JSON NULL,
 action_key VARCHAR(120) NOT NULL,
 action_config_json JSON NULL,
 priority INT NOT NULL DEFAULT 100,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_workflow_event(event_key,active,priority)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS workflow_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 workflow_rule_id BIGINT UNSIGNED NOT NULL,
 event_key VARCHAR(120) NOT NULL,
 entity_type VARCHAR(100) NULL,
 entity_id BIGINT UNSIGNED NULL,
 status ENUM('queued','running','completed','failed','skipped') NOT NULL DEFAULT 'queued',
 context_json JSON NULL,
 result_json JSON NULL,
 error_message TEXT NULL,
 queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 started_at DATETIME NULL,
 completed_at DATETIME NULL,
 FOREIGN KEY(workflow_rule_id) REFERENCES workflow_rules(id) ON DELETE CASCADE,
 INDEX idx_workflow_run_status(status,queued_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS approval_rules (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(190) NOT NULL,
 entity_type ENUM('estimate','job') NOT NULL,
 trigger_field VARCHAR(100) NOT NULL DEFAULT 'total',
 operator ENUM('gt','gte','lt','lte','eq','always') NOT NULL DEFAULT 'gte',
 threshold_value DECIMAL(12,2) NULL,
 required_role VARCHAR(60) NOT NULL DEFAULT 'owner',
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS entity_approvals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 entity_type ENUM('estimate','job') NOT NULL,
 entity_id BIGINT UNSIGNED NOT NULL,
 approval_rule_id BIGINT UNSIGNED NULL,
 status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
 requested_by BIGINT UNSIGNED NULL,
 assigned_role VARCHAR(60) NULL,
 decided_by BIGINT UNSIGNED NULL,
 request_note TEXT NULL,
 decision_note TEXT NULL,
 requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 decided_at DATETIME NULL,
 FOREIGN KEY(approval_rule_id) REFERENCES approval_rules(id) ON DELETE SET NULL,
 FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(decided_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uq_entity_pending(entity_type,entity_id,status),
 INDEX idx_entity_approval(status,assigned_role)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_password_resets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_account_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 requested_ip VARCHAR(64) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_account_id) REFERENCES customer_accounts(id) ON DELETE CASCADE,
 INDEX idx_password_reset_expiry(expires_at,used_at)
) ENGINE=InnoDB;

ALTER TABLE customer_accounts
 ADD COLUMN password_changed_at DATETIME NULL,
 ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN security_email_sent_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS executive_report_schedules (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(190) NOT NULL,
 report_key VARCHAR(100) NOT NULL,
 frequency ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
 day_of_week TINYINT UNSIGNED NULL,
 day_of_month TINYINT UNSIGNED NULL,
 send_time TIME NOT NULL DEFAULT '07:00:00',
 recipients TEXT NOT NULL,
 include_pdf TINYINT(1) NOT NULL DEFAULT 1,
 active TINYINT(1) NOT NULL DEFAULT 1,
 last_run_at DATETIME NULL,
 next_run_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_report_due(active,next_run_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS executive_report_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 schedule_id BIGINT UNSIGNED NULL,
 report_key VARCHAR(100) NOT NULL,
 period_start DATE NOT NULL,
 period_end DATE NOT NULL,
 recipients TEXT NULL,
 status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
 document_id BIGINT UNSIGNED NULL,
 error_message TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 sent_at DATETIME NULL,
 FOREIGN KEY(schedule_id) REFERENCES executive_report_schedules(id) ON DELETE SET NULL,
 FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE webhook_events
 ADD COLUMN headers_json JSON NULL AFTER event_type,
 ADD COLUMN attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
 ADD COLUMN last_error TEXT NULL AFTER attempts;

CREATE TABLE IF NOT EXISTS webhook_replays (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 webhook_event_id BIGINT UNSIGNED NOT NULL,
 requested_by BIGINT UNSIGNED NULL,
 status ENUM('queued','completed','failed') NOT NULL DEFAULT 'queued',
 result_text TEXT NULL,
 requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 completed_at DATETIME NULL,
 FOREIGN KEY(webhook_event_id) REFERENCES webhook_events(id) ON DELETE CASCADE,
 FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS api_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 abilities_json JSON NULL,
 last_used_at DATETIME NULL,
 expires_at DATETIME NULL,
 revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_api_token_active(expires_at,revoked_at)
) ENGINE=InnoDB;
