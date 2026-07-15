CREATE TABLE IF NOT EXISTS route_analytics (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 route_date DATE NOT NULL,
 crew_id BIGINT UNSIGNED NULL,
 user_id BIGINT UNSIGNED NULL,
 vehicle_id BIGINT UNSIGNED NULL,
 jobs_total INT UNSIGNED NOT NULL DEFAULT 0,
 jobs_completed INT UNSIGNED NOT NULL DEFAULT 0,
 planned_miles DECIMAL(10,2) NOT NULL DEFAULT 0,
 actual_miles DECIMAL(10,2) NOT NULL DEFAULT 0,
 planned_minutes INT UNSIGNED NOT NULL DEFAULT 0,
 drive_minutes INT UNSIGNED NOT NULL DEFAULT 0,
 work_minutes INT UNSIGNED NOT NULL DEFAULT 0,
 idle_minutes INT UNSIGNED NOT NULL DEFAULT 0,
 revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
 fuel_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
 efficiency_score DECIMAL(6,2) NOT NULL DEFAULT 0,
 route_deviations INT UNSIGNED NOT NULL DEFAULT 0,
 calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_route_analytics_day_assignment(route_date,crew_id,user_id,vehicle_id),
 INDEX idx_route_analytics_date(route_date),
 FOREIGN KEY(crew_id) REFERENCES crews(id) ON DELETE SET NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crew_daily_statistics (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 statistic_date DATE NOT NULL,
 crew_id BIGINT UNSIGNED NOT NULL,
 jobs_total INT UNSIGNED NOT NULL DEFAULT 0,
 jobs_completed INT UNSIGNED NOT NULL DEFAULT 0,
 drive_minutes INT UNSIGNED NOT NULL DEFAULT 0,
 work_minutes INT UNSIGNED NOT NULL DEFAULT 0,
 idle_minutes INT UNSIGNED NOT NULL DEFAULT 0,
 miles DECIMAL(10,2) NOT NULL DEFAULT 0,
 revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
 fuel_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
 efficiency_score DECIMAL(6,2) NOT NULL DEFAULT 0,
 UNIQUE KEY uq_crew_daily_statistics(statistic_date,crew_id),
 FOREIGN KEY(crew_id) REFERENCES crews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS technician_daily_statistics (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 statistic_date DATE NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 jobs_total INT UNSIGNED NOT NULL DEFAULT 0,
 jobs_completed INT UNSIGNED NOT NULL DEFAULT 0,
 drive_minutes INT UNSIGNED NOT NULL DEFAULT 0,
 work_minutes INT UNSIGNED NOT NULL DEFAULT 0,
 idle_minutes INT UNSIGNED NOT NULL DEFAULT 0,
 miles DECIMAL(10,2) NOT NULL DEFAULT 0,
 revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
 efficiency_score DECIMAL(6,2) NOT NULL DEFAULT 0,
 UNIQUE KEY uq_technician_daily_statistics(statistic_date,user_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operational_analytics_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 run_date DATE NOT NULL,
 status ENUM('ok','failed') NOT NULL,
 routes_built INT UNSIGNED NOT NULL DEFAULT 0,
 crews_built INT UNSIGNED NOT NULL DEFAULT 0,
 technicians_built INT UNSIGNED NOT NULL DEFAULT 0,
 detail TEXT NULL,
 completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_operational_analytics_runs(completed_at,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
