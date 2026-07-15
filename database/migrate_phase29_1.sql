-- ServiceOS 0.29.1 - schema alignment maintenance
-- Adds the canonical optional relationship between invoices and jobs.
-- MySQL-compatible and safe to run against installations where the column,
-- index, or foreign key already exists.

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
      AND COLUMN_NAME = 'job_id'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE invoices ADD COLUMN job_id BIGINT UNSIGNED NULL AFTER customer_id',
    'SELECT ''invoices.job_id already exists'' AS migration_status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
      AND INDEX_NAME = 'idx_invoices_job_id'
);

SET @sql = IF(
    @index_exists = 0,
    'ALTER TABLE invoices ADD INDEX idx_invoices_job_id (job_id)',
    'SELECT ''idx_invoices_job_id already exists'' AS migration_status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @foreign_key_exists = (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
      AND REFERENCED_TABLE_NAME = 'jobs'
      AND CONSTRAINT_NAME = 'fk_invoices_job'
);

SET @sql = IF(
    @foreign_key_exists = 0,
    'ALTER TABLE invoices ADD CONSTRAINT fk_invoices_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL',
    'SELECT ''fk_invoices_job already exists'' AS migration_status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
