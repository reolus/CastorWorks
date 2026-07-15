-- ServiceOS 0.32.2 - Dispatch Map and health monitoring
CREATE TABLE IF NOT EXISTS user_map_preferences (
 user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 center_latitude DECIMAL(10,7) NULL,
 center_longitude DECIMAL(10,7) NULL,
 zoom_level TINYINT UNSIGNED NULL,
 layer_preferences JSON NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_user_map_preferences_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql=IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND INDEX_NAME='idx_jobs_route_map')=0,'ALTER TABLE jobs ADD INDEX idx_jobs_route_map(route_date,crew_id,route_order)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='properties' AND INDEX_NAME='idx_properties_coordinates')=0,'ALTER TABLE properties ADD INDEX idx_properties_coordinates(latitude,longitude)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
