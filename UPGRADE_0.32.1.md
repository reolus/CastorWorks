# Upgrade to ServiceOS 0.32.1

1. Back up the database and `.env`.
2. Copy this patch over ServiceOS 0.31.x.
3. Run `php scripts/migrate.php status`.
4. Run `php scripts/migrate.php migrate`.
5. Run `php tests/phase32_1_regression.php`.
6. Run `php scripts/schema-check.php`.
7. Run `php scripts/validate-upgrade.php`.
8. Restart Apache.

Optional batch geocoding: `php scripts/geocode-properties.php --limit=25`.
