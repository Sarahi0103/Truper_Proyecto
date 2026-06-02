<?php
/**
 * Configuración General de la Aplicación
 */

// ===== INICIALIZACIÓN DE DIRECTORIOS =====
require_once __DIR__ . '/init_dirs.php';

// ===== SEGURIDAD PRIMERA =====
require_once __DIR__ . '/security.php';

$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => (defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 2400),
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Headers de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
if ($is_https) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// ===== OPTIMIZACIONES DE PERFORMANCE =====
// Compresión gzip automática
if (!ob_get_level() || ob_get_status()['name'] === 'default output handler') {
    ob_start('ob_gzhandler');
}

// Headers de caché para navegadores (cliente-side caching)
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$request_path = parse_url($request_uri, PHP_URL_PATH) ?: '';
$is_api_request = strpos($request_path, '/api/') !== false;
$has_authenticated_session = isset($_SESSION['user_id']) && isset($_SESSION['role']);
$is_auth_context = preg_match('#/(admin_login|login|register)\.php$#', $request_path) === 1
    || strpos($request_path, '/api/auth.php') !== false;
$is_dynamic_catalog_page = in_array($request_path, ['/', '/index.php', '/products.php', '/product_detail.php', '/marketplace_ce.php'], true);

if ($has_authenticated_session || $is_auth_context || $is_dynamic_catalog_page) {
    // Evita reutilizar páginas con CSRF viejo y corrige "Sesión inválida" al iniciar sesión.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
} else {
    $cache_control = $is_api_request ? 'private, max-age=300' : 'private, max-age=3600';
    header("Cache-Control: {$cache_control}");
    header("Expires: " . gmdate("D, d M Y H:i:s", time() + 3600) . " GMT");
    header('Pragma: cache');
}

// Caché de servidor persistente (file-based) con primer nivel en memoria
define('CACHE_ENABLED', true);
define('CACHE_TTL', 300); // 5 minutos
$_CACHE = []; // Memoria (primer nivel)

function cache_get($key) {
    global $_CACHE;
    if (!CACHE_ENABLED) return null;
    
    // Nivel 1: Memoria (rápido para la misma petición, evita I/O redundante)
    if (isset($_CACHE[$key])) {
        $entry = $_CACHE[$key];
        if ($entry['expires'] >= time()) {
            return $entry['data'];
        }
        unset($_CACHE[$key]);
    }
    
    // Nivel 2: Archivos (para persistencia entre peticiones)
    $cache_dir = __DIR__ . '/../cache';
    $cache_file = $cache_dir . '/' . md5($key) . '.cache';
    
    if (file_exists($cache_file)) {
        $content = @file_get_contents($cache_file);
        if ($content !== false) {
            $entry = json_decode($content, true);
            if (is_array($entry) && isset($entry['expires']) && array_key_exists('data', $entry)) {
                if ($entry['expires'] >= time()) {
                    // Guardar en Nivel 1 para accesos futuros en esta petición
                    $_CACHE[$key] = $entry;
                    return $entry['data'];
                }
            }
        }
        // Expirado o corrupto, lo eliminamos
        @unlink($cache_file);
    }
    
    return null;
}

function cache_set($key, $data, $ttl = null) {
    global $_CACHE;
    if (!CACHE_ENABLED) return;
    
    $expires = time() + ($ttl ?? CACHE_TTL);
    $entry = [
        'data' => $data,
        'expires' => $expires
    ];
    
    // Guardar en Nivel 1 (Memoria)
    $_CACHE[$key] = $entry;
    
    // Guardar en Nivel 2 (Archivo)
    $cache_dir = __DIR__ . '/../cache';
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0775, true);
    }
    
    if (is_dir($cache_dir) && is_writable($cache_dir)) {
        $cache_file = $cache_dir . '/' . md5($key) . '.cache';
        @file_put_contents($cache_file, json_encode($entry), LOCK_EX);
    }
}

// Incluir base de datos
$pdo = include __DIR__ . '/database.php';

require_once __DIR__ . '/catalog_images.php';

// Contacto principal para cotizaciones y dudas por WhatsApp.
define('COMPANY_WHATSAPP_PHONE', getenv('COMPANY_WHATSAPP_PHONE') ?: '3317915887');

// Funciones de utilidad
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }

    return trim((string)$data);
}

function decode_legacy_entities($value, int $passes = 3) {
    $result = (string)$value;
    if ($result === '') {
        return '';
    }

    $passes = max(1, $passes);
    for ($i = 0; $i < $passes; $i++) {
        $decoded = html_entity_decode($result, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded === $result) {
            break;
        }
        $result = $decoded;
    }

    return $result;
}

