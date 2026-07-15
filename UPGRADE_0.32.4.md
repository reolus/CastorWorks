# Upgrade to CastorWorks 0.32.4

1. Back up the database, `.env`, and `storage/`.
2. Copy the patch into the application root.
3. Run `php scripts/migrate.php status` and `php scripts/migrate.php migrate`.
4. Run `php tests/phase32_4_regression.php`, `php scripts/schema-check.php`, and `php scripts/validate-upgrade.php`.
5. Restart Apache.
6. Open **System → GPS Tracking**, review the policy, then enable tracking when ready.
7. Add a daily cron entry for `scripts/prune-gps-history.php`.
