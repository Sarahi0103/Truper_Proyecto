<?php
// Endpoint seguro para probar conexión a la base de datos.
// Protegido por la variable de entorno DEBUG_TOKEN.
header('Content-Type: application/json; charset=utf-8');

$provided = $_GET['token'] ?? ($_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '');
$secret = getenv('DEBUG_TOKEN') ?: '';

if (empty($secret) || !hash_equals((string)$secret, (string)$provided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden. Missing or invalid token.']);
    exit;
}

// Determine DB connection info (support DATABASE_URL or individual vars)
$databaseUrl = getenv('DATABASE_URL') ?: getenv('INTERNAL_DATABASE_URL');
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '5432';
$dbName = getenv('DB_NAME') ?: '';
$dbUser = getenv('DB_USER') ?: '';
$dbPass = getenv('DB_PASS') ?: '';

if ($databaseUrl) {
    $parts = parse_url($databaseUrl);
    if ($parts !== false) {
        $dbHost = $parts['host'] ?? $dbHost;
        $dbPort = isset($parts['port']) ? (string)$parts['port'] : $dbPort;
        $dbName = isset($parts['path']) ? ltrim($parts['path'], '/') : $dbName;
        $dbUser = $parts['user'] ?? $dbUser;
        $dbPass = $parts['pass'] ?? $dbPass;
    }
}

$result = ['ok' => false, 'host' => $dbHost, 'port' => $dbPort, 'db' => $dbName];

try {
    $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5];
    $pdo = new PDO($dsn, $dbUser, $dbPass, $opts);
    // Simple query
    $stmt = $pdo->query('SELECT 1 AS ok');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $result['ok'] = true;
    $result['msg'] = 'Connection successful';
    $result['query'] = $row;
} catch (Exception $e) {
    $result['ok'] = false;
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;

?>