function whatsapp_phone_digits($phone = null) {
    $raw = $phone;
    if ($raw === null || trim((string)$raw) === '') {
        $raw = COMPANY_WHATSAPP_PHONE;
    }

    $digits = preg_replace('/\D+/', '', (string)$raw);
    if ($digits === '') {
        $digits = '3317915887';
    }
    return $digits;
}

function whatsapp_url($message, $phone = null) {
    $digits = whatsapp_phone_digits($phone);
    $encoded = rawurlencode((string)$message);
    return "https://wa.me/{$digits}?text={$encoded}";
}

function app_base_url() {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443)
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

function randomCode($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function rotate_csrf_token() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Extract CSRF token from multiple sources (POST, JSON, headers)
 * Checks: $_POST['csrf_token'], JSON body, X-CSRF-Token header
 */
function get_csrf_token_from_request() {
    // Try GET parameter for navigations like signed downloads
    if (!empty($_GET['csrf_token'])) {
        return $_GET['csrf_token'];
    }

    // Try POST parameter
    if (!empty($_POST['csrf_token'])) {
        return $_POST['csrf_token'];
    }
    
    // Try JSON body
    $json_input = file_get_contents('php://input');
    if (!empty($json_input)) {
        $data = json_decode($json_input, true);
        if (is_array($data) && !empty($data['csrf_token'])) {
            return $data['csrf_token'];
        }
    }
    
    // Try X-CSRF-Token header
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    
    return null;
}

/**
 * Verify CSRF token from request and return error if invalid
 */
function require_csrf_token() {
    $token = get_csrf_token_from_request();
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido o expirado']);
        exit;
    }
}

function getTrusSIDBug() {
    $trusted_proxies = ["127.0.0.1"];
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (!empty($_SERVER['HTTP_CLIENT_IP']) && in_array($_SERVER['HTTP_CLIENT_IP'], $trusted_proxies)) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    
    return $ip;
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function is_api_request() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    return strpos($uri, '/api/') !== false
        || strtolower($xrw) === 'xmlhttprequest'
        || strpos($accept, 'application/json') !== false;
}

function is_safe_return_path($path) {
    if (!is_string($path) || $path === '') {
        return false;
    }
    if ($path[0] !== '/') {
        return false;
    }
    if (strpos($path, '//') === 0) {
        return false;
    }
    return true;
}

function can_role_access_path($role, $path) {
    $role = (string)$role;
    if ($role === 'admin') {
        return true;
    }

    $adminOnlyPaths = [
        '/admin_supply.php',
        '/cashier.php',
        '/admin_login.php'
    ];

    foreach ($adminOnlyPaths as $adminPath) {
        if (strpos($path, $adminPath) === 0) {
            return false;
        }
    }

    return true;
}

function resolve_post_login_redirect($requested, $role) {
    $fallback = route_by_role($role);
    $requested = trim((string)$requested);
    if ($requested === '') {
        return $fallback;
    }

    $path = parse_url($requested, PHP_URL_PATH) ?: '';
    $query = parse_url($requested, PHP_URL_QUERY) ?: '';
    if (!is_safe_return_path($path)) {
        return $fallback;
    }

    $blocked = ['/login.php', '/register.php', '/admin_login.php', '/api/auth.php'];
    foreach ($blocked as $blockedPath) {
        if (strpos($path, $blockedPath) === 0) {
            return $fallback;
        }
    }

    if (!can_role_access_path($role, $path)) {
        return $fallback;
    }

    return $query !== '' ? ($path . '?' . $query) : $path;
}

function deny_unauthorized($code, $message) {
    if (is_api_request()) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    $currentUri = $_SERVER['REQUEST_URI'] ?? '/';

    if (is_logged_in()) {
        $fallback = '/dashboard.php?error=unauthorized';
        $currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $refererPath = $referer ? (parse_url($referer, PHP_URL_PATH) ?: '') : '';

        if ($refererPath !== '' && $refererPath !== $currentPath) {
            $separator = strpos($referer, '?') !== false ? '&' : '?';
            header('Location: ' . $referer . $separator . 'error=unauthorized');
            exit;
        }

        header('Location: ' . $fallback);
        exit;
    }

    $currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '';
    if ($currentPath !== '' && is_safe_return_path($currentPath) && strpos($currentPath, '/login.php') !== 0 && strpos($currentPath, '/admin_login.php') !== 0 && strpos($currentPath, '/register.php') !== 0) {
        $_SESSION['post_login_redirect'] = $currentUri;
    }

    if ((int)$code === 401) {
        $adminRoutes = [
            '/admin_login.php',
            '/admin_supply.php',
            '/cashier.php',
            '/tickets.php'
        ];

        $isAdminContext = false;
        foreach ($adminRoutes as $adminRoute) {
            if (strpos($currentPath, $adminRoute) === 0) {
                $isAdminContext = true;
                break;
            }
        }

        $loginPath = $isAdminContext ? '/admin_login.php' : '/login.php';
        $separator = strpos($loginPath, '?') !== false ? '&' : '?';
        $redirect = $loginPath . $separator . 'error=expired';

        if ($currentUri !== '/' && is_safe_return_path($currentPath)) {
            $redirect .= '&return_to=' . rawurlencode($currentUri);
        }

        header('Location: ' . $redirect);
        exit;
    }

    header('Location: /login.php?error=unauthorized');
    exit;
}

