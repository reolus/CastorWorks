-- Rock Bluffs Exterior Services Phase 20 (MySQL 8 compatible)
CREATE TABLE IF NOT EXISTS integration_health_checks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 integration_key VARCHAR(80) NOT NULL,
 status ENUM('ok','warning','failed') NOT NULL,
 http_status INT NULL,
 duration_ms INT NULL,
 detail TEXT NULL,
 tested_by BIGINT UNSIGNED NULL,
 tested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_integration_health_key_time(integration_key,tested_at),
 FOREIGN KEY(tested_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS module_states (
 module_key VARCHAR(80) PRIMARY KEY,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 settings_json JSON NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS schema_migrations (
 migration VARCHAR(190) PRIMARY KEY,
 checksum CHAR(64) NULL,
 applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations(migration,checksum) VALUES('migrate_phase1.sql',NULL),('migrate_phase2.sql',NULL),('migrate_phase3.sql',NULL),('migrate_phase4.sql',NULL),('migrate_phase5.sql',NULL),('migrate_phase6.sql',NULL),('migrate_phase7.sql',NULL),('migrate_phase8.sql',NULL),('migrate_phase9.sql',NULL),('migrate_phase10.sql',NULL),('migrate_phase11.sql',NULL),('migrate_phase12.sql',NULL),('migrate_phase13.sql',NULL),('migrate_phase14.sql',NULL),('migrate_phase15.sql',NULL),('migrate_phase16.sql',NULL),('migrate_phase17.sql',NULL),('migrate_phase18.sql',NULL),('migrate_phase19.sql',NULL),('migrate_phase20.sql',NULL);
