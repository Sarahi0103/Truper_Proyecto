<?php
/**
 * Configuración de Base de Datos PostgreSQL
 * Truper Platform
 */

// Configuración de conexión (compatible con Render)
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '5432';
$dbName = getenv('DB_NAME') ?: 'truper_platform';
$dbUser = getenv('DB_USER') ?: 'truper_admin';
$dbPass = getenv('DB_PASS') ?: '';

// Permite usar DATABASE_URL/INTERNAL_DATABASE_URL de Render si está disponible
$databaseUrl = getenv('DATABASE_URL') ?: getenv('INTERNAL_DATABASE_URL');
if ($databaseUrl) {
    $parts = parse_url($databaseUrl);
    if ($parts !== false) {
        $dbHost = $parts['host'] ?? $dbHost;
        $dbPort = isset($parts['port']) ? (string) $parts['port'] : $dbPort;
        $dbName = isset($parts['path']) ? ltrim($parts['path'], '/') : $dbName;
        $dbUser = $parts['user'] ?? $dbUser;
        $dbPass = $parts['pass'] ?? $dbPass;
    }
}

define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);

// Inicializacion automatica de esquema (primer arranque)
define('AUTO_DB_INIT', strtolower((string)(getenv('AUTO_DB_INIT') ?: 'true')) !== 'false');
define('AUTO_DB_INIT_SCHEMA_FILE', __DIR__ . '/../database.sql');

// Configuración de seguridad
define('SESSION_TIMEOUT', 1800); // 30 minutos
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

// Configuración general
define('APP_NAME', 'Truper Platform');
define('APP_VERSION', '1.0.0');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/truper_platform');

function truper_db_table_exists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT to_regclass(?)");
    $stmt->execute([$tableName]);
    return $stmt->fetchColumn() !== null;
}

function truper_db_bootstrap_schema(PDO $pdo): void {
    if (!AUTO_DB_INIT) {
        return;
    }

    // Si ya existe users, asumimos esquema inicializado.
    if (truper_db_table_exists($pdo, 'public.users')) {
        return;
    }

    if (!file_exists(AUTO_DB_INIT_SCHEMA_FILE)) {
        error_log('AUTO_DB_INIT activo pero no se encontro archivo de esquema: ' . AUTO_DB_INIT_SCHEMA_FILE);
        return;
    }

    $sql = file_get_contents(AUTO_DB_INIT_SCHEMA_FILE);
    if ($sql === false || trim($sql) === '') {
        error_log('AUTO_DB_INIT activo pero el archivo de esquema esta vacio.');
        return;
    }

    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
        error_log('Fallo AUTO_DB_INIT: ' . $e->getMessage());
        throw $e;
    }
}

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

    // Primer arranque: crea tablas e inserts base automaticamente.
    truper_db_bootstrap_schema($pdo);
} catch (PDOException $e) {
    error_log("Error de conexión a DB: " . $e->getMessage());
    die("Error al conectar a la base de datos. Por favor intente más tarde.");
}

return $pdo;
?>
