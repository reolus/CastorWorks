# Upgrade to ServiceOS 0.31.0

1. Back up the database and `.env`/`storage`.
2. Copy this patch over ServiceOS 0.30.0.
3. Run `composer install --no-dev --optimize-autoloader`.
4. Run `php scripts/migrate.php status` and `php scripts/migrate.php migrate`.
5. Add `DEFAULT_COUNTRY_CODE=1` and a random `COMMUNICATION_WEBHOOK_SECRET` to `.env`.
6. Configure provider delivery callbacks using the URLs shown in `.env.example`.
7. Run `php tests/phase31_regression.php` and restart Apache.
