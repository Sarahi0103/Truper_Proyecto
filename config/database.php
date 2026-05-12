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
// Fallback: some environments (docker) expose POSTGRES_PASSWORD
if (empty($dbPass)) {
    $dbPass = getenv('POSTGRES_PASSWORD') ?: $dbPass;
}

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
define('SESSION_TIMEOUT', 2400); // 40 minutos
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
// Try to connect with retries to avoid transient startup order issues
$pdo = null;
$connectError = null;
$maxAttempts = 5;
for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
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
        $connectError = null;
        break;
    } catch (PDOException $e) {
        $connectError = $e->getMessage();
        error_log("Intento {$attempt} - Error de conexión a DB: " . $connectError);
        // small backoff
        if ($attempt < $maxAttempts) {
            sleep(1);
            continue;
        }
    }
}

if ($pdo === null) {
    // Do not die with a bare message; render a friendly HTML fragment so browsers show a full page
    error_log('Error fatal de conexión a la base de datos tras reintentos: ' . ($connectError ?? 'unknown'));
    http_response_code(503);
    echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Truper - Error</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#fafafa;color:#333;margin:0;padding:40px} .card{max-width:760px;margin:40px auto;padding:28px;background:#fff;border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,0.06)} h1{margin:0 0 8px;font-size:20px} p{margin:8px 0 0}</style></head><body><div class=\"card\"><h1>Error al conectar a la base de datos</h1><p>Estamos teniendo problemas para acceder a la base de datos. Por favor inténtelo de nuevo en unos minutos.</p><p>Si necesita asistencia inmediata, revise los logs del servidor.</p></div></body></html>";
    exit(0);
}

return $pdo;
?>
