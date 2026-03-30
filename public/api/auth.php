<?php
/**
 * API de Autenticación
 */

require_once '../../config/config.php';
require_once '../../src/controllers/AuthController.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

$auth = new AuthController($pdo);
$response = [];

try {
    switch ($action) {
        case 'register':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $response = $auth->register([
                'email' => sanitize($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'confirm_password' => $_POST['confirm_password'] ?? '',
                'first_name' => sanitize($_POST['first_name'] ?? ''),
                'last_name' => sanitize($_POST['last_name'] ?? ''),
                'phone' => sanitize($_POST['phone'] ?? ''),
                'company_name' => sanitize($_POST['company_name'] ?? '')
            ]);

            if ($response['success']) {
                $response['redirect'] = '/truper_platform/public/login.php';
            }
            break;

        case 'login':
        default:
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $response = $auth->login(
                sanitize($_POST['email'] ?? ''),
                $_POST['password'] ?? ''
            );

            if ($response['success']) {
                $response['redirect'] = $_SESSION['role'] === 'admin' 
                    ? '/truper_platform/public/dashboard.php?role=admin'
                    : '/truper_platform/public/dashboard.php';
            }
            break;

        case 'logout':
            $response = $auth->logout();
            $response['redirect'] = '/truper_platform/public/login.php';
            break;

        case 'verify-email':
            if (!isset($_GET['user_id']) || !isset($_GET['token'])) {
                $response = ['success' => false, 'message' => 'Tokens inválidos'];
                break;
            }

            $response = $auth->verifyEmail(
                (int)$_GET['user_id'],
                sanitize($_GET['token'])
            );
            break;

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }

} catch (Exception $e) {
    error_log("Auth API Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
?>
