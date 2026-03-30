<?php
/**
 * Truper - Instalación Rápida
 * Ejecutar este archivo para configurar la aplicación
 */

// Verificar PHP version
if (version_compare(PHP_VERSION, '7.4.0') < 0) {
    die('PHP 7.4 o superior es requerido');
}

// Crear directorio de logs
if (!is_dir('logs')) {
    mkdir('logs', 0755);
}

// Crear archivo de configuración
$config_file = 'backend/config/database.php';
if (file_exists($config_file)) {
    echo "✓ Configuración existente encontrada\n";
} else {
    echo "⚠ Ejecutar: php -f backend/config/database.php\n";
}

// Verificar base de datos
require_once 'backend/config/database.php';

$db = new Database();
$conn = $db->connect();

if ($conn) {
    echo "✓ Conexión a base de datos exitosa\n";
    $conn->close();
} else {
    die("✗ Error de conexión a base de datos\n");
}

// Crear archivo .env (futuro)
$env_content = <<<ENV
APP_ENV=production
APP_DEBUG=false
DB_HOST=localhost
DB_USER=trupper_user
DB_PASS=trupper_password
DB_NAME=trupper_db
ENV;

if (!file_exists('.env')) {
    file_put_contents('.env', $env_content);
    echo "✓ Archivo .env creado\n";
}

echo "\n✓ La aplicación está lista para usar\n";
echo "Acceder a: http://localhost:8000\n";
?>



