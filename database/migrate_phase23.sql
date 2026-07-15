-- ServiceOS Release 0.23.0 - Workforce Management (MySQL 8 compatible)

CREATE TABLE IF NOT EXISTS crews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 crew_leader_id BIGINT UNSIGNED NULL,
 default_vehicle_id BIGINT UNSIGNED NULL,
 service_territory_id BIGINT UNSIGNED NULL,
 color_label VARCHAR(20) NULL,
 notes TEXT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_crews_name(name),
 INDEX idx_crews_active(active),
 CONSTRAINT fk_crews_leader FOREIGN KEY (crew_leader_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_crews_vehicle FOREIGN KEY (default_vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crew_members (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 crew_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 is_primary TINYINT(1) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1,
 started_at DATE NULL,
 ended_at DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_crew_member(crew_id,user_id),
 INDEX idx_crew_members_user(user_id,active),
 CONSTRAINT fk_crew_members_crew FOREIGN KEY (crew_id) REFERENCES crews(id) ON DELETE CASCADE,
 CONSTRAINT fk_crew_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_skills (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 category VARCHAR(100) NOT NULL DEFAULT 'General',
 description TEXT NULL,
 requires_certification TINYINT(1) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_staff_skills_name(name),
 INDEX idx_staff_skills_category(category,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_skills (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 skill_id BIGINT UNSIGNED NOT NULL,
 proficiency_level ENUM('learning','qualified','advanced','trainer') NOT NULL DEFAULT 'qualified',
 verified_by BIGINT UNSIGNED NULL,
 verified_at DATETIME NULL,
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_user_skill(user_id,skill_id),
 INDEX idx_user_skills_skill(skill_id,proficiency_level),
 CONSTRAINT fk_user_skills_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_user_skills_skill FOREIGN KEY (skill_id) REFERENCES staff_skills(id) ON DELETE CASCADE,
 CONSTRAINT fk_user_skills_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @column_exists = (
 SELECT COUNT(*) FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'crew_id'
);
SET @sql = IF(
 @column_exists = 0,
 'ALTER TABLE jobs ADD COLUMN crew_id BIGINT UNSIGNED NULL AFTER assigned_user_id, ADD INDEX idx_jobs_crew(crew_id), ADD CONSTRAINT fk_jobs_crew FOREIGN KEY (crew_id) REFERENCES crews(id) ON DELETE SET NULL',
 'SELECT 1'
);
PREPARE phase23_stmt FROM @sql;
EXECUTE phase23_stmt;
DEALLOCATE PREPARE phase23_stmt;

INSERT IGNORE INTO staff_skills(name,category,description,requires_certification) VALUES
 ('Traditional Window Cleaning','Window Cleaning','Squeegee, scrubber, detailing, and safe interior/exterior procedures.',0),
 ('Water-Fed Pole Operation','Window Cleaning','Purified-water system setup, brush technique, and equipment care.',0),
 ('Screen Cleaning','Window Cleaning','Safe removal, cleaning, inspection, and reinstallation of screens.',0),
 ('Ladder Safety','Safety','Inspection, placement, stabilization, and safe ladder use.',1),
 ('Pressure Washing','Exterior Cleaning','Safe pressure selection, surface protection, and cleaning technique.',0),
 ('Soft Washing','Exterior Cleaning','Chemical application, dwell time, runoff control, and plant protection.',1),
 ('Crew Leadership','Leadership','Job coordination, customer communication, and quality verification.',0);

INSERT INTO schema_migrations(migration,checksum) VALUES('migrate_phase23.sql',NULL)
ON DUPLICATE KEY UPDATE applied_at=applied_at;
