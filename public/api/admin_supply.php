<?php
require_once '../../config/config.php';
require_admin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'stock';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

function ensure_admin_supply_tables($pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_calendar (
        id SERIAL PRIMARY KEY,
        supplier_name VARCHAR(180) NOT NULL,
        visit_datetime TIMESTAMP NOT NULL,
        notes TEXT,
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_orders (
        id SERIAL PRIMARY KEY,
        folio VARCHAR(50) UNIQUE NOT NULL,
        supplier_name VARCHAR(180) NOT NULL,
        expected_date DATE NOT NULL,
        items_json TEXT NOT NULL,
        total_estimated DECIMAL(12, 2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'pending',
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS transaction_history (
        id SERIAL PRIMARY KEY,
        transaction_type VARCHAR(40) NOT NULL,
        reference_folio VARCHAR(80) NOT NULL,
        data_json TEXT,
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

ensure_admin_supply_tables($pdo);

$response = ['success' => false, 'message' => 'Accion no reconocida'];

try {
    switch ($action) {
        case 'stock':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $stmt = $pdo->query("SELECT id, sku, name, category, stock_quantity, reorder_level, unit_price, COALESCE(image_url, 'images/products/default-product.svg') AS image_url FROM products WHERE is_active = true ORDER BY stock_quantity ASC, name ASC");
            $items = $stmt->fetchAll();
            $response = ['success' => true, 'items' => $items];
            break;

        case 'calendar-list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $stmt = $pdo->query("SELECT id, supplier_name, visit_datetime, notes FROM supplier_calendar ORDER BY visit_datetime ASC LIMIT 200");
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'calendar-create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $supplier_name = sanitize($input['supplier_name'] ?? '');
            $visit_datetime = $input['visit_datetime'] ?? '';
            $notes = sanitize($input['notes'] ?? '');
            if ($supplier_name === '' || $visit_datetime === '') {
                $response = ['success' => false, 'message' => 'Proveedor y fecha son obligatorios'];
                break;
            }
            $stmt = $pdo->prepare("INSERT INTO supplier_calendar (supplier_name, visit_datetime, notes, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$supplier_name, $visit_datetime, $notes, $_SESSION['user_id']]);
            $response = ['success' => true, 'message' => 'Visita registrada'];
            break;

        case 'supplier-order-create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $supplier_name = sanitize($input['supplier_name'] ?? '');
            $expected_date = $input['expected_date'] ?? '';
            $items = $input['items'] ?? [];
            if ($supplier_name === '' || $expected_date === '' || !is_array($items) || count($items) === 0) {
                $response = ['success' => false, 'message' => 'Datos incompletos'];
                break;
            }

            $total = 0;
            foreach ($items as $it) {
                $qty = (int)($it['quantity'] ?? 0);
                $cost = (float)($it['estimated_cost'] ?? 0);
                $total += $qty * $cost;
            }

            $folio = 'PROV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $stmt = $pdo->prepare("INSERT INTO supplier_orders (folio, supplier_name, expected_date, items_json, total_estimated, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$folio, $supplier_name, $expected_date, json_encode($items, JSON_UNESCAPED_UNICODE), $total, $_SESSION['user_id']]);
            $orderId = (int)$pdo->lastInsertId();

            $h = $pdo->prepare("INSERT INTO transaction_history (transaction_type, reference_folio, data_json, created_by) VALUES ('supplier_order', ?, ?, ?)");
            $h->execute([$folio, json_encode(['supplier_name' => $supplier_name, 'expected_date' => $expected_date, 'total' => $total], JSON_UNESCAPED_UNICODE), $_SESSION['user_id']]);

            $response = [
                'success' => true,
                'message' => 'Orden a proveedor creada',
                'folio' => $folio,
                'order_id' => $orderId,
                'ticket_url' => '/ticket_supplier.php?id=' . $orderId
            ];
            break;

        case 'supplier-order-list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $stmt = $pdo->query("SELECT id, folio, supplier_name, expected_date, total_estimated, status, created_at FROM supplier_orders ORDER BY created_at DESC LIMIT 200");
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'history':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $stmt = $pdo->query("SELECT id, transaction_type, reference_folio, data_json, created_at FROM transaction_history ORDER BY created_at DESC LIMIT 300");
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        default:
            $response = ['success' => false, 'message' => 'Accion no reconocida'];
    }
} catch (Exception $e) {
    error_log('admin_supply API error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error interno del servidor'];
}

echo json_encode($response);
