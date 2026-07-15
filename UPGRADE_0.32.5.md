# Upgrade to CastorWorks 0.32.5

1. Back up the database and `.env`/`storage`.
2. Copy the patch into the CastorWorks application root.
3. Run `php scripts/migrate.php status` and `php scripts/migrate.php migrate`.
4. Run `php tests/phase32_5_regression.php`, `php scripts/schema-check.php`, and `php scripts/validate-upgrade.php`.
5. Add the ETA worker to cron: `*/5 * * * * www-data php /var/www/rock-bluffs-exterior/scripts/update-route-etas.php`.
6. Restart Apache and configure **System -> ETA & Route Progress**.
