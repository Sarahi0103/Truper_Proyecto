<?php
/**
 * API de Gastos del Negocio
 * Endpoint: /api/expenses.php
 */
require_once '../../config/config.php';
require_admin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
}

// Auto-crear tabla si no existe
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS business_expenses (
            id          SERIAL PRIMARY KEY,
            title       VARCHAR(255) NOT NULL,
            description TEXT,
            amount      DECIMAL(12,2) NOT NULL,
            category    VARCHAR(100) DEFAULT 'General',
            created_by  INT REFERENCES users(id) ON DELETE SET NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            deleted_at  TIMESTAMP DEFAULT NULL
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expenses_created_at ON business_expenses(created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expenses_deleted ON business_expenses(deleted_at)");
} catch (Exception $e) {
    error_log('Error creando tabla expenses: ' . $e->getMessage());
}

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    switch ($action) {

        // ── Listar gastos ──────────────────────────────────────────────────
        case 'list':
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
            $offset  = ($page - 1) * $perPage;

            $where  = 'WHERE e.deleted_at IS NULL';
            $params = [];

            if (!empty($_GET['category'])) {
                $where .= ' AND e.category = :category';
                $params[':category'] = $_GET['category'];
            }
            if (!empty($_GET['month'])) {          // YYYY-MM
                $where .= " AND TO_CHAR(e.created_at, 'YYYY-MM') = :month";
                $params[':month'] = $_GET['month'];
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM business_expenses e $where");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT e.*, u.first_name || ' ' || COALESCE(u.last_name,'') AS created_by_name
                FROM business_expenses e
                LEFT JOIN users u ON e.created_by = u.id
                $where
                ORDER BY e.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
            $stmt->execute();
            $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'  => true,
                'expenses' => $expenses,
                'pagination' => [
                    'page'        => $page,
                    'per_page'    => $perPage,
                    'total'       => $total,
                    'total_pages' => (int)ceil($total / $perPage),
                ],
            ]);
            break;

        // ── Crear gasto ────────────────────────────────────────────────────
        case 'create':
            if ($method !== 'POST') { echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit; }

            $title       = sanitize($input['title'] ?? '');
            $description = sanitize($input['description'] ?? '');
            $amount      = (float)($input['amount'] ?? 0);
            $category    = sanitize($input['category'] ?? 'General');

            if (empty($title))   { echo json_encode(['success'=>false,'message'=>'El título es requerido']); exit; }
            if ($amount <= 0)    { echo json_encode(['success'=>false,'message'=>'El monto debe ser mayor a 0']); exit; }

            $stmt = $pdo->prepare("
                INSERT INTO business_expenses (title, description, amount, category, created_by, created_at)
                VALUES (:title, :description, :amount, :category, :created_by, NOW())
                RETURNING id, created_at
            ");
            $stmt->execute([
                ':title'       => $title,
                ':description' => $description,
                ':amount'      => $amount,
                ':category'    => $category,
                ':created_by'  => $_SESSION['user_id'],
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'    => true,
                'message'    => 'Gasto registrado correctamente',
                'expense_id' => $row['id'],
                'created_at' => $row['created_at'],
            ]);
            break;

        // ── Eliminar gasto (soft delete) ───────────────────────────────────
        case 'delete':
            if ($method !== 'POST') { echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit; }

            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'ID inválido']); exit; }

            $stmt = $pdo->prepare("UPDATE business_expenses SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() === 0) {
                echo json_encode(['success'=>false,'message'=>'Gasto no encontrado']);
            } else {
                echo json_encode(['success'=>true,'message'=>'Gasto eliminado']);
            }
            break;

        // ── Estadísticas de gastos ─────────────────────────────────────────
        case 'stats':
            $month = $_GET['month'] ?? date('Y-m');  // YYYY-MM
            $year  = $_GET['year']  ?? date('Y');

            // Total gastos del mes
            $stmtMonth = $pdo->prepare("
                SELECT COALESCE(SUM(amount),0) AS total_month, COUNT(*) AS count_month
                FROM business_expenses
                WHERE deleted_at IS NULL
                  AND TO_CHAR(created_at, 'YYYY-MM') = :month
            ");
            $stmtMonth->execute([':month' => $month]);
            $monthData = $stmtMonth->fetch(PDO::FETCH_ASSOC);

            // Total gastos del año
            $stmtYear = $pdo->prepare("
                SELECT COALESCE(SUM(amount),0) AS total_year, COUNT(*) AS count_year
                FROM business_expenses
                WHERE deleted_at IS NULL
                  AND TO_CHAR(created_at, 'YYYY') = :year
            ");
            $stmtYear->execute([':year' => $year]);
            $yearData = $stmtYear->fetch(PDO::FETCH_ASSOC);

            // Gastos por categoría (mes actual)
            $stmtCat = $pdo->prepare("
                SELECT category, COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt
                FROM business_expenses
                WHERE deleted_at IS NULL
                  AND TO_CHAR(created_at, 'YYYY-MM') = :month
                GROUP BY category
                ORDER BY total DESC
            ");
            $stmtCat->execute([':month' => $month]);
            $byCategory = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

            // Gastos por mes (últimos 12 meses)
            $stmtMonthly = $pdo->query("
                SELECT TO_CHAR(created_at, 'YYYY-MM') AS month_key,
                       COALESCE(SUM(amount),0) AS total
                FROM business_expenses
                WHERE deleted_at IS NULL
                  AND created_at >= NOW() - INTERVAL '12 months'
                GROUP BY month_key
                ORDER BY month_key ASC
            ");
            $monthly = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);

            // Total ingresos del mes (de ventas validadas)
            $stmtIncome = $pdo->prepare("
                SELECT COALESCE(SUM(total_amount),0) AS total_income
                FROM sales_tickets
                WHERE deleted_at IS NULL
                  AND payment_status = 'completed'
                  AND TO_CHAR(issued_date, 'YYYY-MM') = :month
            ");
            $stmtIncome->execute([':month' => $month]);
            $incomeData = $stmtIncome->fetch(PDO::FETCH_ASSOC);

            $totalIncome   = (float)($incomeData['total_income'] ?? 0);
            $totalExpenses = (float)($monthData['total_month'] ?? 0);
            $netProfit     = $totalIncome - $totalExpenses;

            echo json_encode([
                'success'       => true,
                'month'         => $month,
                'total_income'  => $totalIncome,
                'total_expenses'=> $totalExpenses,
                'net_profit'    => $netProfit,
                'count_month'   => (int)($monthData['count_month'] ?? 0),
                'total_year'    => (float)($yearData['total_year'] ?? 0),
                'count_year'    => (int)($yearData['count_year'] ?? 0),
                'by_category'   => $byCategory,
                'monthly_trend' => $monthly,
            ]);
            break;

        // ── Categorías disponibles ─────────────────────────────────────────
        case 'categories':
            $stmt = $pdo->query("
                SELECT DISTINCT category FROM business_expenses
                WHERE deleted_at IS NULL
                ORDER BY category ASC
            ");
            $cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success'=>true,'categories'=>$cats]);
            break;

        default:
            echo json_encode(['success'=>false,'message'=>'Acción no reconocida']);
    }

} catch (Exception $e) {
    error_log('Error en expenses API: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Error del servidor']);
}
