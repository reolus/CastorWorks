CREATE TABLE IF NOT EXISTS communication_providers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_key VARCHAR(100) NOT NULL,
    channel ENUM('email','sms','topic','push') NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    allow_fallback TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_communication_provider_key (provider_key),
    KEY idx_communication_provider_route (channel,enabled,priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_delivery_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel ENUM('email','sms','topic','push') NOT NULL,
    provider_key VARCHAR(100) NOT NULL,
    recipient VARCHAR(500) NOT NULL,
    subject VARCHAR(255) NULL,
    status ENUM('sent','failed') NOT NULL,
    provider_message_id VARCHAR(500) NULL,
    error_message TEXT NULL,
    duration_ms INT UNSIGNED NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_communication_attempt_provider (provider_key,created_at),
    KEY idx_communication_attempt_status (channel,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO communication_providers(provider_key,channel,enabled,priority,allow_fallback,notes) VALUES
('microsoft_graph_email','email',1,10,1,'Default email provider for Microsoft 365 installations.'),
('azure_communication_email','email',0,20,1,'Optional Azure Communication Services email provider.'),
('amazon_ses_email','email',0,30,1,'Optional Amazon SES provider; disabled by default.'),
('aws_end_user_messaging_sms','sms',0,10,1,'Preferred AWS application-to-person SMS provider.'),
('azure_communication_sms','sms',0,20,1,'Azure Communication Services SMS provider.'),
('twilio_sms','sms',1,30,1,'Legacy/default third-party SMS provider.'),
('amazon_sns_sms','sms',0,40,1,'Amazon SNS direct-to-phone SMS provider.'),
('amazon_sns_topic','topic',0,10,1,'Amazon SNS publish/subscribe topic provider.')
ON DUPLICATE KEY UPDATE
    channel=VALUES(channel),
    notes=COALESCE(communication_providers.notes,VALUES(notes));

SET @provider_key_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sms_messages' AND COLUMN_NAME='provider_key'
);
SET @sql = IF(
    @provider_key_exists=0,
    'ALTER TABLE sms_messages ADD COLUMN provider_key VARCHAR(100) NULL AFTER status',
    'SELECT ''sms_messages.provider_key already exists'' AS migration_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @provider_index_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sms_messages' AND INDEX_NAME='idx_sms_messages_provider'
);
SET @sql = IF(
    @provider_index_exists=0,
    'ALTER TABLE sms_messages ADD INDEX idx_sms_messages_provider (provider_key,status,created_at)',
    'SELECT ''idx_sms_messages_provider already exists'' AS migration_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
