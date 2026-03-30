<?php
/**
 * Configuración de Base de Datos PostgreSQL
 * Truper Platform
 */

// Configuración de conexión
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'truper_platform');
define('DB_USER', 'truper_admin');
define('DB_PASS', 'TruperSecure2024!');

// Configuración de seguridad
define('SESSION_TIMEOUT', 1800); // 30 minutos
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

// Configuración general
define('APP_NAME', 'Truper Platform');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/truper_platform');

// Conectar a PostgreSQL
try {
    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ]
    );
} catch (PDOException $e) {
    error_log("Error de conexión a DB: " . $e->getMessage());
    die("Error al conectar a la base de datos. Por favor intente más tarde.");
}

return $pdo;
?>
