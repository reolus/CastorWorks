-- ServiceOS 0.32.1 - Geospatial Foundation (MySQL 8 compatible)

CREATE TABLE IF NOT EXISTS map_provider_settings (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 provider VARCHAR(30) NOT NULL DEFAULT 'nominatim',
 fallback_provider VARCHAR(30) NOT NULL DEFAULT 'none',
 cache_days INT UNSIGNED NOT NULL DEFAULT 180,
 requests_per_second TINYINT UNSIGNED NOT NULL DEFAULT 1,
 user_agent VARCHAR(190) NOT NULL DEFAULT 'ServiceOS/0.32.1',
 contact_email VARCHAR(190) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO map_provider_settings(id,provider,fallback_provider,cache_days,requests_per_second,user_agent,active)
VALUES(1,'nominatim','none',180,1,'ServiceOS/0.32.1',1)
ON DUPLICATE KEY UPDATE id=id;

CREATE TABLE IF NOT EXISTS geocoding_cache (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 address_hash CHAR(64) NOT NULL,
 address_text VARCHAR(500) NOT NULL,
 provider VARCHAR(30) NOT NULL,
 latitude DECIMAL(10,7) NOT NULL,
 longitude DECIMAL(10,7) NOT NULL,
 formatted_address VARCHAR(500) NULL,
 accuracy VARCHAR(80) NULL,
 raw_response JSON NULL,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_geocoding_cache_hash(address_hash),
 INDEX idx_geocoding_cache_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='properties' AND COLUMN_NAME='latitude')=0,'ALTER TABLE properties ADD COLUMN latitude DECIMAL(10,7) NULL, ADD COLUMN longitude DECIMAL(10,7) NULL','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='properties' AND COLUMN_NAME='geocode_source')=0,'ALTER TABLE properties ADD COLUMN geocode_source VARCHAR(30) NULL AFTER longitude, ADD COLUMN geocode_status VARCHAR(30) NOT NULL DEFAULT ''pending'' AFTER geocode_source, ADD COLUMN geocode_accuracy VARCHAR(80) NULL AFTER geocode_status, ADD COLUMN geocode_formatted_address VARCHAR(500) NULL AFTER geocode_accuracy, ADD COLUMN geocode_error TEXT NULL AFTER geocode_formatted_address, ADD COLUMN geocoded_at DATETIME NULL AFTER geocode_error, ADD COLUMN geocode_verified_at DATETIME NULL AFTER geocoded_at, ADD INDEX idx_properties_geocode_status(geocode_status)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='latitude')=0,'ALTER TABLE jobs ADD COLUMN latitude DECIMAL(10,7) NULL, ADD COLUMN longitude DECIMAL(10,7) NULL','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='travel_time_minutes')=0,'ALTER TABLE jobs ADD COLUMN travel_time_minutes INT UNSIGNED NULL, ADD COLUMN travel_distance_miles DECIMAL(10,2) NULL','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vehicles' AND COLUMN_NAME='current_latitude')=0,'ALTER TABLE vehicles ADD COLUMN current_latitude DECIMAL(10,7) NULL, ADD COLUMN current_longitude DECIMAL(10,7) NULL, ADD COLUMN last_location_update DATETIME NULL','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='crews' AND COLUMN_NAME='current_latitude')=0,'ALTER TABLE crews ADD COLUMN current_latitude DECIMAL(10,7) NULL, ADD COLUMN current_longitude DECIMAL(10,7) NULL, ADD COLUMN last_location_update DATETIME NULL','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS gps_location_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 crew_id BIGINT UNSIGNED NULL,
 vehicle_id BIGINT UNSIGNED NULL,
 job_id BIGINT UNSIGNED NULL,
 latitude DECIMAL(10,7) NOT NULL,
 longitude DECIMAL(10,7) NOT NULL,
 accuracy_meters DECIMAL(10,2) NULL,
 heading_degrees DECIMAL(6,2) NULL,
 speed_mph DECIMAL(8,2) NULL,
 source VARCHAR(30) NOT NULL DEFAULT 'browser',
 captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_gps_user_time(user_id,captured_at),
 INDEX idx_gps_crew_time(crew_id,captured_at),
 INDEX idx_gps_vehicle_time(vehicle_id,captured_at),
 INDEX idx_gps_job_time(job_id,captured_at),
 CONSTRAINT fk_gps_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_gps_crew FOREIGN KEY(crew_id) REFERENCES crews(id) ON DELETE SET NULL,
 CONSTRAINT fk_gps_vehicle FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
 CONSTRAINT fk_gps_job FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
