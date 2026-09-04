#!/usr/bin/env bash
set -euo pipefail

PORT_VALUE="${PORT:-80}"
APP_ROOT="/var/www/html/public"

# Si no existe la carpeta public, fallback a la raíz
if [ ! -d "${APP_ROOT}" ]; then
  APP_ROOT="/var/www/html"
fi

echo "==> Configurando Apache en puerto ${PORT_VALUE} con DocumentRoot en ${APP_ROOT}..."

# Configurar puertos de Apache
sed -ri "s/^Listen .*/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf

# Configurar VirtualHost de Apache
cat <<EOF > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:${PORT_VALUE}>
    ServerAdmin webmaster@localhost
    DocumentRoot ${APP_ROOT}

    <Directory ${APP_ROOT}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Configurar almacenamiento persistente de imágenes
PERSIST_DIR="/var/www/html/images"
IS_RENDER=false
if [ "${RENDER:-}" = "true" ] || [ "${APP_ENV:-}" = "production" ]; then
  IS_RENDER=true
fi

echo "==> Inicializando sistema de almacenamiento de imágenes..."

if [ "$IS_RENDER" = "true" ] && [ -d "${PERSIST_DIR}" ]; then
  echo "==> Disco persistente detectado en: ${PERSIST_DIR}"
  mkdir -p "${PERSIST_DIR}/products/gallery"
  mkdir -p "${PERSIST_DIR}/products/by_code"

  # Si public/images es un directorio regular (no symlink), migramos los assets iniciales al disco persistente
  if [ -d "/var/www/html/public/images" ] && [ ! -L "/var/www/html/public/images" ]; then
    echo "==> Copiando imágenes base del repositorio al disco persistente..."
    cp -rn /var/www/html/public/images/* "${PERSIST_DIR}/" 2>/dev/null || true
    rm -rf /var/www/html/public/images
    ln -s "${PERSIST_DIR}" /var/www/html/public/images
  elif [ ! -e "/var/www/html/public/images" ]; then
    ln -s "${PERSIST_DIR}" /var/www/html/public/images
  fi

  chown -R www-data:www-data "${PERSIST_DIR}" 2>/dev/null || true
  chmod -R 775 "${PERSIST_DIR}" 2>/dev/null || true
else
  echo "==> Modo local o disco efímero: inicializando directorios directos..."
  mkdir -p /var/www/html/public/images/products/gallery
  mkdir -p /var/www/html/public/images/products/by_code
  chown -R www-data:www-data /var/www/html/public/images 2>/dev/null || true
  chmod -R 775 /var/www/html/public/images 2>/dev/null || true
fi

# Asegurar permisos en directorio de cache y logs
mkdir -p /var/www/html/cache /var/www/html/logs
chown -R www-data:www-data /var/www/html/cache /var/www/html/logs 2>/dev/null || true
chmod -R 775 /var/www/html/cache /var/www/html/logs 2>/dev/null || true

# Ejecutar script init_dirs.sh si existe
if [ -f "/var/www/html/init_dirs.sh" ]; then
  bash /var/www/html/init_dirs.sh || true
fi

# Inicialización temprana de Base de Datos si está configurada
if [ "${AUTO_DB_INIT:-true}" = "true" ] && [ -n "${DB_HOST:-}" ]; then
  echo "==> Verificando conexión a Base de Datos y ejecutando migraciones iniciales..."
  php -r "
    try {
        require_once '/var/www/html/config/config.php';
        echo '✓ Base de datos conectada y esquema verificado.' . PHP_EOL;
    } catch (Throwable \$e) {
        echo 'ℹ Inicialización de DB diferida al primer request HTTP: ' . \$e->getMessage() . PHP_EOL;
    }
  " || true
fi

echo "==> Servidor listo. Iniciando Apache..."
exec apache2-foreground
