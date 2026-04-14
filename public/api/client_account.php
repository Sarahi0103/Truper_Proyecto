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
    // Ensure tables exist (PostgreSQL compatible)
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_credit_balance (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
        credit_limit DECIMAL(10, 2) DEFAULT 0,
        credit_available DECIMAL(10, 2) DEFAULT 0,
        credit_used DECIMAL(10, 2) DEFAULT 0,
        total_owed DECIMAL(10, 2) DEFAULT 0,
        last_payment_date DATE,
        days_overdue INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS weekly_consumption_summary (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        week_start DATE NOT NULL,
        week_end DATE NOT NULL,
        total_consumed DECIMAL(10, 2) DEFAULT 0,
        total_owed DECIMAL(10, 2) DEFAULT 0,
        payment_status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (user_id, week_start),
        CHECK (payment_status IN ('pending', 'partial', 'paid'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS credit_payments (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
        payment_amount DECIMAL(10, 2) NOT NULL,
        payment_date DATE NOT NULL,
        payment_method VARCHAR(50),
        reference_number VARCHAR(100),
        notes TEXT,
        recorded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS whatsapp_quotes (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        quote_data JSONB NOT NULL,
        total_amount DECIMAL(10, 2) NOT NULL,
        items_count INTEGER NOT NULL,
        whatsapp_phone VARCHAR(20),
        status VARCHAR(30) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CHECK (status IN ('pending', 'sent', 'answered', 'converted_to_order'))
    )");

    switch ($action) {
        case 'credit-summary':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $stmt = $pdo->prepare("SELECT * FROM client_credit_balance WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $credit = $stmt->fetch();

            if (!$credit) {
                $stmt = $pdo->prepare("INSERT INTO client_credit_balance (user_id, credit_limit, credit_available, credit_used, total_owed, updated_at) VALUES (?, 0, 0, 0, 0, CURRENT_TIMESTAMP)");
                $stmt->execute([$user_id]);
                $credit = ['credit_limit' => 0, 'credit_available' => 0, 'total_owed' => 0, 'days_overdue' => 0];
            }

            $response = ['success' => true, 'credit' => $credit];
            break;

        case 'weekly-summary':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));

            $stmt = $pdo->prepare("SELECT * FROM weekly_consumption_summary WHERE user_id = ? AND week_start = ?");
            $stmt->execute([$user_id, $weekStart]);
            $weekly = $stmt->fetch();

            if (!$weekly) {
                $stmt = $pdo->prepare("
                    SELECT
                        COALESCE(SUM(o.total_amount), 0) AS total_consumed,
                        COALESCE(SUM(CASE WHEN o.payment_status IN ('pending','partial') THEN COALESCE(o.balance, 0) ELSE 0 END), 0) AS total_owed
                    FROM orders o
                    INNER JOIN clients c ON c.id = o.client_id
                    WHERE c.user_id = ?
                      AND o.created_at::date >= ?
                      AND o.created_at::date <= ?
                ");
                $stmt->execute([$user_id, $weekStart, $weekEnd]);
                $calc = $stmt->fetch();

                $stmt = $pdo->prepare("
                    INSERT INTO weekly_consumption_summary (user_id, week_start, week_end, total_consumed, total_owed)
                    VALUES (?, ?, ?, ?, ?)
                    ON CONFLICT (user_id, week_start)
                    DO UPDATE SET
                        week_end = EXCLUDED.week_end,
                        total_consumed = EXCLUDED.total_consumed,
                        total_owed = EXCLUDED.total_owed,
                        updated_at = CURRENT_TIMESTAMP
                ");
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

            $stmt = $pdo->prepare("INSERT INTO client_credit_balance (user_id, credit_limit, credit_available, credit_used, total_owed, last_payment_date, updated_at) VALUES (?, 0, 0, 0, 0, ?, CURRENT_TIMESTAMP) ON CONFLICT (user_id) DO NOTHING");
            $stmt->execute([$target_user_id, $payment_date]);

            $stmt = $pdo->prepare("UPDATE client_credit_balance SET credit_used = GREATEST(COALESCE(credit_used, 0) - ?, 0), total_owed = GREATEST(COALESCE(total_owed, 0) - ?, 0), last_payment_date = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
            $stmt->execute([$payment_amount, $payment_amount, $payment_date, $target_user_id]);

            if ($order_id) {
                $stmt = $pdo->prepare("UPDATE orders SET payment_amount = COALESCE(payment_amount, 0) + ?, balance = GREATEST(COALESCE(balance, 0) - ?, 0), payment_status = CASE WHEN COALESCE(balance, 0) - ? <= 0 THEN 'paid' ELSE 'partial' END, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$payment_amount, $payment_amount, $payment_amount, $order_id]);
            }

            $response = ['success' => true, 'message' => 'Pago registrado correctamente'];
            break;

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
?>