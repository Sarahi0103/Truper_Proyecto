<?php
require_once '../../config/config.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'status';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$response = [];

function table_exists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT to_regclass(?) AS reg");
    $stmt->execute([$tableName]);
    $row = $stmt->fetch();
    return !empty($row['reg']);
}

function column_exists(PDO $pdo, string $tableName, string $columnName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$tableName, $columnName]);
    return (bool)$stmt->fetchColumn();
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cash_drawer_sessions (
        id SERIAL PRIMARY KEY,
        opened_by INTEGER NOT NULL REFERENCES users(id),
        opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        opening_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        closed_by INTEGER REFERENCES users(id),
        closed_at TIMESTAMP,
        closing_amount DECIMAL(12,2),
        expected_amount DECIMAL(12,2),
        difference_amount DECIMAL(12,2),
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        notes TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cash_drawer_movements (
        id SERIAL PRIMARY KEY,
        session_id INTEGER NOT NULL REFERENCES cash_drawer_sessions(id) ON DELETE CASCADE,
        movement_type VARCHAR(20) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        description TEXT,
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    switch ($action) {
        case 'open':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $stmt = $pdo->prepare("SELECT id FROM cash_drawer_sessions WHERE status='open' LIMIT 1");
            $stmt->execute();
            if ($stmt->fetch()) {
                $response = ['success' => false, 'message' => 'Ya existe una caja abierta'];
                break;
            }

            $opening = (float)($input['opening_amount'] ?? 0);
            $stmt = $pdo->prepare("INSERT INTO cash_drawer_sessions (opened_by, opening_amount, status) VALUES (?, ?, 'open') RETURNING id");
            $stmt->execute([$_SESSION['user_id'], $opening]);
            $row = $stmt->fetch();
            $response = ['success' => true, 'session_id' => $row['id'], 'message' => 'Caja abierta'];
            break;

        case 'movement':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $stmt = $pdo->prepare("SELECT id FROM cash_drawer_sessions WHERE status='open' ORDER BY opened_at DESC LIMIT 1");
            $stmt->execute();
            $session = $stmt->fetch();
            if (!$session) {
                $response = ['success' => false, 'message' => 'No hay caja abierta'];
                break;
            }

            $stmt = $pdo->prepare("INSERT INTO cash_drawer_movements (session_id, movement_type, amount, description, created_by)
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $session['id'],
                sanitize($input['movement_type'] ?? 'in'),
                (float)($input['amount'] ?? 0),
                sanitize($input['description'] ?? ''),
                $_SESSION['user_id']
            ]);
            $response = ['success' => true, 'message' => 'Movimiento registrado'];
            break;

        case 'close':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $stmt = $pdo->prepare("SELECT * FROM cash_drawer_sessions WHERE status='open' ORDER BY opened_at DESC LIMIT 1");
            $stmt->execute();
            $session = $stmt->fetch();
            if (!$session) {
                $response = ['success' => false, 'message' => 'No hay caja abierta'];
                break;
            }

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN movement_type IN ('in','sale') THEN amount ELSE -amount END),0) total
                                   FROM cash_drawer_movements WHERE session_id = ?");
            $stmt->execute([$session['id']]);
            $calc = $stmt->fetch();

            $expected = (float)$session['opening_amount'] + (float)$calc['total'];
            $closing = (float)($input['closing_amount'] ?? 0);
            $difference = $closing - $expected;

            $stmt = $pdo->prepare("UPDATE cash_drawer_sessions SET closed_by=?, closed_at=NOW(), closing_amount=?, expected_amount=?, difference_amount=?, status='closed', notes=? WHERE id=?");
            $stmt->execute([$_SESSION['user_id'], $closing, $expected, $difference, sanitize($input['notes'] ?? ''), $session['id']]);
            $response = ['success' => true, 'message' => 'Caja cerrada', 'expected_amount' => $expected, 'difference_amount' => $difference];
            break;

        case 'summary':
            require_admin();
            $stmt = $pdo->prepare("SELECT * FROM cash_drawer_sessions WHERE status='open' ORDER BY opened_at DESC LIMIT 1");
            $stmt->execute();
            $open = $stmt->fetch();

            $movementNet = 0.0;
            if ($open) {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN movement_type IN ('in','sale') THEN amount ELSE -amount END),0) total FROM cash_drawer_movements WHERE session_id = ?");
                $stmt->execute([$open['id']]);
                $movementNet = (float)($stmt->fetch()['total'] ?? 0);
            }

            $salesToday = 0.0;
            $pendingCollections = 0.0;
            if (table_exists($pdo, 'public.orders')) {
                $ordersTotalColumn = column_exists($pdo, 'orders', 'total_amount') ? 'total_amount' : 'total';
                $stmt = $pdo->query("SELECT COALESCE(SUM($ordersTotalColumn),0) AS total FROM orders WHERE DATE(created_at) = CURRENT_DATE");
                $salesToday = (float)($stmt->fetch()['total'] ?? 0);

                if (column_exists($pdo, 'orders', 'payment_status')) {
                    $stmt = $pdo->query("SELECT COALESCE(SUM($ordersTotalColumn),0) AS total FROM orders WHERE payment_status IN ('pending','partial')");
                    $pendingCollections = (float)($stmt->fetch()['total'] ?? 0);
                }
            }

            $pendingSupplierPayments = 0.0;
            if (table_exists($pdo, 'public.supplier_orders')) {
                $stmt = $pdo->query("SELECT COALESCE(SUM(total_estimated),0) AS total FROM supplier_orders WHERE status IN ('pending','created')");
                $pendingSupplierPayments = (float)($stmt->fetch()['total'] ?? 0);
            }

            $cashExpected = $open ? (float)$open['opening_amount'] + $movementNet : 0.0;
            $realProfit = $salesToday - $pendingSupplierPayments;
            $profitMarginPct = $salesToday > 0 ? ($realProfit / $salesToday) * 100 : 0;

            $response = [
                'success' => true,
                'open_session' => $open ?: null,
                'summary' => [
                    'movement_net' => $movementNet,
                    'cash_expected' => $cashExpected,
                    'sales_today' => $salesToday,
                    'pending_collections' => $pendingCollections,
                    'pending_supplier_payments' => $pendingSupplierPayments,
                    'real_profit' => $realProfit,
                    'profit_margin_pct' => $profitMarginPct
                ]
            ];
            break;

        case 'status':
        default:
            $stmt = $pdo->prepare("SELECT * FROM cash_drawer_sessions WHERE status='open' ORDER BY opened_at DESC LIMIT 1");
            $stmt->execute();
            $open = $stmt->fetch();
            $response = ['success' => true, 'open_session' => $open ?: null];
    }
} catch (Exception $e) {
    error_log('Cashier API error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
