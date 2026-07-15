# Upgrade to CastorWorks 0.33.2

1. Back up the database, `.env`, and `storage/`.
2. Copy this patch into the CastorWorks application root.
3. Run `php scripts/apply-release-0.33.2.php`.
4. Run `php scripts/migrate.php status` and `php scripts/migrate.php migrate`.
5. Run `php tests/phase33_2_regression.php`.
6. Restart Apache.
7. Review AI governance settings under System -> AI Assistant.
