#!/bin/sh
set -eu
apt update
apt install -y \
  apache2 default-mysql-server default-mysql-client \
  php libapache2-mod-php php-cli php-common php-mysql php-curl php-mbstring \
  php-xml php-zip php-gd php-intl php-bcmath php-apcu \
  composer git curl ca-certificates unzip zip xz-utils cron redis-server \

a2enmod rewrite ssl headers expires deflate remoteip
systemctl enable --now apache2 mariadb cron redis-server
printf '%s\n' 'Debian 13 package installation completed.'
