<?php
/**
 * Configuración de Seguridad - Truper
 */

// Configuración de sesión segura (antes de iniciar sesión)
if (session_status() === PHP_SESSION_NONE) {
    $sessionTimeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    ini_set('session.gc_maxlifetime', (string)$sessionTimeout);
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.use_strict_mode', '1');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    session_start();
}

// Headers de seguridad
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; object-src \'none\'; base-uri \'self\'; frame-ancestors \'self\'');
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443)) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

class Security {
    /**
     * Verificar si el usuario está autenticado
     */
    public static function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Verificar si es administrador
     */
    public static function isAdmin() {
        return self::isAuthenticated() && $_SESSION['user_role'] === 'admin';
    }

    /**
     * Verificar si es cliente
     */
    public static function isClient() {
        return self::isAuthenticated() && $_SESSION['user_role'] === 'client';
    }

    /**
     * Verificar si es empleado
     */
    public static function isEmployee() {
        return self::isAuthenticated() && $_SESSION['user_role'] === 'employee';
    }

    /**
     * Hash de contraseña
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verificar contraseña
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Generar token CSRF
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verificar token CSRF
     */
    public static function verifyCSRFToken($token) {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    /**
     * Verificar token CSRF desde POST o header
     */
    public static function verifyRequestCSRFToken() {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return self::verifyCSRFToken($token);
    }

    /**
     * Exigir método POST
     */
    public static function requirePost() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('Método no permitido');
        }
    }

    /**
     * Sanitizar entrada
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validar email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Logout
     */
    public static function logout() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        header("Location: /index.php");
        exit();
    }

    /**
     * Redirigir si no está autenticado
     */
    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            header("Location: /views/login.php");
            exit();
        }
    }

    /**
     * Redirigir si no es admin
     */
    public static function requireAdmin() {
        if (!self::isAdmin()) {
            header("Location: /views/unauthorized.php");
            exit();
        }
    }
}
?>


