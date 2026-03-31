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

            $sets = [];
            $values = [];

            if (db_column_exists('users', 'first_name')) {
                $sets[] = 'first_name = ?';
                $values[] = sanitize($_POST['first_name'] ?? '');
            }
            if (db_column_exists('users', 'last_name')) {
                $sets[] = 'last_name = ?';
                $values[] = sanitize($_POST['last_name'] ?? '');
            }
            if (db_column_exists('users', 'name')) {
                $sets[] = 'name = ?';
                $values[] = trim(sanitize($_POST['first_name'] ?? '') . ' ' . sanitize($_POST['last_name'] ?? ''));
            }
            if (db_column_exists('users', 'phone')) {
                $sets[] = 'phone = ?';
                $values[] = sanitize($_POST['phone'] ?? '');
            }
            if (db_column_exists('users', 'address')) {
                $sets[] = 'address = ?';
                $values[] = sanitize($_POST['address'] ?? '');
            }
            if (db_column_exists('users', 'birthdate')) {
                $sets[] = 'birthdate = ?';
                $values[] = $_POST['birthdate'] ?? null;
            } elseif (db_column_exists('users', 'birthday')) {
                $sets[] = 'birthday = ?';
                $values[] = $_POST['birthdate'] ?? null;
            }
            if (db_column_exists('users', 'updated_at')) {
                $sets[] = 'updated_at = NOW()';
            }

            if (empty($sets)) {
                $response = ['success' => false, 'message' => 'No hay campos disponibles para actualizar'];
                break;
            }

            $values[] = $_SESSION['user_id'];
            $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $stmt->execute($values);

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
            $stmt = $pdo->prepare("SELECT password_hash, password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            $currentPassword = $_POST['current_password'] ?? '';
            $ok = false;
            if (!empty($user['password_hash'])) {
                $ok = verify_password($currentPassword, $user['password_hash']);
            }
            if (!$ok && !empty($user['password'])) {
                $legacy = (string)$user['password'];
                if (str_starts_with($legacy, '$2y$') || str_starts_with($legacy, '$2a$') || str_starts_with($legacy, '$2b$')) {
                    $ok = verify_password($currentPassword, $legacy);
                } else {
                    $ok = hash_equals($legacy, (string)$currentPassword);
                }
            }

            if (!$user || !$ok) {
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

            if (db_column_exists('users', 'password_hash')) {
                $sql = 'UPDATE users SET password_hash = ?';
            } else {
                $sql = 'UPDATE users SET password = ?';
            }
            if (db_column_exists('users', 'updated_at')) {
                $sql .= ', updated_at = NOW()';
            }
            $sql .= ' WHERE id = ?';
            $stmt = $pdo->prepare($sql);
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

            $select = ['id', 'email'];
            $select[] = db_column_exists('users', 'first_name') ? 'first_name' : "'' AS first_name";
            $select[] = db_column_exists('users', 'last_name') ? 'last_name' : "'' AS last_name";
            $select[] = db_column_exists('users', 'phone') ? 'phone' : "'' AS phone";
            $select[] = db_column_exists('users', 'address') ? 'address' : "'' AS address";
            if (db_column_exists('users', 'birthdate')) {
                $select[] = 'birthdate';
            } elseif (db_column_exists('users', 'birthday')) {
                $select[] = 'birthday AS birthdate';
            } else {
                $select[] = 'NULL AS birthdate';
            }
            if (db_column_exists('users', 'loyalty_points')) {
                $select[] = 'loyalty_points';
            } elseif (db_column_exists('users', 'points')) {
                $select[] = 'points AS loyalty_points';
            } else {
                $select[] = '0 AS loyalty_points';
            }
            $select[] = db_column_exists('users', 'role') ? 'role' : "'client' AS role";

            $stmt = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM users WHERE id = ?');
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
