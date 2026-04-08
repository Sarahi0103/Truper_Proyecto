<?php
/**
 * Configuración General de la Aplicación
 */

$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 1800,
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
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// Incluir base de datos
$pdo = include __DIR__ . '/database.php';

// Funciones de utilidad
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
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

function deny_unauthorized($code, $message) {
    if (is_api_request()) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    if (is_logged_in()) {
        $fallback = '/dashboard.php?error=unauthorized';
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
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

    header("Location: /login.php?error=unauthorized");
    exit;
}

function require_login() {
    if (!is_logged_in()) {
        deny_unauthorized(401, 'Debes iniciar sesión');
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        deny_unauthorized(403, 'Acceso solo para administradores');
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
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ?)");
        $stmt->execute([$table_name, $column_name]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function get_xlsx_seed_products() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    $seedPath = __DIR__ . '/../db/PRODUCTOS_XLSX_IMPORT.sql';
    if (!file_exists($seedPath)) {
        return $cache;
    }

    $lines = file($seedPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return $cache;
    }

    $id = 900000;
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || $trimmed[0] !== '(') {
            continue;
        }

        $pattern = "/^\\('((?:[^']|'{2})*)',\\s*'((?:[^']|'{2})*)',\\s*'((?:[^']|'{2})*)',\\s*'((?:[^']|'{2})*)',\\s*([0-9]+(?:\\.[0-9]+)?),\\s*'((?:[^']|'{2})*)'\\),?$/";
        if (!preg_match($pattern, $trimmed, $m)) {
            continue;
        }

        $sku = str_replace("''", "'", $m[1]);
        $name = str_replace("''", "'", $m[2]);
        $description = str_replace("''", "'", $m[3]);
        $category = str_replace("''", "'", $m[4]);
        $price = (float)$m[5];
        $barcode = str_replace("''", "'", $m[6]);

        $cache[] = [
            'id' => $id++,
            'sku' => $sku,
            'name' => $name !== '' ? $name : $description,
            'description' => $description,
            'category' => $category,
            'unit_price' => $price,
            'barcode' => $barcode,
            'technical_specs' => 'N/A',
            'stock_quantity' => 50,
            'image_url' => 'images/products/default-product.svg',
            'variants_json' => '[]'
        ];
    }

    return $cache;
}

function ensure_xlsx_products_seeded() {
    static $alreadyChecked = false;
    if ($alreadyChecked) {
        return;
    }
    $alreadyChecked = true;

    global $pdo;

    if (!db_table_exists('products')) {
        return;
    }

    $seedPath = __DIR__ . '/../db/PRODUCTOS_XLSX_IMPORT.sql';
    if (!file_exists($seedPath)) {
        return;
    }

    $seedProducts = get_xlsx_seed_products();
    if (empty($seedProducts)) {
        return;
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE sku LIKE 'XLS-%'");
        $existing = (int)$stmt->fetchColumn();
        if ($existing > 0) {
            return;
        }

        $sql = file_get_contents($seedPath);
        if ($sql === false || trim($sql) === '') {
            throw new Exception('SQL seed vacío');
        }

        $pdo->exec($sql);
    } catch (Exception $e) {
        error_log('No fue posible autoimportar productos XLS: ' . $e->getMessage());

        foreach ($seedProducts as $p) {
            try {
                $stmtA = $pdo->prepare("INSERT INTO products (sku, name, description, category, unit_price, barcode, stock_quantity, reorder_level, is_active) VALUES (?, ?, ?, ?, ?, ?, 50, 10, true) ON CONFLICT (sku) DO NOTHING");
                $stmtA->execute([$p['sku'], $p['name'], $p['description'], $p['category'], $p['unit_price'], $p['barcode']]);
                continue;
            } catch (Exception $ignoredA) {
            }

            try {
                $stmtB = $pdo->prepare("INSERT INTO products (sku, name, description, category, unit_price, barcode, stock_quantity, reorder_level, is_active) VALUES (?, ?, ?, ?, ?, ?, 50, 10, true)");
                $stmtB->execute([$p['sku'], $p['name'], $p['description'], $p['category'], $p['unit_price'], $p['barcode']]);
                continue;
            } catch (Exception $ignoredB) {
            }

            try {
                $stmtC = $pdo->prepare("INSERT IGNORE INTO products (sku, name, description, category, sell_price, barcode, active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmtC->execute([$p['sku'], $p['name'], $p['description'], $p['category'], $p['unit_price'], $p['barcode']]);
            } catch (Exception $ignoredC) {
            }
        }
    }
}

function route_by_role($role) {
    if ($role === 'admin') {
        return '/admin_supply.php';
    }
    if ($role === 'employee') {
        return '/tasks.php';
    }
    return '/orders.php?tab=newOrder';
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
            log_action($user_id, 'BIRTHDAY_BONUS', 'Bono de cumpleaños aplicado: +50 puntos y 10% promo', getTrusSIDBug());
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
?>
