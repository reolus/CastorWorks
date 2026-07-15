#!/bin/sh
set -eu
APP_DIR=${APP_DIR:-/var/www/rock-bluffs-exterior}
cd "$APP_DIR"
php -v
composer install --no-dev --prefer-dist --optimize-autoloader
php tests/smoke.php
chown -R www-data:www-data storage
chmod -R 770 storage
php scripts/system-health.php
apache2ctl configtest
systemctl reload apache2
echo "Deployment completed."
