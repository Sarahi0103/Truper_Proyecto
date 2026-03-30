<?php
/**
 * Configuración General de la Aplicación
 */

session_start();

// Headers de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// Configuración de sesión
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

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

function require_login() {
    if (!is_logged_in()) {
        header("Location: /truper_platform/public/login.php");
        exit;
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: /truper_platform/public/dashboard.php?error=unauthorized");
        exit;
    }
}

function require_client() {
    require_login();
    if ($_SESSION['role'] !== 'client') {
        header("Location: /truper_platform/public/dashboard.php?error=unauthorized");
        exit;
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
?>
