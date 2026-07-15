-- Rock Bluffs Exterior Services - Phase 14
ALTER TABLE inventory_items ADD COLUMN asset_tag VARCHAR(100) NULL, ADD COLUMN qr_token VARCHAR(100) NULL, ADD UNIQUE KEY uq_inventory_asset_tag(asset_tag), ADD UNIQUE KEY uq_inventory_qr_token(qr_token);
ALTER TABLE vehicles ADD COLUMN asset_tag VARCHAR(100) NULL, ADD COLUMN qr_token VARCHAR(100) NULL, ADD UNIQUE KEY uq_vehicle_asset_tag(asset_tag), ADD UNIQUE KEY uq_vehicle_qr_token(qr_token);
UPDATE inventory_items SET asset_tag=CONCAT('RB-EQP-',LPAD(id,5,'0')),qr_token=LOWER(HEX(RANDOM_BYTES(16))) WHERE asset_tag IS NULL;
UPDATE vehicles SET asset_tag=CONCAT('RB-VEH-',LPAD(id,4,'0')),qr_token=LOWER(HEX(RANDOM_BYTES(16))) WHERE asset_tag IS NULL;
CREATE TABLE IF NOT EXISTS api_idempotency_keys (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 api_token_id BIGINT UNSIGNED NOT NULL,
 idempotency_key VARCHAR(190) NOT NULL,
 request_method VARCHAR(10) NOT NULL,
 request_path VARCHAR(255) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 response_status INT NOT NULL,
 response_body LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 FOREIGN KEY(api_token_id) REFERENCES api_tokens(id) ON DELETE CASCADE,
 UNIQUE KEY uq_api_idempotency(api_token_id,idempotency_key),
 INDEX idx_api_idempotency_expiry(expires_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS certification_alerts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_certification_id BIGINT UNSIGNED NOT NULL,
 alert_days INT NOT NULL,
 delivery_channel ENUM('portal','email','teams') NOT NULL DEFAULT 'portal',
 sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_cert_alert(employee_certification_id,alert_days,delivery_channel),
 FOREIGN KEY(employee_certification_id) REFERENCES employee_certifications(id) ON DELETE CASCADE
) ENGINE=InnoDB;
