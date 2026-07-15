# Upgrade to ServiceOS 0.32.2

1. Back up the database and `.env`/`storage`.
2. Copy the patch into the ServiceOS root.
3. Run `php scripts/migrate.php status` and `php scripts/migrate.php migrate`.
4. Run `php tests/phase32_2_regression.php` and `php scripts/validate-upgrade.php`.
5. Restart Apache and force-refresh the browser.
