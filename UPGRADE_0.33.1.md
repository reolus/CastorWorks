# Upgrade to CastorWorks 0.33.1

1. Back up the database, `.env`, and `storage/`.
2. Extract this patch into the application root.
3. Run `php scripts/apply-release-0.33.1.php`.
4. Run `php scripts/migrate.php status` and `php scripts/migrate.php migrate`.
5. Run `php tests/phase33_1_regression.php`.
6. Restart Apache.
7. Open **System → AI Assistant**. AI remains disabled until explicitly configured.
