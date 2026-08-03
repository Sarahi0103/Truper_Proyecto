<?php
/**
 * API para crear un ticket de cotización de invitado desde el carrito.
 * Endpoint: /api/create_quote_ticket.php
 */

require_once '../../config/config.php';
require_once '../../backend/models/SalesTicket.php';

header('Content-Type: application/json');

// Permitir solicitudes POST con CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    require_csrf_token();
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido o expirado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$items = $input['items'] ?? [];
$total = (float)($input['total'] ?? 0);

if (empty($items)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El carrito está vacío o items inválidos']);
    exit;
}

$fulfillmentType = $input['fulfillment_type'] ?? 'delivery'; // 'delivery' or 'pickup'
$deliveryAddress = sanitize($input['delivery_address'] ?? '');
$customerName = sanitize($input['customer_name'] ?? 'Invitado (Cotización)');

$shippingJson = json_encode([
    'fulfillment_type' => $fulfillmentType,
    'address' => $deliveryAddress,
    'city' => sanitize($input['city'] ?? 'México'),
    'postalCode' => sanitize($input['postal_code'] ?? '')
]);

$orderStatus = ($fulfillmentType === 'pickup') ? 'ready_for_pickup' : 'in_preparation';
$notesText = ($fulfillmentType === 'pickup') ? 'RETIRO EN TIENDA' : ("ENVÍO A DOMICILIO: " . $deliveryAddress);

try {
    $pdo = $GLOBALS['pdo'];

    // 1. Resolver o crear el usuario de cotización de invitado
    $guestEmail = 'invitado_cotizacion@truper.com';
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $userStmt->execute([$guestEmail]);
    $existingUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        $guestUserId = (int)$existingUser['id'];
    } else {
        $dummyPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 12]);
        $insertUser = $pdo->prepare("
            INSERT INTO users (email, password_hash, first_name, last_name, role, phone, birthdate, loyalty_points, is_active, is_verified, created_at, updated_at)
            VALUES (?, ?, 'Invitado', 'Cotización', 'guest', '', '2000-01-01', 0, true, true, NOW(), NOW())
            RETURNING id
        ");
        $insertUser->execute([$guestEmail, $dummyPassword]);
        $guestUserId = (int)$insertUser->fetchColumn();
        
        $clientInsert = $pdo->prepare("INSERT INTO clients (user_id, company_name, created_at, updated_at) VALUES (?, NULL, NOW(), NOW())");
        $clientInsert->execute([$guestUserId]);
    }

    // 2. Instanciar SalesTicket y crear ticket
    $ticketModel = new SalesTicket($pdo);

    $isMarketplace = false;
    foreach ($items as $item) {
        if (strpos($item['name'] ?? '', '[CE]') !== false) {
            $isMarketplace = true;
            break;
        }
    }
    $originSource = $isMarketplace ? 'Marketplace' : 'Stock';

    $ticketData = [
        'order_id' => null,
        'user_id' => $guestUserId,
        'customer_name' => $customerName,
        'ticket_type' => 'sale',
        'description' => $originSource,
        'subtotal_amount' => $total,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => $total,
        'payment_method' => ($fulfillmentType === 'pickup') ? 'Retiro en Tienda' : 'Envío a Domicilio',
        'payment_status' => 'pending',
        'issued_by' => null,
        'notes' => $notesText,
        'items' => array_map(function($item) {
            return [
                'product_id' => !empty($item['id']) ? (int)$item['id'] : null,
                'product_name' => $item['name'] ?? 'Producto',
                'quantity' => !empty($item['quantity']) ? (int)$item['quantity'] : 1,
                'price' => !empty($item['unit_price']) ? (float)$item['unit_price'] : (!empty($item['price']) ? (float)$item['price'] : 0),
                'line_total' => (!empty($item['quantity']) ? (int)$item['quantity'] : 1) * (!empty($item['unit_price']) ? (float)$item['unit_price'] : (!empty($item['price']) ? (float)$item['price'] : 0)),
                'discount' => 0
            ];
        }, $items)
    ];

    $result = $ticketModel->createTicket($ticketData);

    if ($result['success']) {
        // Save fulfillment details in sales_tickets
        try {
            $upd = $pdo->prepare("UPDATE sales_tickets SET order_status = ?, shipping_address_json = ? WHERE folio = ?");
            $upd->execute([$orderStatus, $shippingJson, $result['folio']]);
            
            $logIns = $pdo->prepare("INSERT INTO order_tracking_history (order_folio, status, notes, changed_by) VALUES (?, ?, ?, 'Sistema')");
            $logIns->execute([$result['folio'], $orderStatus, $notesText]);
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'folio' => $result['folio'],
            'ticket_id' => $result['ticket_id'],
            'fulfillment_type' => $fulfillmentType
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Error al crear el ticket en base de datos'
        ]);
    }

} catch (Exception $e) {
    error_log('Error en create_quote_ticket.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
}
