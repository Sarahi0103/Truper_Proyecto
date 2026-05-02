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

# Initialize image directories
echo "Initializing image directories..."
mkdir -p /var/www/html/public/images/products/gallery
chmod 777 /var/www/html/public/images/products/gallery 2>/dev/null || true
chmod 777 /var/www/html/public/images/products 2>/dev/null || true
chmod 777 /var/www/html/public/images 2>/dev/null || true

# Execute init script if available
if [ -f "/var/www/html/init_dirs.sh" ]; then
  bash /var/www/html/init_dirs.sh || true
fi

echo "✓ Image directories initialized"

exec apache2-foreground
