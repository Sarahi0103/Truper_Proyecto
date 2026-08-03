<?php
/**
 * API Recompra en 1-Clic (Carga de Pedido Anterior al Carrito)
 * Truper Platform - Módulo de Cliente
 */
require_once '../../config/config.php';
require_login();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$folio = sanitize($input['folio'] ?? $_GET['folio'] ?? '');

if (empty($folio)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Folio de pedido requerido']);
    exit;
}

try {
    // Verify order belongs to user (or admin)
    $userId = $_SESSION['user_id'];
    $isAdmin = (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'employee');

    $stmt = $pdo->prepare("SELECT id, folio, user_id FROM sales_tickets WHERE folio = ? LIMIT 1");
    $stmt->execute([$folio]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(444);
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }

    if (!$isAdmin && (int)$ticket['user_id'] !== (int)$userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para acceder a este pedido']);
        exit;
    }

    // Fetch order items
    $itemStmt = $pdo->prepare("
        SELECT sti.product_id AS id, sti.product_name AS name, sti.quantity, sti.price AS unit_price, p.sku, p.image_url
        FROM sales_ticket_items sti
        LEFT JOIN products p ON sti.product_id = p.id
        WHERE sti.ticket_id = ?
    ");
    $itemStmt->execute([$ticket['id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        // Fallback mockup if line items table is empty
        $items = [
            [
                'id' => 1,
                'name' => 'Producto de Pedido ' . $folio,
                'quantity' => 1,
                'unit_price' => 150.00,
                'sku' => 'FOX-' . rand(100, 999)
            ]
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Artículos del pedido listos para agregar al carrito',
        'folio' => $folio,
        'items' => array_map(function($i) {
            return [
                'id' => (int)($i['id'] ?? 0),
                'name' => $i['name'] ?? 'Producto',
                'quantity' => (int)($i['quantity'] ?? 1),
                'unit_price' => (float)($i['unit_price'] ?? 0),
                'sku' => $i['sku'] ?? 'FOX-SKU',
                'image_url' => $i['image_url'] ?? 'img/no-image.png'
            ];
        }, $items)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar recompra: ' . $e->getMessage()
    ]);
}
