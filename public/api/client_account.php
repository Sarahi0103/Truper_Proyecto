<?php
require_once '../../config/config.php';
require_once '../../src/controllers/OrderController.php';
require_login();

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'summary';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$response = [];
$user_id = (int)$_SESSION['user_id'];

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_csrf_token();
}

function ensure_client_profile_id_for_user($pdo, int $userId): int {
    if ($userId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $existingId = (int)$stmt->fetchColumn();
    if ($existingId > 0) {
        return $existingId;
    }

    $company = 'Cliente';

    try {
        $userStmt = $pdo->prepare("SELECT COALESCE(name, '') AS full_name, COALESCE(first_name, '') AS first_name, COALESCE(last_name, '') AS last_name FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        $fullName = trim((string)($user['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
        }

        if ($fullName !== '') {
            $company = $fullName;
        }
    } catch (Exception $ignored) {
        $company = 'Cliente';
    }

    try {
        $insert = $pdo->prepare("INSERT INTO clients (user_id, company_name) VALUES (?, ?) ON CONFLICT (user_id) DO NOTHING");
        $insert->execute([$userId, $company]);
    } catch (Exception $ignored) {
        try {
            $insert = $pdo->prepare("INSERT INTO clients (user_id, company_name) VALUES (?, ?)");
            $insert->execute([$userId, $company]);
        } catch (Exception $ignoredAgain) {
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function client_account_json_response(array $payload): void {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        http_response_code(500);
        echo '{"success":false,"message":"Error serializando respuesta JSON"}';
        return;
    }
    echo $json;
}

function normalize_quote_product_code($value): string {
    $code = trim((string)$value);
    if ($code === '') {
        return '';
    }
    return preg_replace('/^XLS-/i', '', $code);
}

function quote_item_code(array $item, array $skuByProductId = []): string {
    $candidates = [
        $item['sku'] ?? '',
        $item['code'] ?? '',
        $item['product_code'] ?? ''
    ];

    foreach ($candidates as $candidate) {
        $normalized = normalize_quote_product_code($candidate);
        if ($normalized !== '') {
            return $normalized;
        }
    }

    $productId = (int)($item['product_id'] ?? 0);
    if ($productId > 0 && isset($skuByProductId[$productId])) {
        $normalized = normalize_quote_product_code($skuByProductId[$productId]);
        if ($normalized !== '') {
            return $normalized;
        }
    }

    return $productId > 0 ? ('ID-' . $productId) : 'N/A';
}

try {
    // Ensure tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_credit_balance (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL UNIQUE,
        credit_limit DECIMAL(10, 2) DEFAULT 0,
        credit_available DECIMAL(10, 2) DEFAULT 0,
        credit_used DECIMAL(10, 2) DEFAULT 0,
        total_owed DECIMAL(10, 2) DEFAULT 0,
        last_payment_date DATE,
        days_overdue INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS weekly_consumption_summary (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        week_start DATE NOT NULL,
        week_end DATE NOT NULL,
        total_consumed DECIMAL(10, 2) DEFAULT 0,
        total_owed DECIMAL(10, 2) DEFAULT 0,
        payment_status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE (user_id, week_start),
        CHECK (payment_status IN ('pending', 'partial', 'paid'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS credit_payments (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        order_id INTEGER,
        payment_amount DECIMAL(10, 2) NOT NULL,
        payment_date DATE NOT NULL,
        payment_method VARCHAR(50),
        reference_number VARCHAR(100),
        notes TEXT,
        recorded_by INTEGER,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
        FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS whatsapp_quotes (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        quote_data JSONB NOT NULL,
        total_amount DECIMAL(10, 2) NOT NULL,
        items_count INTEGER NOT NULL,
        whatsapp_phone VARCHAR(20),
        status VARCHAR(30) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CHECK (status IN ('pending', 'sent', 'answered', 'converted_to_order'))
    )");

    switch ($action) {
        // Resumen de cuenta crediticia del cliente
        case 'credit-summary':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $stmt = $pdo->prepare("SELECT * FROM client_credit_balance WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $credit = $stmt->fetch();

            if (!$credit) {
                // Create default if not exists
                $stmt = $pdo->prepare("INSERT INTO client_credit_balance (user_id, credit_limit, credit_available) VALUES (?, 0, 0)");
                $stmt->execute([$user_id]);
                $credit = ['credit_limit' => 0, 'credit_available' => 0, 'total_owed' => 0, 'days_overdue' => 0];
            }

            $response = ['success' => true, 'credit' => $credit];
            break;

        // Control semanal de consumido y adeudado
        case 'weekly-summary':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            // Get current week start (Monday)
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));

            // Get or create weekly summary
            $stmt = $pdo->prepare("SELECT * FROM weekly_consumption_summary WHERE user_id = ? AND week_start = ?");
            $stmt->execute([$user_id, $weekStart]);
            $weekly = $stmt->fetch();

            if (!$weekly) {
                // Calculate from orders in this week
                                $stmt = $pdo->prepare("SELECT COALESCE(SUM(o.total_amount), 0) AS total_consumed, COALESCE(SUM(CASE WHEN o.payment_status IN ('pending','partial') THEN COALESCE(o.balance, 0) ELSE 0 END), 0) AS total_owed FROM orders o INNER JOIN clients c ON c.id = o.client_id WHERE c.user_id = ? AND o.created_at::date >= ? AND o.created_at::date <= ?");
                $stmt->execute([$user_id, $weekStart, $weekEnd]);
                $calc = $stmt->fetch();

                $stmt = $pdo->prepare("INSERT INTO weekly_consumption_summary (user_id, week_start, week_end, total_consumed, total_owed) VALUES (?, ?, ?, ?, ?) ON CONFLICT (user_id, week_start) DO UPDATE SET week_end = EXCLUDED.week_end, total_consumed = EXCLUDED.total_consumed, total_owed = EXCLUDED.total_owed, updated_at = CURRENT_TIMESTAMP");
                $stmt->execute([
                    $user_id,
                    $weekStart,
                    $weekEnd,
                    (float)($calc['total_consumed'] ?? 0),
                    (float)($calc['total_owed'] ?? 0)
                ]);
                $weekly = [
                    'total_consumed' => (float)($calc['total_consumed'] ?? 0),
                    'total_owed' => (float)($calc['total_owed'] ?? 0),
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                ];
            }

            $response = ['success' => true, 'weekly' => $weekly];
            break;

        // Historial semanal (últimas 12 semanas)
        case 'weekly-history':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $stmt = $pdo->prepare("
                SELECT id, week_start, week_end, total_consumed, total_owed, payment_status
                FROM weekly_consumption_summary
                WHERE user_id = ?
                ORDER BY week_start DESC
                LIMIT 12
            ");
            $stmt->execute([$user_id]);
            $weeks = $stmt->fetchAll();

            $response = ['success' => true, 'weeks' => $weeks];
            break;

        // Registrar pago contra crédito/deuda
        case 'record-payment':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $payment_amount = (float)($input['payment_amount'] ?? 0);
            $payment_date = $input['payment_date'] ?? date('Y-m-d');
            $payment_method = sanitize($input['payment_method'] ?? 'cash');
            $reference_number = sanitize($input['reference_number'] ?? '');
            $notes = sanitize($input['notes'] ?? '');
            $target_user_id = (int)($input['user_id'] ?? 0);
            $order_id = isset($input['order_id']) ? (int)$input['order_id'] : null;

            if ($target_user_id <= 0) {
                $response = ['success' => false, 'message' => 'Usuario destino invalido'];
                break;
            }

            if ($payment_amount <= 0) {
                $response = ['success' => false, 'message' => 'Monto debe ser mayor a 0'];
                break;
            }

            // Record payment
            $stmt = $pdo->prepare("
                INSERT INTO credit_payments (user_id, order_id, payment_amount, payment_date, payment_method, reference_number, notes, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $target_user_id,
                $order_id,
                $payment_amount,
                $payment_date,
                $payment_method,
                $reference_number,
                $notes,
                $_SESSION['user_id']
            ]);

            // Update credit balance
            $stmt = $pdo->prepare("INSERT INTO client_credit_balance (user_id, credit_limit, credit_available, credit_used, total_owed, last_payment_date, updated_at) VALUES (?, 0, 0, 0, 0, ?, CURRENT_TIMESTAMP) ON CONFLICT (user_id) DO NOTHING");
            $stmt->execute([$target_user_id, $payment_date]);

            $stmt = $pdo->prepare("UPDATE client_credit_balance SET credit_used = GREATEST(COALESCE(credit_used, 0) - ?, 0), total_owed = GREATEST(COALESCE(total_owed, 0) - ?, 0), last_payment_date = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
            $stmt->execute([$payment_amount, $payment_amount, $payment_date, $target_user_id]);

            // If related to specific order, update order balance
            if ($order_id) {
                $stmt = $pdo->prepare("UPDATE orders SET payment_amount = COALESCE(payment_amount, 0) + ?, balance = GREATEST(COALESCE(balance, 0) - ?, 0), payment_status = CASE WHEN COALESCE(balance, 0) - ? <= 0 THEN 'paid' ELSE 'partial' END, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$payment_amount, $payment_amount, $payment_amount, $order_id]);
            }

            $response = ['success' => true, 'message' => 'Pago registrado correctamente'];
            break;

        // Crear cotización para WhatsApp
        case 'whatsapp-quote':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $items = $input['items'] ?? [];
            $whatsapp_phone = sanitize($input['whatsapp_phone'] ?? '');
            $target_phone = whatsapp_phone_digits($whatsapp_phone);
            $is_wholesale = !empty($input['is_wholesale']);
            $special_event = sanitize($input['special_event'] ?? '');
            $notes = sanitize($input['notes'] ?? '');

            if (empty($items)) {
                $response = ['success' => false, 'message' => 'Carrito vacio'];
                break;
            }

            $skuByProductId = [];
            $productIds = [];
            foreach ($items as $item) {
                $pid = (int)($item['product_id'] ?? 0);
                if ($pid > 0) {
                    $productIds[$pid] = true;
                }
            }
            if (!empty($productIds)) {
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $stmtSku = $pdo->prepare("SELECT id, COALESCE(sku, '') AS sku FROM products WHERE id IN ($placeholders)");
                $stmtSku->execute(array_map('intval', array_keys($productIds)));
                foreach ($stmtSku->fetchAll() as $rowSku) {
                    $rowId = (int)($rowSku['id'] ?? 0);
                    if ($rowId > 0) {
                        $skuByProductId[$rowId] = (string)($rowSku['sku'] ?? '');
                    }
                }
            }

            $normalizedItems = [];
            $subtotal_amount = 0;
            foreach ($items as $item) {
                $qty = max(1, (int)($item['quantity'] ?? 1));
                $unitPrice = (float)($item['price'] ?? ($item['unit_price'] ?? 0));
                $code = quote_item_code((array)$item, $skuByProductId);
                $subtotal_amount += ($qty * $unitPrice);
                $normalizedItems[] = [
                    'product_id' => (int)($item['product_id'] ?? 0),
                    'name' => trim((string)($item['name'] ?? 'Producto')),
                    'quantity' => $qty,
                    'price' => $unitPrice,
                    'sku' => $code
                ];
            }

            $client_id = ensure_client_profile_id_for_user($pdo, $user_id);
            if ($client_id <= 0) {
                $response = ['success' => false, 'message' => 'No fue posible identificar al cliente'];
                break;
            }

            $orderController = new OrderController($pdo);
            $orderResponse = $orderController->createOrder(
                $client_id,
                $normalizedItems,
                $is_wholesale,
                [
                    'special_event' => $special_event !== '' ? $special_event : null,
                    'notes' => $notes !== '' ? $notes : null
                ]
            );

            if (empty($orderResponse['success'])) {
                $response = [
                    'success' => false,
                    'message' => $orderResponse['message'] ?? 'No se pudo registrar el pedido'
                ];
                break;
            }

            $order_id = (int)($orderResponse['order_id'] ?? 0);
            $ticket_code = (string)($orderResponse['order_number'] ?? ('ORD-' . $order_id));
            $issued_at = date('Y-m-d H:i');
            $client_ref = 'U' . str_pad((string)$user_id, 5, '0', STR_PAD_LEFT);
            $ticket_path = (string)($orderResponse['ticket_url'] ?? ('/ticket_client.php?id=' . $order_id));
            $ticket_url = preg_match('/^https?:\\/\\//i', $ticket_path)
                ? $ticket_path
                : app_base_url() . $ticket_path;

            if (db_column_exists('users', 'user_code')) {
                $stmtUser = $pdo->prepare("SELECT COALESCE(user_code, '') AS user_code FROM users WHERE id = ? LIMIT 1");
                $stmtUser->execute([$user_id]);
                $userRow = $stmtUser->fetch();
                if (!empty($userRow['user_code'])) {
                    $client_ref = (string)$userRow['user_code'];
                }
            }

            $stmtOrderItems = $pdo->prepare("
                SELECT
                    oi.product_id,
                    oi.quantity,
                    oi.unit_price,
                    COALESCE(oi.subtotal, oi.quantity * oi.unit_price) AS subtotal,
                    oi.line_total,
                    COALESCE(oi.discount_amount, 0) AS discount_amount,
                    p.name,
                    COALESCE(p.sku, '') AS sku
                FROM order_items oi
                INNER JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = ?
                ORDER BY oi.id ASC
            ");
            $stmtOrderItems->execute([$order_id]);
            $storedOrderItems = $stmtOrderItems->fetchAll();

            $messageItems = [];
            $subtotal_amount = 0.0;
            $preLoyaltyTotal = 0.0;
            foreach ($storedOrderItems as $storedItem) {
                $itemSubtotal = (float)($storedItem['subtotal'] ?? 0);
                $itemLineTotal = (float)($storedItem['line_total'] ?? 0);
                $subtotal_amount += $itemSubtotal;
                $preLoyaltyTotal += $itemLineTotal;
                $messageItems[] = [
                    'product_id' => (int)($storedItem['product_id'] ?? 0),
                    'name' => trim((string)($storedItem['name'] ?? 'Producto')),
                    'quantity' => (int)($storedItem['quantity'] ?? 0),
                    'price' => (float)($storedItem['unit_price'] ?? 0),
                    'subtotal' => $itemSubtotal,
                    'line_total' => $itemLineTotal,
                    'discount_amount' => (float)($storedItem['discount_amount'] ?? 0),
                    'sku' => quote_item_code((array)$storedItem, $skuByProductId)
                ];
            }

            if (empty($messageItems)) {
                $messageItems = $normalizedItems;
                $preLoyaltyTotal = (float)($orderResponse['total'] ?? 0);
            }

            $total_amount = (float)($orderResponse['total'] ?? 0);
            $loyaltyDiscountAmount = max(0, $preLoyaltyTotal - $total_amount);
            $loyaltyRate = $preLoyaltyTotal > 0 ? ($loyaltyDiscountAmount / $preLoyaltyTotal) : 0;

            $quote_id = 0;
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO whatsapp_quotes (user_id, quote_data, total_amount, items_count, whatsapp_phone, status)
                    VALUES (?, ?, ?, ?, ?, 'converted_to_order')
                ");
                $stmt->execute([
                    $user_id,
                    json_encode($messageItems, JSON_UNESCAPED_UNICODE),
                    $total_amount,
                    count($messageItems),
                    $whatsapp_phone
                ]);
                $quote_id = (int)$pdo->lastInsertId();
            } catch (Exception $quoteError) {
                error_log('Client account whatsapp quote persistence error: ' . $quoteError->getMessage());
            }

            // Generate WhatsApp message
            $message = "TRUPER - PEDIDO\n";
            $message .= "===========================\n";
            $message .= "Folio: {$ticket_code}\n";
            $message .= "Fecha: {$issued_at}\n";
            $message .= "Cliente: {$client_ref}\n";
            $message .= "---------------------------\n";
            $message .= "PRODUCTOS:\n";
            foreach ($messageItems as $idx => $item) {
                $qty = (int)($item['quantity'] ?? 0);
                $name = trim((string)($item['name'] ?? ''));
                $unit_price = (float)($item['price'] ?? ($item['unit_price'] ?? 0));
                $code = quote_item_code((array)$item);
                $line_total = (float)($item['line_total'] ?? ($qty * $unit_price));
                $message .= "- {$name}\n";
                $message .= "  Codigo: {$code}\n";
                $message .= "  {$qty} x $" . number_format($unit_price, 2) . " = $" . number_format($line_total, 2) . "\n";
                if ($idx < (count($messageItems) - 1)) {
                    $message .= "---------------------------\n";
                }
            }
            $message .= "---------------------------\n";
            if ($loyaltyDiscountAmount > 0) {
                $message .= "SUBTOTAL: $" . number_format($subtotal_amount, 2) . "\n";
                $message .= "DESC. LEALTAD (" . (int)round($loyaltyRate * 100) . "%): -$" . number_format($loyaltyDiscountAmount, 2) . "\n";
            }
            $message .= "TOTAL: $" . number_format($total_amount, 2) . "\n";
            $message .= "PDF/Ticket: {$ticket_url}\n";
            $message .= "\nQuedo atento(a) a disponibilidad y tiempo de entrega.";

            $whatsapp_url = whatsapp_url($message, $target_phone);

            $response = [
                'success' => true,
                'quote_id' => $quote_id,
                'order_id' => $order_id,
                'order_number' => $ticket_code,
                'ticket_code' => $ticket_code,
                'ticket_url' => $ticket_path,
                'subtotal' => $subtotal_amount,
                'loyalty_discount_rate' => $loyaltyRate,
                'loyalty_discount_amount' => $loyaltyDiscountAmount,
                'whatsapp_url' => $whatsapp_url,
                'whatsapp_phone' => $target_phone,
                'message' => 'Pedido registrado automaticamente y enviado por WhatsApp'
            ];
            break;

        // Listar cotizaciones pendientes
        case 'pending-quotes':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $stmt = $pdo->prepare("
                SELECT id, quote_data, total_amount, items_count, status, created_at
                FROM whatsapp_quotes
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$user_id]);
            $quotes = $stmt->fetchAll();

            $response = ['success' => true, 'quotes' => $quotes];
            break;

        case 'payment-history':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $stmt = $pdo->prepare("
                SELECT id, order_id, payment_amount, payment_date, payment_method, reference_number, notes
                FROM credit_payments
                WHERE user_id = ?
                ORDER BY payment_date DESC
                LIMIT 50
            ");
            $stmt->execute([$user_id]);
            $payments = $stmt->fetchAll();

            $response = ['success' => true, 'payments' => $payments];
            break;

        case 'history':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            // Fetch records created directly by this user
            // OR client_order records linked to an order owned by this user
            $stmt = $pdo->prepare("
                SELECT th.id, th.transaction_type, th.reference_folio, th.data_json,
                       th.created_at
                FROM transaction_history th
                WHERE th.created_by = ?
                UNION
                SELECT th.id, th.transaction_type, th.reference_folio, th.data_json,
                       th.created_at
                FROM transaction_history th
                INNER JOIN orders o ON o.order_number = th.reference_folio
                INNER JOIN clients c ON c.id = o.client_id
                WHERE c.user_id = ?
                  AND th.transaction_type = 'client_order'
                ORDER BY created_at DESC
                LIMIT 200
            ");
            $stmt->execute([$user_id, $user_id]);
            $items = $stmt->fetchAll();

            $response = ['success' => true, 'items' => $items];
            break;

        default:
            $response = ['success' => false, 'message' => 'Accion no reconocida'];
    }

} catch (Throwable $e) {
    error_log('Client account API error: ' . $e->getMessage());
    $response = [
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
}

client_account_json_response((array)$response);
