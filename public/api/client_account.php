<?php
require_once '../../config/config.php';
require_login();

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'summary';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$response = [];
$user_id = (int)$_SESSION['user_id'];

try {
    // Ensure tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_credit_balance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL UNIQUE,
        credit_limit DECIMAL(10, 2) DEFAULT 0,
        credit_available DECIMAL(10, 2) DEFAULT 0,
        credit_used DECIMAL(10, 2) DEFAULT 0,
        total_owed DECIMAL(10, 2) DEFAULT 0,
        last_payment_date DATE,
        days_overdue INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS weekly_consumption_summary (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        week_start DATE NOT NULL,
        week_end DATE NOT NULL,
        total_consumed DECIMAL(10, 2) DEFAULT 0,
        total_owed DECIMAL(10, 2) DEFAULT 0,
        payment_status ENUM('pending', 'partial', 'paid') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS credit_payments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        order_id INT,
        payment_amount DECIMAL(10, 2) NOT NULL,
        payment_date DATE NOT NULL,
        payment_method VARCHAR(50),
        reference_number VARCHAR(100),
        notes TEXT,
        recorded_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (order_id) REFERENCES orders(id),
        FOREIGN KEY (recorded_by) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS whatsapp_quotes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        quote_data JSON NOT NULL,
        total_amount DECIMAL(10, 2) NOT NULL,
        items_count INT NOT NULL,
        whatsapp_phone VARCHAR(20),
        status ENUM('pending', 'sent', 'answered', 'converted_to_order') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
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
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(total), 0) as total_consumed,
                        COALESCE(SUM(CASE WHEN payment_status IN ('pending','partial') THEN (total - COALESCE(balance, 0)) ELSE 0 END), 0) as total_owed
                    FROM orders
                    WHERE user_id = ? AND DATE(created_at) >= ? AND DATE(created_at) <= ?
                ");
                $stmt->execute([$user_id, $weekStart, $weekEnd]);
                $calc = $stmt->fetch();

                // Insert or update
                $stmt = $pdo->prepare("
                    INSERT INTO weekly_consumption_summary (user_id, week_start, week_end, total_consumed, total_owed)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE total_consumed = ?, total_owed = ?, updated_at = NOW()
                ");
                $stmt->execute([
                    $user_id, $weekStart, $weekEnd,
                    $calc['total_consumed'], $calc['total_owed'],
                    $calc['total_consumed'], $calc['total_owed']
                ]);
                $weekly = $calc;
                $weekly['week_start'] = $weekStart;
                $weekly['week_end'] = $weekEnd;
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
            $stmt = $pdo->prepare("UPDATE client_credit_balance SET credit_used = credit_used - ?, last_payment_date = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$payment_amount, $payment_date, $target_user_id]);

            // If related to specific order, update order balance
            if ($order_id) {
                $stmt = $pdo->prepare("UPDATE orders SET balance = balance - ?, payment_status = CASE WHEN balance - ? <= 0 THEN 'paid' ELSE 'partial' END WHERE id = ?");
                $stmt->execute([$payment_amount, $payment_amount, $order_id]);
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
            $total_amount = (float)($input['total'] ?? 0);
            $whatsapp_phone = sanitize($input['whatsapp_phone'] ?? '');

            if (empty($items) || $total_amount <= 0) {
                $response = ['success' => false, 'message' => 'Carrito vacio o total invalido'];
                break;
            }

            // Save quote
            $stmt = $pdo->prepare("
                INSERT INTO whatsapp_quotes (user_id, quote_data, total_amount, items_count, whatsapp_phone, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $user_id,
                json_encode($items, JSON_UNESCAPED_UNICODE),
                $total_amount,
                count($items),
                $whatsapp_phone
            ]);
            $quote_id = (int)$pdo->lastInsertId();

            // Generate WhatsApp message
            $message = "Solicito cotización:\n";
            foreach ($items as $item) {
                $qty = (int)($item['quantity'] ?? 0);
                $name = htmlspecialchars($item['name'] ?? '', ENT_QUOTES);
                $message .= "• $qty x $name\n";
            }
            $message .= "\nTotal estimado: \$$total_amount";

            $encoded_msg = urlencode($message);
            $whatsapp_url = "https://wa.me/521234567890?text=$encoded_msg";

            $response = [
                'success' => true,
                'quote_id' => $quote_id,
                'whatsapp_url' => $whatsapp_url,
                'message' => 'Cotización creada'
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

        default:
            $response = ['success' => false, 'message' => 'Accion no reconocida'];
    }

} catch (Exception $e) {
    error_log('Client account API error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
