-- Rock Bluffs Exterior Services - Phase 13
CREATE TABLE IF NOT EXISTS conversation_threads (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 job_id BIGINT UNSIGNED NULL,
 subject VARCHAR(255) NOT NULL,
 status ENUM('open','waiting_customer','waiting_staff','closed') NOT NULL DEFAULT 'open',
 priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
 assigned_user_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL,
 last_message_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL,
 FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_conversation_status(status,last_message_at),
 INDEX idx_conversation_customer(customer_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conversation_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 conversation_thread_id BIGINT UNSIGNED NOT NULL,
 sender_type ENUM('staff','customer','system') NOT NULL DEFAULT 'staff',
 sender_user_id BIGINT UNSIGNED NULL,
 channel ENUM('portal','email','sms','phone','internal') NOT NULL DEFAULT 'portal',
 body TEXT NOT NULL,
 external_message_id VARCHAR(255) NULL,
 is_internal TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(conversation_thread_id) REFERENCES conversation_threads(id) ON DELETE CASCADE,
 FOREIGN KEY(sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_message_thread(conversation_thread_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inspection_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(190) NOT NULL,
 entity_type ENUM('job','vehicle','property','equipment') NOT NULL DEFAULT 'job',
 description TEXT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inspection_template_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 inspection_template_id BIGINT UNSIGNED NOT NULL,
 section_name VARCHAR(190) NULL,
 label VARCHAR(255) NOT NULL,
 field_type ENUM('checkbox','text','textarea','number','select','photo','signature') NOT NULL DEFAULT 'checkbox',
 options_json JSON NULL,
 required TINYINT(1) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 100,
 FOREIGN KEY(inspection_template_id) REFERENCES inspection_templates(id) ON DELETE CASCADE,
 INDEX idx_inspection_items(inspection_template_id,sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inspections (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 inspection_template_id BIGINT UNSIGNED NOT NULL,
 entity_type ENUM('job','vehicle','property','equipment') NOT NULL,
 entity_id BIGINT UNSIGNED NOT NULL,
 status ENUM('draft','completed','approved','rejected') NOT NULL DEFAULT 'draft',
 completed_by BIGINT UNSIGNED NULL,
 completed_at DATETIME NULL,
 approved_by BIGINT UNSIGNED NULL,
 approved_at DATETIME NULL,
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(inspection_template_id) REFERENCES inspection_templates(id) ON DELETE RESTRICT,
 FOREIGN KEY(completed_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_inspection_entity(entity_type,entity_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inspection_responses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 inspection_id BIGINT UNSIGNED NOT NULL,
 inspection_template_item_id BIGINT UNSIGNED NOT NULL,
 response_text TEXT NULL,
 response_json JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
 FOREIGN KEY(inspection_template_item_id) REFERENCES inspection_template_items(id) ON DELETE CASCADE,
 UNIQUE KEY uq_inspection_response(inspection_id,inspection_template_item_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS custom_field_definitions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 entity_type ENUM('customer','property','lead','estimate','job','invoice','vehicle','equipment','user') NOT NULL,
 field_key VARCHAR(100) NOT NULL,
 label VARCHAR(190) NOT NULL,
 field_type ENUM('text','textarea','number','date','datetime','checkbox','select','email','phone','url') NOT NULL DEFAULT 'text',
 options_json JSON NULL,
 required TINYINT(1) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 100,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_custom_field(entity_type,field_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS custom_field_values (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 custom_field_definition_id BIGINT UNSIGNED NOT NULL,
 entity_id BIGINT UNSIGNED NOT NULL,
 value_text LONGTEXT NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(custom_field_definition_id) REFERENCES custom_field_definitions(id) ON DELETE CASCADE,
 FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uq_custom_value(custom_field_definition_id,entity_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipment_custody (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 inventory_item_id BIGINT UNSIGNED NULL,
 vehicle_id BIGINT UNSIGNED NULL,
 assigned_user_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
 issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expected_return_at DATETIME NULL,
 returned_at DATETIME NULL,
 condition_out VARCHAR(255) NULL,
 condition_in VARCHAR(255) NULL,
 notes TEXT NULL,
 issued_by BIGINT UNSIGNED NULL,
 returned_to BIGINT UNSIGNED NULL,
 FOREIGN KEY(inventory_item_id) REFERENCES inventory_items(id) ON DELETE SET NULL,
 FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
 FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(issued_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(returned_to) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_custody_open(assigned_user_id,returned_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employee_certifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 certification_name VARCHAR(190) NOT NULL,
 issuing_organization VARCHAR(190) NULL,
 credential_number VARCHAR(120) NULL,
 issued_date DATE NULL,
 expires_date DATE NULL,
 status ENUM('active','expired','suspended','revoked') NOT NULL DEFAULT 'active',
 document_path VARCHAR(500) NULL,
 notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_cert_expiry(expires_date,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS job_notes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 note_type ENUM('general','customer','safety','completion','internal') NOT NULL DEFAULT 'general',
 body TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_job_notes(job_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS work_order_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(190) NOT NULL,
 service_id BIGINT UNSIGNED NULL,
 default_duration_minutes INT UNSIGNED NULL,
 service_summary VARCHAR(500) NULL,
 instructions TEXT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS work_order_template_tasks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 work_order_template_id BIGINT UNSIGNED NOT NULL,
 task_label VARCHAR(255) NOT NULL,
 required TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 100,
 FOREIGN KEY(work_order_template_id) REFERENCES work_order_templates(id) ON DELETE CASCADE,
 INDEX idx_work_order_tasks(work_order_template_id,sort_order)
) ENGINE=InnoDB;
