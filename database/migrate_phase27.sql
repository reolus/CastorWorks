-- ServiceOS 0.27.0 - Business Intelligence
SET @exists=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoices' AND INDEX_NAME='idx_invoices_issue_status');
SET @sql=IF(@exists=0,'ALTER TABLE invoices ADD INDEX idx_invoices_issue_status(issue_date,status)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' AND INDEX_NAME='idx_payments_received');
SET @sql=IF(@exists=0,'ALTER TABLE payments ADD INDEX idx_payments_received(received_at)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='estimates' AND INDEX_NAME='idx_estimates_created_status');
SET @sql=IF(@exists=0,'ALTER TABLE estimates ADD INDEX idx_estimates_created_status(created_at,status)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND INDEX_NAME='idx_jobs_schedule_status');
SET @sql=IF(@exists=0,'ALTER TABLE jobs ADD INDEX idx_jobs_schedule_status(scheduled_start,status)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_service_requests' AND INDEX_NAME='idx_service_requests_created_status');
SET @sql=IF(@exists=0,'ALTER TABLE customer_service_requests ADD INDEX idx_service_requests_created_status(created_at,status)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_referrals' AND INDEX_NAME='idx_referrals_created_status');
SET @sql=IF(@exists=0,'ALTER TABLE customer_referrals ADD INDEX idx_referrals_created_status(created_at,status)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @exists=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_satisfaction_surveys' AND INDEX_NAME='idx_surveys_created');
SET @sql=IF(@exists=0,'ALTER TABLE customer_satisfaction_surveys ADD INDEX idx_surveys_created(created_at)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
INSERT INTO schema_migrations(migration,checksum) VALUES('migrate_phase27.sql',NULL) ON DUPLICATE KEY UPDATE applied_at=applied_at;
