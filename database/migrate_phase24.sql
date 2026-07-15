-- ServiceOS 0.24.0 - Crew Operations
CREATE TABLE IF NOT EXISTS job_crew_assignment_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 crew_id BIGINT UNSIGNED NULL,
 assigned_user_id BIGINT UNSIGNED NULL,
 vehicle_id BIGINT UNSIGNED NULL,
 assigned_by BIGINT UNSIGNED NULL,
 assignment_source ENUM('job','dispatch','suggestion','api') NOT NULL DEFAULT 'job',
 notes JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_jcah_job FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 CONSTRAINT fk_jcah_crew FOREIGN KEY(crew_id) REFERENCES crews(id) ON DELETE SET NULL,
 CONSTRAINT fk_jcah_user FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_jcah_vehicle FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
 CONSTRAINT fk_jcah_actor FOREIGN KEY(assigned_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_jcah_job_date(job_id,created_at), INDEX idx_jcah_crew_date(crew_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_required_skills (
 job_id BIGINT UNSIGNED NOT NULL,
 skill_id BIGINT UNSIGNED NOT NULL,
 minimum_proficiency ENUM('learning','qualified','advanced','trainer') NOT NULL DEFAULT 'qualified',
 PRIMARY KEY(job_id,skill_id),
 CONSTRAINT fk_job_required_skills_job FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 CONSTRAINT fk_job_required_skills_skill FOREIGN KEY(skill_id) REFERENCES staff_skills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(migration,checksum) VALUES('migrate_phase24.sql',NULL)
ON DUPLICATE KEY UPDATE applied_at=applied_at;
