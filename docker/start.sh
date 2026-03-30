#!/usr/bin/env bash
set -euo pipefail

PORT_VALUE="${PORT:-80}"
APP_ROOT="/var/www/html"

# Prefer serving from public web root when available.
if [ -f "/var/www/html/public/index.php" ] || [ -f "/var/www/html/public/login.php" ]; then
  APP_ROOT="/var/www/html/public"
fi

# Some deploys use a parent folder that contains trupper_web as subdirectory.
if [ ! -f "${APP_ROOT}/index.php" ] && [ -f "${APP_ROOT}/trupper_web/index.php" ]; then
  APP_ROOT="${APP_ROOT}/trupper_web"
fi

sed -ri "s/^Listen 80$/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf
sed -ri "s#<VirtualHost \*:80>#<VirtualHost *:${PORT_VALUE}>#" /etc/apache2/sites-available/000-default.conf
sed -ri "s#DocumentRoot /var/www/html#DocumentRoot ${APP_ROOT}#" /etc/apache2/sites-available/000-default.conf
sed -ri "s#<Directory /var/www/>#<Directory ${APP_ROOT}/>#" /etc/apache2/apache2.conf

exec apache2-foreground