<?php
/**
 * Truper - InstalaciÃ³n RÃ¡pida
 * Ejecutar este archivo para configurar la aplicaciÃ³n
 */

// Verificar PHP version
if (version_compare(PHP_VERSION, '7.4.0') < 0) {
    die('PHP 7.4 o superior es requerido');
}

// Crear directorio de logs
if (!is_dir('logs')) {
    mkdir('logs', 0755);
}

// Crear archivo de configuraciÃ³n
$config_file = 'backend/config/database.php';
if (file_exists($config_file)) {
    echo "âœ“ ConfiguraciÃ³n existente encontrada\n";
} else {
    echo "âš  Ejecutar: php -f backend/config/database.php\n";
}

// Verificar base de datos
require_once 'backend/config/database.php';

$db = new Database();
$conn = $db->connect();

if ($conn) {
    echo "âœ“ ConexiÃ³n a base de datos exitosa\n";
    $conn->close();
} else {
    die("âœ— Error de conexiÃ³n a base de datos\n");
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
    echo "âœ“ Archivo .env creado\n";
}

echo "\nâœ“ La aplicaciÃ³n estÃ¡ lista para usar\n";
echo "Acceder a: http://localhost:8000\n";
?>



