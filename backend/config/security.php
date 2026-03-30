<?php
/**
 * Configuración de Seguridad - TRUPPER
 */

// Comenzar sesión segura
session_start();

// Headers de seguridad
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'');

// Configuración de sesión
ini_set('session.cookie_secure', true);
ini_set('session.cookie_httponly', true);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);

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
