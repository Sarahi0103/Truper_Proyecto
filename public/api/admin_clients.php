<?php
require_once '../../config/config.php';
require_once '../../src/controllers/AuthController.php';

require_admin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'create';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$auth = new AuthController($pdo);
$response = [];

try {
    switch ($action) {
        case 'create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $firstName = sanitize($input['first_name'] ?? '');
            $lastName = sanitize($input['last_name'] ?? '');
            $phone = sanitize($input['phone'] ?? '');
            $email = sanitize($input['email'] ?? '');
            $password = (string)($input['password'] ?? '');
            $companyName = sanitize($input['company_name'] ?? '');
            $birthdate = $input['birthdate'] ?? null;

            if ($firstName === '' || $lastName === '' || $phone === '' || $password === '') {
                $response = ['success' => false, 'message' => 'Nombre, apellido, teléfono y contraseña son obligatorios'];
                break;
            }

            if ($email === '') {
                $digits = preg_replace('/\D+/', '', $phone);
                $suffix = $digits !== '' ? substr($digits, -8) : (string)time();
                $email = 'cliente.' . $suffix . '@truper.local';
            }

            $result = $auth->register([
                'email' => $email,
                'password' => $password,
                'confirm_password' => $password,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'birthdate' => $birthdate,
                'company_name' => $companyName
            ]);

            if (!$result['success']) {
                $response = $result;
                break;
            }

            $stmt = $pdo->prepare("SELECT id, email, phone, COALESCE(user_code, '') AS user_code FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$result['user_id']]);
            $user = $stmt->fetch() ?: [];

            log_action(
                $_SESSION['user_id'],
                'ADMIN_CREATE_CLIENT',
                'Cliente registrado por admin: ' . ($user['email'] ?? $email),
                getTrusSIDBug()
            );

            $response = [
                'success' => true,
                'message' => 'Cliente registrado correctamente',
                'client' => [
                    'id' => $result['user_id'],
                    'email' => $user['email'] ?? $email,
                    'phone' => $user['phone'] ?? $phone,
                    'user_code' => $user['user_code'] ?? ($result['user_code'] ?? '')
                ]
            ];
            break;

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
            break;
    }
} catch (Exception $e) {
    error_log('admin_clients API Error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
