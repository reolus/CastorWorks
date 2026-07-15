-- ServiceOS 0.26.1 - Document schema alignment and webhook timestamp repair
-- MySQL-compatible and safe to run against either legacy or aligned schemas.

-- documents.entity_type -> documents.reference_type
SET @old_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'entity_type'
);
SET @new_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'reference_type'
);
SET @sql = IF(
    @old_exists = 1 AND @new_exists = 0,
    'ALTER TABLE documents CHANGE entity_type reference_type VARCHAR(100) NOT NULL',
    'SELECT ''documents.reference_type already aligned'' AS migration_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- documents.entity_id -> documents.reference_id
SET @old_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'entity_id'
);
SET @new_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'reference_id'
);
SET @sql = IF(
    @old_exists = 1 AND @new_exists = 0,
    'ALTER TABLE documents CHANGE entity_id reference_id BIGINT UNSIGNED NOT NULL',
    'SELECT ''documents.reference_id already aligned'' AS migration_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- documents.filename -> documents.original_name
SET @old_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'filename'
);
SET @new_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'original_name'
);
SET @sql = IF(
    @old_exists = 1 AND @new_exists = 0,
    'ALTER TABLE documents CHANGE filename original_name VARCHAR(255) NOT NULL',
    'SELECT ''documents.original_name already aligned'' AS migration_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- webhook_events.created_at -> webhook_events.received_at
SET @old_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'webhook_events' AND COLUMN_NAME = 'created_at'
);
SET @new_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'webhook_events' AND COLUMN_NAME = 'received_at'
);
SET @sql = IF(
    @old_exists = 1 AND @new_exists = 0,
    'ALTER TABLE webhook_events CHANGE created_at received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    IF(
        @old_exists = 0 AND @new_exists = 0,
        'ALTER TABLE webhook_events ADD COLUMN received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'SELECT ''webhook_events.received_at already aligned'' AS migration_status'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
