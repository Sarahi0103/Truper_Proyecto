<?php
/**
 * Auth Controller - Truper
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Utilities.php';

$action = $_POST['action'] ?? null;

if ($action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $user_model = new User();
    $result = $user_model->login($email, $password);
    
    if ($result['success']) {
        Logger::info("User login: " . $email);
        header("Location: /views/dashboard.php");
        exit();
    } else {
        Logger::warning("Failed login attempt: " . $email);
        header("Location: /views/login.php?error=" . urlencode($result['message']));
        exit();
    }
}

elseif ($action === 'register') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    
    if ($password !== $password_confirm) {
        header("Location: /views/register.php?error=" . urlencode("Las contraseÃ±as no coinciden"));
        exit();
    }
    
    $user_model = new User();
    $result = $user_model->register($email, $password, $name, $phone);
    
    if ($result['success']) {
        // Guardar fecha de cumpleaÃ±os
        $_SESSION['user_id'] = $result['user_id'];
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = 'client';
        
        Logger::info("User registered: " . $email);
        
        // Enviar email de bienvenida
        EmailService::sendOrderConfirmation($email, "#BIENVENIDA", "Â¡Registrate exitoso!");
        
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


