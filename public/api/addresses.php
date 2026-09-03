<?php
/**
 * API de Direcciones de Usuario
 * Maneja operaciones CRUD para direcciones múltiples del cliente
 */

require_once '../../config/config.php';
require_once '../../src/Services/UserAddressService.php';
require_once '../../src/utils/AppLogger.php';

header('Content-Type: application/json');

// Verificar sesión
require_login();

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $addressService = new UserAddressService($pdo);
    
    switch ($action) {
        case 'list':
            // Listar direcciones del usuario actual
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $addresses = $addressService->getUserAddresses($userId);
            
            echo json_encode([
                'success' => true,
                'addresses' => $addresses
            ]);
            break;
            
        case 'create':
            // Crear nueva dirección
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            require_csrf_token();
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['address_line1']) || empty($input['city']) || empty($input['state']) || empty($input['postal_code'])) {
                echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                exit;
            }
            
            $addressData = [
                'user_id' => $userId,
                'address_line1' => $input['address_line1'],
                'address_line2' => $input['address_line2'] ?? null,
                'city' => $input['city'],
                'state' => $input['state'],
                'postal_code' => $input['postal_code'],
                'country' => $input['country'] ?? 'México',
                'address_label' => $input['address_label'] ?? null,
                'is_default' => !empty($input['is_default'])
            ];
            
            $result = $addressService->createAddress($addressData);
            
            echo json_encode($result);
            break;
            
        case 'update':
            // Actualizar dirección existente
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            require_csrf_token();
            
            $input = json_decode(file_get_contents('php://input'), true);
            $addressId = $input['address_id'] ?? 0;
            
            if (!$addressId) {
                echo json_encode(['success' => false, 'message' => 'ID de dirección requerido']);
                exit;
            }
            
            // Verificar que la dirección pertenece al usuario
            $checkStmt = $pdo->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
            $checkStmt->execute([$addressId, $userId]);
            if (!$checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Dirección no encontrada o no pertenece al usuario']);
                exit;
            }
            
            $updateData = [];
            if (isset($input['address_line1'])) $updateData['address_line1'] = $input['address_line1'];
            if (isset($input['address_line2'])) $updateData['address_line2'] = $input['address_line2'];
            if (isset($input['city'])) $updateData['city'] = $input['city'];
            if (isset($input['state'])) $updateData['state'] = $input['state'];
            if (isset($input['postal_code'])) $updateData['postal_code'] = $input['postal_code'];
            if (isset($input['address_label'])) $updateData['address_label'] = $input['address_label'];
            if (isset($input['is_default'])) $updateData['is_default'] = $input['is_default'];
            
            $result = $addressService->updateAddress($addressId, $updateData);
            
            echo json_encode($result);
            break;
            
        case 'delete':
            // Eliminar dirección (desactivar)
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            require_csrf_token();
            
            $input = json_decode(file_get_contents('php://input'), true);
            $addressId = $input['address_id'] ?? 0;
            
            if (!$addressId) {
                echo json_encode(['success' => false, 'message' => 'ID de dirección requerido']);
                exit;
            }
            
            // Verificar que la dirección pertenece al usuario
            $checkStmt = $pdo->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
            $checkStmt->execute([$addressId, $userId]);
            if (!$checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Dirección no encontrada o no pertenece al usuario']);
                exit;
            }
            
            $result = $addressService->deleteAddress($addressId);
            
            echo json_encode($result);
            break;
            
        case 'set_default':
            // Establecer dirección como predeterminada
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            require_csrf_token();
            
            $input = json_decode(file_get_contents('php://input'), true);
            $addressId = $input['address_id'] ?? 0;
            
            if (!$addressId) {
                echo json_encode(['success' => false, 'message' => 'ID de dirección requerido']);
                exit;
            }
            
            // Verificar que la dirección pertenece al usuario
            $checkStmt = $pdo->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
            $checkStmt->execute([$addressId, $userId]);
            if (!$checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Dirección no encontrada o no pertenece al usuario']);
                exit;
            }
            
            $result = $addressService->setDefaultAddress($userId, $addressId);
            
            echo json_encode($result);
            break;
            
        case 'get_default':
            // Obtener dirección predeterminada
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $defaultAddress = $addressService->getDefaultAddress($userId);
            
            echo json_encode([
                'success' => true,
                'address' => $defaultAddress
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    AppLogger::error("Addresses API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
