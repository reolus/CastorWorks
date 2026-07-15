# Upgrade to CastorWorks 0.33.5

1. Back up the database, `.env`, and `storage/`.
2. Copy the patch contents over the CastorWorks application root.
3. Run `php scripts/migrate.php status`.
4. Run `php scripts/migrate.php migrate`.
5. Run `php tests/phase33_5_regression.php`.
6. Add the AI retention worker to cron.
7. Restart Apache.

Recommended cron entry:

```cron
35 2 * * * www-data flock -n /run/lock/castorworks-ai-prune.lock php /var/www/rock-bluffs-exterior/scripts/prune-ai-records.php >> /var/log/castorworks/prune-ai-records.log 2>&1
```
