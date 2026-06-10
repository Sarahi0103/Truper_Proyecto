<?php
/**
 * Auth Controller - Truper
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Utilities.php';

$action = $_POST['action'] ?? null;

if (in_array($action, ['login', 'register'], true)) {
    Security::requirePost();
    if (!Security::verifyRequestCSRFToken()) {
        header("Location: /views/login.php?error=" . urlencode("Sesión inválida, recarga la página"));
        exit();
    }
}

if ($action === 'login') {
    $email = Security::sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Rate limiting por IP
    $ipKey = 'login_attempts_ip_' . hash('sha256', $ip_address);
    $ipBucket = $_SESSION[$ipKey] ?? ['count' => 0, 'first' => time()];
    $windowSeconds = 900; // 15 minutos
    $maxIpAttempts = 10; // Máximo 10 intentos por IP

    if ((time() - $ipBucket['first']) > $windowSeconds) {
        $ipBucket = ['count' => 0, 'first' => time()];
    }

    if ($ipBucket['count'] >= $maxIpAttempts) {
        Logger::warning("IP blocked due to too many login attempts: " . $ip_address);
        header("Location: /views/login.php?error=" . urlencode("Demasiados intentos desde tu IP. Intenta de nuevo en 15 minutos"));
        exit();
    }

    // Limitador básico de intentos por sesión+email (protección inicial)
    $attemptKey = 'login_attempts_' . hash('sha256', strtolower($email));
    $bucket = $_SESSION[$attemptKey] ?? ['count' => 0, 'first' => time()];
    $maxAttempts = 5;

    if ((time() - $bucket['first']) > $windowSeconds) {
        $bucket = ['count' => 0, 'first' => time()];
    }

    if ($bucket['count'] >= $maxAttempts) {
        header("Location: /views/login.php?error=" . urlencode("Demasiados intentos. Intenta de nuevo en 15 minutos"));
        exit();
    }
    
    $user_model = new User();
    $result = $user_model->login($email, $password);
    
    if ($result['success']) {
        session_regenerate_id(true);
        unset($_SESSION[$attemptKey]);
        unset($_SESSION[$ipKey]);
        Logger::info("User login: " . $email . " from IP: " . $ip_address);
        header("Location: /views/dashboard.php");
        exit();
    } else {
        $bucket['count']++;
        $_SESSION[$attemptKey] = $bucket;
        
        $ipBucket['count']++;
        $_SESSION[$ipKey] = $ipBucket;
        
        Logger::warning("Failed login attempt: " . $email . " from IP: " . $ip_address);
        header("Location: /views/login.php?error=" . urlencode($result['message']));
        exit();
    }
}

elseif ($action === 'register') {
    $email = Security::sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $name = Security::sanitize($_POST['name'] ?? '');
    $phone = Security::sanitize($_POST['phone'] ?? '');
    $birthday = Security::sanitize($_POST['birthday'] ?? '');

    if (!Security::validateEmail($email)) {
        header("Location: /views/register.php?error=" . urlencode("Email inválido"));
        exit();
    }
    
    if ($password !== $password_confirm) {
        header("Location: /views/register.php?error=" . urlencode("Las contraseñas no coinciden"));
        exit();
    }

    if (strlen($password) < 8) {
        header("Location: /views/register.php?error=" . urlencode("La contraseña debe tener al menos 8 caracteres"));
        exit();
    }

    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        header("Location: /views/register.php?error=" . urlencode("La contraseña debe incluir letras y números"));
        exit();
    }
    
    $user_model = new User();
    $result = $user_model->register($email, $password, $name, $phone);
    
    if ($result['success']) {
        // Guardar fecha de cumpleaños
        session_regenerate_id(true);
        $_SESSION['user_id'] = $result['user_id'];
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = 'client';
        
        Logger::info("User registered: " . $email);
        
        // Enviar email de bienvenida
        EmailService::sendOrderConfirmation($email, "#BIENVENIDA", "¡Registrate exitoso!");
        
        header("Location: /views/dashboard.php");
        exit();
    } else {
        Logger::warning("Failed registration: " . $email);
        header("Location: /views/register.php?error=" . urlencode($result['message']));
        exit();
    }
}

elseif ($action === 'logout') {
    Security::logout();
}

else {
    header("Location: /index.php");
}
?>


