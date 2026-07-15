# Upgrade to ServiceOS 0.30.0

1. Back up the database, `.env`, and `storage/`.
2. Extract the patch over ServiceOS 0.29.1.
3. Run `composer install --no-dev --optimize-autoloader` to install the AWS SDK and rebuild the classmap.
4. Run `php scripts/migrate.php status`.
5. Run `php scripts/migrate.php migrate`.
6. Add only the provider credentials you intend to use to `.env`.
7. Open **System > Communication Providers** and enable providers in the desired priority order.
8. Run `php tests/phase30_regression.php`.
9. Restart Apache.

Microsoft Graph remains the default email provider. Amazon SES is installed as an optional adapter but remains disabled until explicitly enabled.
