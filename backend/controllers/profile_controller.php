<?php
/**
 * Profile Controller - Truper
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

Security::requireAuth();

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
    
    $user = $user_model->getById($_SESSION['user_id']);
    
    if (!Security::verifyPassword($current_password, $user['password'])) {
        header("Location: /views/profile.php?error=ContraseÃ±a actual incorrecta");
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        header("Location: /views/profile.php?error=Las contraseÃ±as no coinciden");
        exit();
    }
    
    if (strlen($new_password) < 6) {
        header("Location: /views/profile.php?error=La contraseÃ±a debe tener al menos 6 caracteres");
        exit();
    }
    
    // Actualizar contraseÃ±a
    $hashed = Security::hashPassword($new_password);
    $query = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $GLOBALS['db']->prepare($query);
    $stmt->bind_param("si", $hashed, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        header("Location: /views/profile.php?success=ContraseÃ±a actualizada");
    } else {
        header("Location: /views/profile.php?error=Error al actualizar contraseÃ±a");
    }
}

else {
    header("Location: /views/profile.php");
}
?>


