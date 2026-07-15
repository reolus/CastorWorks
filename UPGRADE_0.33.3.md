# Upgrade to CastorWorks 0.33.3

1. Back up the database and persistent storage.
2. Copy this patch over the application root.
3. Run `php scripts/migrate.php status`.
4. Run `php scripts/migrate.php migrate`.
5. Run `php tests/phase33_3_regression.php`.
6. Restart Apache.

No patch script is used. All files are direct replacements or additions.