function require_login() {
    if (!is_logged_in()) {
        deny_unauthorized(401, 'Debes iniciar sesión');
    }

    // No permitir cache de páginas/sesiones autenticadas en navegador o proxies.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        deny_unauthorized(403, 'Acceso solo para administradores');
    }
    
    // Validar IP whitelist para admin (opcional)
    if (getenv('ENFORCE_ADMIN_IP_WHITELIST') === 'true') {
        if (!IPSecurity::isAdminIPWhitelisted()) {
            $secLogger = new SecurityLogger($GLOBALS['pdo']);
            $secLogger->logSuspiciousActivity('Admin access attempt from non-whitelisted IP: ' . IPSecurity::getClientIP());
            deny_unauthorized(403, 'Acceso denegado desde esta ubicación');
        }
    }
}

function require_client() {
    require_login();
    if ($_SESSION['role'] !== 'client') {
        deny_unauthorized(403, 'Acceso solo para clientes');
    }
}

function log_action($user_id, $action, $description, $ip_address) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO action_logs (user_id, action, description, ip_address, timestamp)
            VALUES (:user_id, :action, :description, :ip_address, NOW())
        ");
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':action' => $action,
            ':description' => $description,
            ':ip_address' => $ip_address
        ]);
    } catch (PDOException $e) {
        error_log("Error logging action: " . $e->getMessage());
    }
}

function calculateDiscountByPoints($points) {
    if ($points >= 1000) return 0.20; // 20% decuento
    if ($points >= 500) return 0.15;  // 15%
    if ($points >= 250) return 0.10;  // 10%
    if ($points >= 100) return 0.05;  // 5%
    return 0;
}

function calculateProductPrice($base_price, $quantity, $is_wholesale = false) {
    if ($is_wholesale && $quantity >= 100) {
        return $base_price * 0.70; // 30% descuento mayoreo
    } elseif ($quantity >= 50) {
        return $base_price * 0.80; // 20%
    } elseif ($quantity >= 20) {
        return $base_price * 0.90; // 10%
    }
    return $base_price;
}

function db_table_exists($table_name) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT to_regclass(?)");
        $stmt->execute([$table_name]);
        return $stmt->fetchColumn() !== null;
    } catch (Exception $e) {
        return false;
    }
}

