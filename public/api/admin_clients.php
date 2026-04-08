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

function numeric_client_code($value): string {
    $digits = preg_replace('/\D+/', '', (string)$value);
    return $digits !== '' ? $digits : '';
}

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
            $companyName = sanitize($input['company_name'] ?? '');
            $birthdate = $input['birthdate'] ?? null;

            if ($firstName === '' || $lastName === '' || $phone === '') {
                $response = ['success' => false, 'message' => 'Nombre, apellido y teléfono son obligatorios'];
                break;
            }

            if (empty($birthdate)) {
                $response = ['success' => false, 'message' => 'La fecha de nacimiento es obligatoria'];
                break;
            }

            if ($email === '') {
                $digits = preg_replace('/\D+/', '', $phone);
                $suffix = $digits !== '' ? substr($digits, -8) : (string)time();
                $email = 'cliente.' . $suffix . '@truper.local';
            }

            $result = $auth->register([
                'email' => $email,
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

        case 'list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $stmt = $pdo->query("SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.birthdate, u.user_code, u.is_active, c.company_name FROM users u LEFT JOIN clients c ON c.user_id = u.id WHERE u.role = 'client' ORDER BY u.created_at DESC LIMIT 200");
            $clients = $stmt ? $stmt->fetchAll() : [];

            $clients = array_map(function ($client) {
                $client['user_code'] = numeric_client_code($client['user_code'] ?? '');
                return $client;
            }, is_array($clients) ? $clients : []);

            $response = ['success' => true, 'clients' => $clients];
            break;

        case 'update':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $clientId = (int)($input['id'] ?? 0);
            $firstName = sanitize($input['first_name'] ?? '');
            $lastName = sanitize($input['last_name'] ?? '');
            $phone = sanitize($input['phone'] ?? '');
            $email = sanitize($input['email'] ?? '');
            $companyName = sanitize($input['company_name'] ?? '');
            $birthdate = $input['birthdate'] ?? null;
            $isActive = !empty($input['is_active']);

            if ($clientId <= 0) {
                $response = ['success' => false, 'message' => 'Cliente inválido'];
                break;
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'client' LIMIT 1");
            $stmt->execute([$clientId]);
            if (!$stmt->fetchColumn()) {
                $response = ['success' => false, 'message' => 'Cliente no encontrado'];
                break;
            }

            if ($firstName === '' || $lastName === '' || $phone === '') {
                $response = ['success' => false, 'message' => 'Nombre, apellido y teléfono son obligatorios'];
                break;
            }

            if ($email === '') {
                $digits = preg_replace('/\D+/', '', $phone);
                $suffix = $digits !== '' ? substr($digits, -8) : (string)$clientId;
                $email = 'cliente.' . $suffix . '@truper.local';
            }

            $userSets = [];
            $userValues = [];

            if (db_column_exists('users', 'first_name')) {
                $userSets[] = 'first_name = ?';
                $userValues[] = $firstName;
            }
            if (db_column_exists('users', 'last_name')) {
                $userSets[] = 'last_name = ?';
                $userValues[] = $lastName;
            }
            if (db_column_exists('users', 'name')) {
                $userSets[] = 'name = ?';
                $userValues[] = trim($firstName . ' ' . $lastName);
            }
            if (db_column_exists('users', 'email')) {
                $userSets[] = 'email = ?';
                $userValues[] = $email;
            }
            if (db_column_exists('users', 'phone')) {
                $userSets[] = 'phone = ?';
                $userValues[] = $phone;
            }
            if (db_column_exists('users', 'birthdate')) {
                $userSets[] = 'birthdate = ?';
                $userValues[] = $birthdate;
            } elseif (db_column_exists('users', 'birthday')) {
                $userSets[] = 'birthday = ?';
                $userValues[] = $birthdate;
            }
            if (db_column_exists('users', 'is_active')) {
                $userSets[] = 'is_active = ?';
                $userValues[] = $isActive;
            }
            if (db_column_exists('users', 'active')) {
                $userSets[] = 'active = ?';
                $userValues[] = $isActive ? 1 : 0;
            }
            if (db_column_exists('users', 'updated_at')) {
                $userSets[] = 'updated_at = CURRENT_TIMESTAMP';
            }

            if (empty($userSets)) {
                $response = ['success' => false, 'message' => 'No hay columnas para actualizar en users'];
                break;
            }

            $userValues[] = $clientId;
            $updateSql = 'UPDATE users SET ' . implode(', ', $userSets) . ' WHERE id = ?';
            $updateUser = $pdo->prepare($updateSql);
            $updateUser->execute($userValues);

            $ensureCode = new ReflectionClass($auth);
            $methodEnsure = $ensureCode->getMethod('ensureUserCodeForUser');
            $methodEnsure->setAccessible(true);
            $code = (string)$methodEnsure->invoke($auth, $clientId);

            try {
                    if (db_column_exists('clients', 'client_code')) {
                    if (db_column_exists('clients', 'updated_at')) {
                        $clientCodeStmt = $pdo->prepare("UPDATE clients SET client_code = ?, company_name = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
                    } else {
                        $clientCodeStmt = $pdo->prepare("UPDATE clients SET client_code = ?, company_name = ? WHERE user_id = ?");
                    }
                        $clientCodeStmt->execute([$code, $companyName, $clientId]);
                    } else {
                    if (db_column_exists('clients', 'updated_at')) {
                        $companyStmt = $pdo->prepare("UPDATE clients SET company_name = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
                    } else {
                        $companyStmt = $pdo->prepare("UPDATE clients SET company_name = ? WHERE user_id = ?");
                    }
                        $companyStmt->execute([$companyName, $clientId]);
                    }
                } catch (Exception $ignoredCompany) {
                }

            log_action(
                $_SESSION['user_id'],
                'ADMIN_UPDATE_CLIENT',
                'Cliente actualizado: ' . $email,
                getTrusSIDBug()
            );

            $response = [
                'success' => true,
                'message' => 'Cliente actualizado correctamente',
                'client' => [
                    'id' => $clientId,
                    'email' => $email,
                    'phone' => $phone,
                    'user_code' => $code
                ]
            ];
            break;

        case 'delete':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $clientId = (int)($input['id'] ?? 0);
            if ($clientId <= 0) {
                $response = ['success' => false, 'message' => 'Cliente inválido'];
                break;
            }

            $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ? AND role = 'client' LIMIT 1");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$client) {
                $response = ['success' => false, 'message' => 'Cliente no encontrado'];
                break;
            }

            $deleteClients = $pdo->prepare("DELETE FROM clients WHERE user_id = ?");
            $deleteClients->execute([$clientId]);

            $deleteUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $deleteUser->execute([$clientId]);

            log_action(
                $_SESSION['user_id'],
                'ADMIN_DELETE_CLIENT',
                'Cliente eliminado: ' . ($client['email'] ?? (string)$clientId),
                getTrusSIDBug()
            );

            $response = ['success' => true, 'message' => 'Cliente eliminado correctamente'];
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
