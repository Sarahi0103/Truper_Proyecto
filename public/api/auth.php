<?php
/**
 * API de Autenticación
 */

require_once '../../config/config.php';
require_once '../../src/controllers/AuthController.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? 'login');

$auth = new AuthController($pdo);
$response = [];

function auth_rate_limit_check($key, $maxAttempts, $windowSeconds) {
    $now = time();
    $bucket = $_SESSION[$key] ?? ['count' => 0, 'start' => $now];
    if (($now - (int)$bucket['start']) > $windowSeconds) {
        $bucket = ['count' => 0, 'start' => $now];
    }
    if ((int)$bucket['count'] >= $maxAttempts) {
        return false;
    }
    $bucket['count'] = (int)$bucket['count'] + 1;
    $_SESSION[$key] = $bucket;
    return true;
}

function auth_rate_limit_reset($key) {
    unset($_SESSION[$key]);
}

try {
    switch ($action) {
        case 'register':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                $response = ['success' => false, 'message' => 'Sesión inválida. Recarga la página.'];
                break;
            }

            $registerKey = 'register_attempts_' . hash('sha256', strtolower(trim((string)($_POST['email'] ?? ''))) . '_' . getTrusSIDBug());
            if (!auth_rate_limit_check($registerKey, 4, 900)) {
                $response = ['success' => false, 'message' => 'Demasiados intentos de registro. Intenta en 15 minutos.'];
                break;
            }

            $response = $auth->register([
                'email' => sanitize($_POST['email'] ?? ''),
                'first_name' => sanitize($_POST['first_name'] ?? ''),
                'last_name' => sanitize($_POST['last_name'] ?? ''),
                'phone' => sanitize($_POST['phone'] ?? ''),
                'birthdate' => $_POST['birthdate'] ?? null,
                'company_name' => sanitize($_POST['company_name'] ?? '')
            ]);

            if ($response['success']) {
                auth_rate_limit_reset($registerKey);
                $redirect = '/login.php?registered=1';
                if (!empty($response['user_code'])) {
                    $redirect .= '&code=' . rawurlencode((string)$response['user_code']);
                }
                $response['redirect'] = $redirect;
            }
            break;

        case 'client-login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                $response = ['success' => false, 'message' => 'Sesión inválida. Recarga la página.'];
                break;
            }

            $loginKey = 'client_login_attempts_' . hash('sha256', strtolower(trim((string)($_POST['code'] ?? ''))) . '_' . getTrusSIDBug());
            if (!auth_rate_limit_check($loginKey, 6, 900)) {
                $response = ['success' => false, 'message' => 'Demasiados intentos. Intenta en 15 minutos.'];
                break;
            }

            $response = $auth->loginByClientCode(
                sanitize($_POST['code'] ?? ''),
                $_POST['birthdate'] ?? ''
            );

            if ($response['success']) {
                auth_rate_limit_reset($loginKey);
                log_action($_SESSION['user_id'], 'LOGIN_CLIENT', 'Inicio de sesión de cliente por código', getTrusSIDBug());
                $role = $_SESSION['role'] ?? 'client';
                $requested = trim((string)($_POST['return_to'] ?? ($_SESSION['post_login_redirect'] ?? '')));
                $response['redirect'] = resolve_post_login_redirect($requested, $role);
                unset($_SESSION['post_login_redirect']);
            }
            break;

        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                $response = ['success' => false, 'message' => 'Sesión inválida. Recarga la página.'];
                break;
            }

            $loginKey = 'login_attempts_' . hash('sha256', strtolower(trim((string)($_POST['email'] ?? ''))) . '_' . getTrusSIDBug());
            if (!auth_rate_limit_check($loginKey, 6, 900)) {
                $response = ['success' => false, 'message' => 'Demasiados intentos. Intenta en 15 minutos.'];
                break;
            }

            $response = $auth->login(
                sanitize($_POST['email'] ?? ''),
                $_POST['password'] ?? ''
            );

            if ($response['success']) {
                auth_rate_limit_reset($loginKey);
                log_action($_SESSION['user_id'], 'LOGIN', 'Inicio de sesión exitoso', getTrusSIDBug());
                $engagement = apply_login_engagement_rules($_SESSION['user_id']);
                if (!empty($engagement['birthday_bonus_awarded'])) {
                    $response['message'] = ($response['message'] ?? 'Bienvenido') . '. ' . ($engagement['message'] ?? 'Bono de cumpleaños aplicado.');
                }
                $role = $_SESSION['role'] ?? 'client';
                $requested = trim((string)($_POST['return_to'] ?? ($_SESSION['post_login_redirect'] ?? '')));
                $response['redirect'] = resolve_post_login_redirect($requested, $role);
                unset($_SESSION['post_login_redirect']);
            }
            break;

        case 'logout':
            $response = $auth->logout();
            $xrw = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
            $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
            $isBrowserNavigation = ($xrw !== 'xmlhttprequest') && (strpos($accept, 'text/html') !== false || $accept === '' || strpos($accept, '*/*') !== false);

            if ($isBrowserNavigation || !is_api_request()) {
                header('Location: /index.php');
                exit;
            }
            $response['redirect'] = '/index.php';
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