function db_column_exists($table_name, $column_name) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE LOWER(table_name) = LOWER(?) AND LOWER(column_name) = LOWER(?) AND table_schema = current_schema())");
        $stmt->execute([$table_name, $column_name]);
        if ((bool)$stmt->fetchColumn()) {
            return true;
        }

        $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE LOWER(table_name) = LOWER(?) AND LOWER(column_name) = LOWER(?) AND table_schema = 'public')");
        $stmt->execute([$table_name, $column_name]);
        if ((bool)$stmt->fetchColumn()) {
            return true;
        }

        // Last-resort lookup for databases where tables are in non-default schemas.
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE LOWER(table_name) = LOWER(?) AND LOWER(column_name) = LOWER(?))");
        $stmt->execute([$table_name, $column_name]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function ensure_postgresql_form_schema() {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    global $pdo;

    try {
        if (!($pdo instanceof PDO)) {
            return;
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return;
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS deleted_product_skus (
            sku VARCHAR(100) PRIMARY KEY,
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reason TEXT
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
            id SERIAL PRIMARY KEY,
            user_id INTEGER UNIQUE,
            company_name VARCHAR(255),
            client_code VARCHAR(32),
            is_wholesale BOOLEAN DEFAULT false,
            credit_limit DECIMAL(12,2) DEFAULT 0,
            credit_available DECIMAL(12,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS wholesalers (
            id SERIAL PRIMARY KEY,
            client_id INTEGER NOT NULL,
            business_type VARCHAR(100),
            min_order_quantity INTEGER DEFAULT 50,
            discount_percentage DECIMAL(5,2) DEFAULT 15,
            payment_terms VARCHAR(100),
            is_approved BOOLEAN DEFAULT false,
            requested_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_date TIMESTAMP,
            approved_by INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id SERIAL PRIMARY KEY,
            client_id INTEGER,
            order_number VARCHAR(80),
            total_amount DECIMAL(12,2) DEFAULT 0,
            payment_amount DECIMAL(12,2) DEFAULT 0,
            balance DECIMAL(12,2) DEFAULT 0,
            is_wholesale BOOLEAN DEFAULT false,
            status VARCHAR(30) DEFAULT 'pending',
            payment_status VARCHAR(30) DEFAULT 'pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
            id SERIAL PRIMARY KEY,
            order_id INTEGER NOT NULL,
            product_id INTEGER,
            quantity INTEGER NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) DEFAULT 0,
            subtotal DECIMAL(12,2) DEFAULT 0,
            discount_percentage DECIMAL(8,2) DEFAULT 0,
            discount_amount DECIMAL(12,2) DEFAULT 0,
            line_total DECIMAL(12,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
            id SERIAL PRIMARY KEY,
            order_id INTEGER NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            payment_method VARCHAR(60),
            reference_number VARCHAR(120),
            processed_by INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_statistics (
            id SERIAL PRIMARY KEY,
            product_id INTEGER NOT NULL,
            month INTEGER NOT NULL,
            year INTEGER NOT NULL,
            total_quantity INTEGER DEFAULT 0,
            total_amount DECIMAL(12,2) DEFAULT 0,
            season VARCHAR(40),
            weather_condition VARCHAR(80),
            special_event VARCHAR(120),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (product_id, month, year)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS transaction_history (
            id SERIAL PRIMARY KEY,
            transaction_type VARCHAR(40) NOT NULL,
            reference_folio VARCHAR(80) NOT NULL,
            data_json TEXT,
            created_by INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $usersAlters = [
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255)",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS password VARCHAR(255)",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS first_name VARCHAR(120)",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_name VARCHAR(120)",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS name VARCHAR(255)",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'client'",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30)",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS birthdate DATE",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS birthday DATE",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS loyalty_points INTEGER DEFAULT 0",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS points INTEGER DEFAULT 0",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS active BOOLEAN DEFAULT true",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified BOOLEAN DEFAULT true",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS user_code VARCHAR(32)",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP"
        ];

        $productAlters = [
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS sku VARCHAR(100)",
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS name VARCHAR(255)",
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS description TEXT",
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS category VARCHAR(120)",
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS unit_price DECIMAL(12,2) DEFAULT 0",
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS sell_price DECIMAL(12,2) DEFAULT 0",
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS stock_quantity INTEGER DEFAULT 0",
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS reorder_level INTEGER DEFAULT 10",
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS image_url TEXT",
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true",
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS active BOOLEAN DEFAULT true",
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE products ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
        ];

        $clientAlters = [
            "ALTER TABLE clients ADD COLUMN IF NOT EXISTS company_name VARCHAR(255)",
            "ALTER TABLE clients ADD COLUMN IF NOT EXISTS client_code VARCHAR(32)",
            "ALTER TABLE clients ADD COLUMN IF NOT EXISTS is_wholesale BOOLEAN DEFAULT false",
            "ALTER TABLE clients ADD COLUMN IF NOT EXISTS credit_limit DECIMAL(12,2) DEFAULT 0",
            "ALTER TABLE clients ADD COLUMN IF NOT EXISTS credit_available DECIMAL(12,2) DEFAULT 0",
            "ALTER TABLE clients ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE clients ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
        ];

        $wholesaleAlters = [
            "ALTER TABLE wholesalers ADD COLUMN IF NOT EXISTS requested_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE wholesalers ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE wholesalers ADD COLUMN IF NOT EXISTS approved_date TIMESTAMP",
            "ALTER TABLE wholesalers ADD COLUMN IF NOT EXISTS approved_by INTEGER"
        ];

        $orderAlters = [
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS client_id INTEGER",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS order_number VARCHAR(80)",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS total_amount DECIMAL(12,2) DEFAULT 0",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_amount DECIMAL(12,2) DEFAULT 0",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS balance DECIMAL(12,2) DEFAULT 0",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS is_wholesale BOOLEAN DEFAULT false",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'pending'",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status VARCHAR(30) DEFAULT 'pending'",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS notes TEXT",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
        ];

        foreach (array_merge($usersAlters, $productAlters, $clientAlters, $wholesaleAlters, $orderAlters) as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Exception $ignored) {
            }
        }

        try {
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_user_code_unique ON users (user_code)");
        } catch (Exception $ignored) {
        }
        try {
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_products_sku_unique ON products (sku)");
        } catch (Exception $ignored) {
        }

        try {
            $pdo->exec("UPDATE users SET name = TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) WHERE COALESCE(name, '') = '' AND (COALESCE(first_name, '') <> '' OR COALESCE(last_name, '') <> '')");
        } catch (Exception $ignored) {
        }

        try {
            $pdo->exec("UPDATE products SET name = COALESCE(NULLIF(description, ''), 'Producto ' || id::text) WHERE COALESCE(name, '') = ''");
        } catch (Exception $ignored) {
        }

        try {
            $pdo->exec("UPDATE products SET unit_price = COALESCE(unit_price, sell_price, 0)");
        } catch (Exception $ignored) {
        }

        try {
            $pdo->exec("UPDATE products SET is_active = COALESCE(is_active, active, true)");
        } catch (Exception $ignored) {
        }

        try {
            $pdo->exec("UPDATE clients c SET client_code = u.user_code FROM users u WHERE c.user_id = u.id AND COALESCE(c.client_code, '') = '' AND COALESCE(u.user_code, '') <> ''");
        } catch (Exception $ignored) {
        }
    } catch (Exception $e) {
        error_log('ensure_postgresql_form_schema warning: ' . $e->getMessage());
    }
}


function route_by_role($role) {
    if ($role === 'admin') {
        return '/dashboard.php';
    }
    if ($role === 'employee') {
        return '/dashboard.php';
    }
    return '/dashboard.php';
}

function apply_login_engagement_rules($user_id) {
    global $pdo;

    try {
        $userStmt = $pdo->prepare("SELECT id, role, birthdate, birthday, loyalty_points, points FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch();

        if (!$user || (($user['role'] ?? 'client') !== 'client')) {
            return ['birthday_bonus_awarded' => false];
        }

        $birth = $user['birthdate'] ?? ($user['birthday'] ?? null);
        if (empty($birth)) {
            return ['birthday_bonus_awarded' => false];
        }

        $today = date('m-d');
        $birthMmDd = date('m-d', strtotime((string)$birth));
        if ($today !== $birthMmDd) {
            return ['birthday_bonus_awarded' => false];
        }

        $alreadyAwarded = false;
        if (db_table_exists('action_logs')) {
            $logStmt = $pdo->prepare("SELECT 1 FROM action_logs WHERE user_id = ? AND action = 'BIRTHDAY_BONUS' AND EXTRACT(YEAR FROM timestamp) = EXTRACT(YEAR FROM NOW()) LIMIT 1");
            $logStmt->execute([$user_id]);
            $alreadyAwarded = (bool)$logStmt->fetchColumn();
        }

        if ($alreadyAwarded) {
            return ['birthday_bonus_awarded' => false];
        }

        $pointsColumn = db_column_exists('users', 'loyalty_points') ? 'loyalty_points' : (db_column_exists('users', 'points') ? 'points' : null);
        if ($pointsColumn) {
            $updateStmt = $pdo->prepare("UPDATE users SET {$pointsColumn} = COALESCE({$pointsColumn}, 0) + 50 WHERE id = ?");
            $updateStmt->execute([$user_id]);

            if (isset($_SESSION['loyalty_points'])) {
                $_SESSION['loyalty_points'] = (int)$_SESSION['loyalty_points'] + 50;
            }
        }

        if (db_table_exists('clients') && db_table_exists('promotions')) {
            $clientStmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
            $clientStmt->execute([$user_id]);
            $clientId = $clientStmt->fetchColumn();
            if ($clientId) {
                $promoStmt = $pdo->prepare("INSERT INTO promotions (client_id, promotion_type, discount_percentage, expiry_date) VALUES (?, 'birthday_bonus', 10, ?) ");
                $promoStmt->execute([$clientId, date('Y-m-d', strtotime('+30 days'))]);
            }
        }

        if (db_table_exists('action_logs')) {
            log_action($user_id, 'BIRTHDAY_BONUS', 'Bono de cumpleaños aplicado: +50 puntos y 10% promo', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        }

        $_SESSION['birthday_bonus_notice'] = 'Feliz cumpleaños. Recibiste 50 puntos y un bono del 10%.';

        return [
            'birthday_bonus_awarded' => true,
            'message' => $_SESSION['birthday_bonus_notice']
        ];
    } catch (Exception $e) {
        error_log('apply_login_engagement_rules error: ' . $e->getMessage());
        return ['birthday_bonus_awarded' => false];
    }
}

ensure_postgresql_form_schema();
?>
