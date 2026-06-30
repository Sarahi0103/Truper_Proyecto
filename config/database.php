<?php
/**
 * Configuración de Base de Datos PostgreSQL
 * Truper Platform
 */

if (!function_exists('truper_load_env_file')) {
    function truper_load_env_file(string $path): void {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            if ($value !== '' && (
                (substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
            )) {
                $value = substr($value, 1, -1);
            }

            if (($commentPos = strpos($value, ' #')) !== false) {
                $value = trim(substr($value, 0, $commentPos));
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

truper_load_env_file(__DIR__ . '/../.env');
require_once __DIR__ . '/../src/utils/AppLogger.php';

// Configuración de conexión (compatible con Render)
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '5432';
$dbName = getenv('DB_NAME') ?: 'truper_platform';
$dbUser = getenv('DB_USER') ?: 'truper_admin';
$dbPass = getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: '';
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

if (!defined('DB_HOST')) define('DB_HOST', $dbHost);
if (!defined('DB_PORT')) define('DB_PORT', $dbPort);
if (!defined('DB_NAME')) define('DB_NAME', $dbName);
if (!defined('DB_USER')) define('DB_USER', $dbUser);
if (!defined('DB_PASS')) define('DB_PASS', $dbPass);

// Inicializacion automatica de esquema (primer arranque)
if (!defined('AUTO_DB_INIT')) define('AUTO_DB_INIT', strtolower((string)(getenv('AUTO_DB_INIT') ?: 'true')) !== 'false');
if (!defined('AUTO_DB_INIT_SCHEMA_FILE')) define('AUTO_DB_INIT_SCHEMA_FILE', __DIR__ . '/../database.sql');

// Configuración de seguridad
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 0); // Sin límite: sesión válida hasta que el usuario cierre sesión
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOCKOUT_TIME')) define('LOCKOUT_TIME', 900); // 15 minutos

// Configuración general
if (!defined('APP_NAME')) define('APP_NAME', 'Truper Platform');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('APP_URL')) define('APP_URL', getenv('APP_URL') ?: 'http://localhost/truper_platform');

if (!function_exists('truper_db_table_exists')) {
    function truper_db_table_exists(PDO $pdo, string $tableName): bool {
        $stmt = $pdo->prepare("SELECT to_regclass(?)");
        $stmt->execute([$tableName]);
        return $stmt->fetchColumn() !== null;
    }
}

if (!function_exists('truper_db_bootstrap_schema')) {
    function truper_db_bootstrap_schema(PDO $pdo): void {
        if (!AUTO_DB_INIT) {
            return;
        }

        // Si ya existe users, asumimos esquema inicializado.
        if (truper_db_table_exists($pdo, 'public.users')) {
            return;
        }

        if (!file_exists(AUTO_DB_INIT_SCHEMA_FILE)) {
            AppLogger::error('AUTO_DB_INIT activo pero no se encontro archivo de esquema: ' . AUTO_DB_INIT_SCHEMA_FILE);
            return;
        }

        $sql = file_get_contents(AUTO_DB_INIT_SCHEMA_FILE);
        if ($sql === false || trim($sql) === '') {
            AppLogger::error('AUTO_DB_INIT activo pero el archivo de esquema esta vacio.');
            return;
        }

        try {
            $pdo->exec($sql);
            AppLogger::info('AUTO_DB_INIT completado exitosamente.');
        } catch (Exception $e) {
            AppLogger::error('Fallo AUTO_DB_INIT: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
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
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_PERSISTENT => strtolower((string)(getenv('DB_PERSISTENT') ?: 'false')) === 'true'
            ]
        );

        // Primer arranque: crea tablas e inserts base automaticamente.
        truper_db_bootstrap_schema($pdo);
        // Establecer zona horaria local de México para reportes y fechas
        $pdo->exec("SET TIME ZONE 'America/Mexico_City'");
        $connectError = null;
        AppLogger::info('Conexión a base de datos establecida exitosamente.');
        break;
    } catch (PDOException $e) {
        $connectError = $e->getMessage();
        AppLogger::warning("Intento {$attempt} - Error de conexión a DB: " . $connectError, ['attempt' => $attempt]);
        // small backoff
        if ($attempt < $maxAttempts) {
            sleep(1);
            continue;
        }
    }
}

if ($pdo === null) {
    // Do not die with a bare message; render a friendly HTML fragment so browsers show a full page
    AppLogger::error('Error fatal de conexión a la base de datos tras reintentos: ' . ($connectError ?? 'unknown'), ['error' => $connectError]);
    http_response_code(503);
    echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Truper - Error</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#fafafa;color:#333;margin:0;padding:40px} .card{max-width:760px;margin:40px auto;padding:28px;background:#fff;border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,0.06)} h1{margin:0 0 8px;font-size:20px} p{margin:8px 0 0}</style></head><body><div class=\"card\"><h1>Error al conectar a la base de datos</h1><p>Estamos teniendo problemas para acceder a la base de datos. Por favor inténtelo de nuevo en unos minutos.</p><p>Si necesita asistencia inmediata, revise los logs del servidor.</p></div></body></html>";
    exit(0);
}

// Migraciones automáticas del sistema (Descuento y Precio Base)
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS net_price DECIMAL(10,2)");
} catch (Exception $ignored) {}
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS discount_percentage DECIMAL(5,2) DEFAULT 0");
} catch (Exception $ignored) {}
try {
    $pdo->exec("ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS net_price DECIMAL(12,2)");
} catch (Exception $ignored) {}
try {
    $pdo->exec("ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS discount_percentage DECIMAL(5,2) DEFAULT 0");
} catch (Exception $ignored) {}

return $pdo;
?>
