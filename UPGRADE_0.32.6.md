# Upgrade to CastorWorks 0.32.6

1. Back up the database and `.env`/`storage`.
2. Copy patch files into the application root.
3. Run `php scripts/migrate.php migrate`.
4. Run `php scripts/build-route-analytics.php`.
5. Run `php tests/phase32_6_regression.php`.
6. Restart Apache.
7. Add the hourly analytics cron entry.
