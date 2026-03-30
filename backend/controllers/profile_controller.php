<?php
/**
 * Profile Controller - Truper
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

Security::requireAuth();
Security::requirePost();

if (!Security::verifyRequestCSRFToken()) {
    header("Location: /views/profile.php?error=" . urlencode("Sesión inválida, recarga la página"));
    exit();
}

$action = $_POST['action'] ?? null;
$user_model = new User();

if ($action === 'update_profile') {
    $name = Security::sanitize($_POST['name'] ?? '');
    $phone = Security::sanitize($_POST['phone'] ?? '');
    $birthday = Security::sanitize($_POST['birthday'] ?? '');
    
    if ($user_model->updateProfile($_SESSION['user_id'], $name, $phone, $birthday)) {
        $_SESSION['user_name'] = $name;
        header("Location: /views/profile.php?success=Perfil actualizado");
    } else {
        header("Location: /views/profile.php?error=Error al actualizar");
    }
}

elseif ($action === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $passwordHash = $user_model->getPasswordHashById($_SESSION['user_id']);

    if (!$passwordHash || !Security::verifyPassword($current_password, $passwordHash)) {
        header("Location: /views/profile.php?error=Contraseña actual incorrecta");
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        header("Location: /views/profile.php?error=Las contraseñas no coinciden");
        exit();
    }
    
    if (strlen($new_password) < 8) {
        header("Location: /views/profile.php?error=La contraseña debe tener al menos 8 caracteres");
        exit();
    }

    if (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/\d/', $new_password)) {
        header("Location: /views/profile.php?error=La contraseña debe incluir letras y números");
        exit();
    }
    
    // Actualizar contraseña
    $hashed = Security::hashPassword($new_password);
    $query = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $GLOBALS['db']->prepare($query);
    $stmt->bind_param("si", $hashed, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        header("Location: /views/profile.php?success=Contraseña actualizada");
    } else {
        header("Location: /views/profile.php?error=Error al actualizar contraseña");
    }
}

else {
    header("Location: /views/profile.php");
}
?>


