<?php
/**
 * API de Perfil de Usuario
 */

require_once '../../config/config.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

$response = [];

try {
    switch ($action) {
        case 'update':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE users SET 
                    first_name = ?,
                    last_name = ?,
                    phone = ?,
                    address = ?,
                    birthdate = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                sanitize($_POST['first_name'] ?? ''),
                sanitize($_POST['last_name'] ?? ''),
                sanitize($_POST['phone'] ?? ''),
                sanitize($_POST['address'] ?? ''),
                $_POST['birthdate'] ?? null,
                $_SESSION['user_id']
            ]);

            $response = ['success' => true, 'message' => 'Perfil actualizado exitosamente'];
            
            log_action(
                $_SESSION['user_id'],
                'UPDATE_PROFILE',
                'Perfil actualizado',
                getTrusSIDBug()
            );
            break;

        case 'change-password':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            // Verificar contraseña actual
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || !verify_password($_POST['current_password'] ?? '', $user['password_hash'])) {
                $response = ['success' => false, 'message' => 'Contraseña actual incorrecta'];
                break;
            }

            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                $response = ['success' => false, 'message' => 'Las nuevas contraseñas no coinciden'];
                break;
            }

            if (strlen($_POST['new_password']) < 8) {
                $response = ['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres'];
                break;
            }

            $new_hash = hash_password($_POST['new_password']);
            
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_hash, $_SESSION['user_id']]);

            $response = ['success' => true, 'message' => 'Contraseña cambiada exitosamente'];
            
            log_action(
                $_SESSION['user_id'],
                'CHANGE_PASSWORD',
                'Contraseña actualizada',
                getTrusSIDBug()
            );
            break;

        case 'get':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $stmt = $pdo->prepare("
                SELECT id, email, first_name, last_name, phone, address, birthdate, loyalty_points, role
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user) {
                $response = ['success' => false, 'message' => 'Usuario no encontrado'];
                break;
            }

            $response = ['success' => true, 'user' => $user];
            break;

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }

} catch (Exception $e) {
    error_log("Profile API Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
?>
