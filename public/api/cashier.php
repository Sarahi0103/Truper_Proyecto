<?php
require_once '../../config/config.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'status';
$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$decodedInput = json_decode($rawInput, true);
$input = is_array($decodedInput) ? $decodedInput : (is_array($_POST) ? $_POST : []);

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_csrf_token();
}

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

    $pdo->exec("CREATE TABLE IF NOT EXISTS cash_monthly_goals (
        id SERIAL PRIMARY KEY,
        month_key VARCHAR(7) NOT NULL UNIQUE,
        target_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cash_control_notes (
        id SERIAL PRIMARY KEY,
        note_folio VARCHAR(60) NOT NULL UNIQUE,
        note_type VARCHAR(20) NOT NULL DEFAULT 'customer',
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
        payment_term VARCHAR(20) NOT NULL DEFAULT 'contado',
        due_date DATE,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        reference_ticket VARCHAR(80),
        description TEXT,
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cash_note_payments (
        id SERIAL PRIMARY KEY,
        note_id INTEGER NOT NULL REFERENCES cash_control_notes(id) ON DELETE CASCADE,
        amount DECIMAL(12,2) NOT NULL,
        payment_method VARCHAR(40) NOT NULL DEFAULT 'cash',
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
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

            $pendingNotes = 0.0;
            $overdueNotes = 0.0;
            if (table_exists($pdo, 'public.cash_control_notes')) {
                $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount - amount_paid), 0) AS total FROM cash_control_notes WHERE status IN ('pending','partial')");
                $pendingNotes = (float)($stmt->fetch()['total'] ?? 0);

                $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount - amount_paid), 0) AS total FROM cash_control_notes WHERE status IN ('pending','partial','overdue') AND due_date IS NOT NULL AND due_date < CURRENT_DATE");
                $overdueNotes = (float)($stmt->fetch()['total'] ?? 0);
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
                    'pending_notes' => $pendingNotes,
                    'overdue_notes' => $overdueNotes,
                    'real_profit' => $realProfit,
                    'profit_margin_pct' => $profitMarginPct
                ]
            ];
            break;

        case 'goal-save':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $monthKey = preg_match('/^\d{4}-\d{2}$/', (string)($input['month_key'] ?? ''))
                ? (string)$input['month_key']
                : date('Y-m');
            $targetAmount = (float)($input['target_amount'] ?? 0);

            if ($targetAmount < 0) {
                $response = ['success' => false, 'message' => 'Meta inválida'];
                break;
            }

            $stmt = $pdo->prepare("INSERT INTO cash_monthly_goals (month_key, target_amount, created_by, updated_at)
                                   VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                                   ON CONFLICT (month_key)
                                   DO UPDATE SET target_amount = EXCLUDED.target_amount, updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$monthKey, $targetAmount, $_SESSION['user_id']]);
            $response = ['success' => true, 'message' => 'Meta mensual guardada'];
            break;

        case 'goal-summary':
            require_admin();
            $monthKey = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month_key'] ?? ''))
                ? (string)$_GET['month_key']
                : date('Y-m');
            $monthStart = $monthKey . '-01';
            $monthEnd = date('Y-m-t', strtotime($monthStart));

            $targetAmount = 0.0;
            $stmt = $pdo->prepare("SELECT target_amount FROM cash_monthly_goals WHERE month_key = ? LIMIT 1");
            $stmt->execute([$monthKey]);
            $rowGoal = $stmt->fetch();
            if ($rowGoal) {
                $targetAmount = (float)$rowGoal['target_amount'];
            }

            $ordersTotalColumn = column_exists($pdo, 'orders', 'total_amount') ? 'total_amount' : 'total';
            $achieved = 0.0;
            if (table_exists($pdo, 'public.orders')) {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM($ordersTotalColumn),0) AS total FROM orders WHERE DATE(created_at) BETWEEN ? AND ?");
                $stmt->execute([$monthStart, $monthEnd]);
                $achieved = (float)($stmt->fetch()['total'] ?? 0);
            }

            $remaining = max(0, $targetAmount - $achieved);

            $weeklyRows = [];
            if (table_exists($pdo, 'public.orders')) {
                $stmt = $pdo->prepare("SELECT DATE_TRUNC('week', created_at)::date AS week_start, COALESCE(SUM($ordersTotalColumn),0) AS week_total
                                       FROM orders
                                       WHERE DATE(created_at) BETWEEN ? AND ?
                                       GROUP BY 1 ORDER BY 1 ASC");
                $stmt->execute([$monthStart, $monthEnd]);
                $weeklyRows = $stmt->fetchAll();
            }

            $weekCount = max(4, count($weeklyRows));
            $weeklyTarget = $weekCount > 0 ? ($targetAmount / $weekCount) : 0;

            $response = [
                'success' => true,
                'month_key' => $monthKey,
                'goal' => [
                    'target_amount' => $targetAmount,
                    'achieved_amount' => $achieved,
                    'remaining_amount' => $remaining,
                    'progress_pct' => $targetAmount > 0 ? (($achieved / $targetAmount) * 100) : 0
                ],
                'weekly' => array_map(function ($w) use ($weeklyTarget) {
                    return [
                        'week_start' => $w['week_start'],
                        'week_total' => (float)$w['week_total'],
                        'week_target' => (float)$weeklyTarget
                    ];
                }, $weeklyRows)
            ];
            break;

        case 'note-create':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $noteType = sanitize($input['note_type'] ?? 'customer');
            $totalAmount = (float)($input['total_amount'] ?? 0);
            $paymentTerm = sanitize($input['payment_term'] ?? 'contado');
            $referenceTicket = sanitize($input['reference_ticket'] ?? '');
            $description = sanitize($input['description'] ?? '');

            if (!in_array($noteType, ['customer', 'supplier'], true)) {
                $noteType = 'customer';
            }
            if (!in_array($paymentTerm, ['contado', '15dias', '30dias'], true)) {
                $paymentTerm = 'contado';
            }
            if ($totalAmount <= 0) {
                $response = ['success' => false, 'message' => 'Monto inválido'];
                break;
            }

            $folioPrefix = $noteType === 'supplier' ? 'PROV' : 'CLI';
            $noteFolio = $folioPrefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $dueDate = null;
            if ($paymentTerm === '15dias') {
                $dueDate = date('Y-m-d', strtotime('+15 days'));
            } elseif ($paymentTerm === '30dias') {
                $dueDate = date('Y-m-d', strtotime('+30 days'));
            } else {
                $dueDate = date('Y-m-d');
            }

            $status = $paymentTerm === 'contado' ? 'pending' : 'pending';

            $stmt = $pdo->prepare("INSERT INTO cash_control_notes (note_folio, note_type, total_amount, amount_paid, payment_term, due_date, status, reference_ticket, description, created_by)
                                   VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$noteFolio, $noteType, $totalAmount, $paymentTerm, $dueDate, $status, $referenceTicket, $description, $_SESSION['user_id']]);

            $response = ['success' => true, 'message' => 'Nota registrada', 'note_folio' => $noteFolio];
            break;

        case 'notes-list':
            require_admin();
            $statusFilter = sanitize($_GET['status'] ?? '');
            $params = [];
            $where = '';
            if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'partial', 'paid', 'overdue'], true)) {
                $where = ' WHERE status = ?';
                $params[] = $statusFilter;
            }

            $sql = "SELECT id, note_folio, note_type, total_amount, amount_paid, payment_term, due_date, status, reference_ticket, description, created_at
                    FROM cash_control_notes" . $where . " ORDER BY created_at DESC LIMIT 300";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'note-payment':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $noteId = (int)($input['note_id'] ?? 0);
            $amount = (float)($input['amount'] ?? 0);
            $paymentMethod = sanitize($input['payment_method'] ?? 'cash');
            $paymentNotes = sanitize($input['notes'] ?? '');

            if ($noteId <= 0 || $amount <= 0) {
                $response = ['success' => false, 'message' => 'Pago inválido'];
                break;
            }

            $stmt = $pdo->prepare("SELECT id, total_amount, amount_paid, due_date FROM cash_control_notes WHERE id = ? LIMIT 1");
            $stmt->execute([$noteId]);
            $note = $stmt->fetch();
            if (!$note) {
                $response = ['success' => false, 'message' => 'Nota no encontrada'];
                break;
            }

            $newPaid = (float)$note['amount_paid'] + $amount;
            $total = (float)$note['total_amount'];
            if ($newPaid > $total) {
                $newPaid = $total;
            }

            $newStatus = 'partial';
            if ($newPaid >= $total) {
                $newStatus = 'paid';
            } elseif (!empty($note['due_date']) && strtotime((string)$note['due_date']) < strtotime(date('Y-m-d'))) {
                $newStatus = 'overdue';
            }

            $stmt = $pdo->prepare("INSERT INTO cash_note_payments (note_id, amount, payment_method, notes, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$noteId, $amount, $paymentMethod, $paymentNotes, $_SESSION['user_id']]);

            $stmt = $pdo->prepare("UPDATE cash_control_notes SET amount_paid = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newPaid, $newStatus, $noteId]);

            $response = ['success' => true, 'message' => 'Pago registrado', 'remaining' => max(0, $total - $newPaid), 'status' => $newStatus];
            break;

        case 'weekly-cashflow':
            require_admin();
            $weeks = (int)($_GET['weeks'] ?? 8);
            if ($weeks < 1) {
                $weeks = 8;
            }
            if ($weeks > 24) {
                $weeks = 24;
            }

            $stmt = $pdo->prepare("SELECT DATE_TRUNC('week', created_at)::date AS week_start,
                                          COALESCE(SUM(CASE WHEN movement_type IN ('in','sale') THEN amount ELSE 0 END),0) AS total_in,
                                          COALESCE(SUM(CASE WHEN movement_type = 'out' THEN amount ELSE 0 END),0) AS total_out
                                   FROM cash_drawer_movements
                                   WHERE created_at >= (CURRENT_DATE - (? || ' weeks')::interval)
                                   GROUP BY 1 ORDER BY 1 ASC");
            $stmt->execute([$weeks]);
            $movements = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT DATE_TRUNC('week', created_at)::date AS week_start,
                                          COALESCE(SUM(total_amount),0) AS notes_total,
                                          COALESCE(SUM(amount_paid),0) AS notes_paid
                                   FROM cash_control_notes
                                   WHERE created_at >= (CURRENT_DATE - (? || ' weeks')::interval)
                                   GROUP BY 1 ORDER BY 1 ASC");
            $stmt->execute([$weeks]);
            $notes = $stmt->fetchAll();

            $response = ['success' => true, 'movements' => $movements, 'notes' => $notes];
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
    if (($_SESSION['role'] ?? '') === 'admin') {
        $response['debug'] = [
            'action' => (string)$action,
            'detail' => (string)$e->getMessage()
        ];
    }
}

echo json_encode($response);
