<?php
/**
 * API de Estadísticas y Análisis
 */

require_once '../../config/config.php';
require_once '../../src/controllers/AnalyticsController.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

$analyticsController = new AnalyticsController($pdo);
$response = [];

function is_admin_user(): bool {
    return (($_SESSION['role'] ?? '') === 'admin');
}

function get_current_client_id($pdo): ?int {
    try {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id'] ?? 0]);
        $id = (int)$stmt->fetchColumn();
        return $id > 0 ? $id : null;
    } catch (Exception $ignored) {
        return null;
    }
}

function client_available_years($pdo, int $clientId): array {
    $queries = [
        ["SELECT DISTINCT YEAR(created_at) AS year_value FROM orders WHERE client_id = ? AND created_at IS NOT NULL ORDER BY year_value DESC", [$clientId]],
        ["SELECT DISTINCT EXTRACT(YEAR FROM created_at) AS year_value FROM orders WHERE client_id = ? AND created_at IS NOT NULL ORDER BY year_value DESC", [$clientId]],
    ];

    foreach ($queries as $querySpec) {
        try {
            $stmt = $pdo->prepare($querySpec[0]);
            $stmt->execute($querySpec[1]);
            $rows = $stmt->fetchAll();
            if (!is_array($rows)) {
                continue;
            }
            $years = [];
            foreach ($rows as $row) {
                $yearValue = (int)($row['year_value'] ?? 0);
                if ($yearValue > 0) {
                    $years[] = $yearValue;
                }
            }
            if (!empty($years)) {
                return array_values(array_unique($years));
            }
        } catch (Exception $ignored) {
        }
    }

    return [(int)date('Y')];
}

function client_purchase_stats($pdo, int $clientId, ?int $year): array {
    $params = [$clientId];
    $queries = [];

    if ($year && $year > 0) {
        $queries[] = [
            "SELECT MONTH(created_at) AS month_num, COUNT(*) AS total_orders, COALESCE(SUM(total_amount), 0) AS total_amount FROM orders WHERE client_id = ? AND YEAR(created_at) = ? GROUP BY MONTH(created_at) ORDER BY month_num ASC",
            [$clientId, $year]
        ];
        $queries[] = [
            "SELECT EXTRACT(MONTH FROM created_at) AS month_num, COUNT(*) AS total_orders, COALESCE(SUM(total_amount), 0) AS total_amount FROM orders WHERE client_id = ? AND EXTRACT(YEAR FROM created_at) = ? GROUP BY EXTRACT(MONTH FROM created_at) ORDER BY month_num ASC",
            [$clientId, $year]
        ];
    } else {
        $queries[] = [
            "SELECT MONTH(created_at) AS month_num, COUNT(*) AS total_orders, COALESCE(SUM(total_amount), 0) AS total_amount FROM orders WHERE client_id = ? GROUP BY MONTH(created_at) ORDER BY month_num ASC",
            $params
        ];
        $queries[] = [
            "SELECT EXTRACT(MONTH FROM created_at) AS month_num, COUNT(*) AS total_orders, COALESCE(SUM(total_amount), 0) AS total_amount FROM orders WHERE client_id = ? GROUP BY EXTRACT(MONTH FROM created_at) ORDER BY month_num ASC",
            $params
        ];
    }

    $rows = [];
    foreach ($queries as $querySpec) {
        try {
            $stmt = $pdo->prepare($querySpec[0]);
            $stmt->execute($querySpec[1]);
            $rows = $stmt->fetchAll();
            if (is_array($rows)) {
                break;
            }
        } catch (Exception $ignored) {
            $rows = [];
        }
    }

    $monthNames = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    return array_map(function ($row) use ($monthNames) {
        $monthNum = (int)($row['month_num'] ?? 0);
        return [
            'Mes' => $monthNames[$monthNum] ?? ('Mes ' . $monthNum),
            'Pedidos' => (int)($row['total_orders'] ?? 0),
            'Total' => (float)($row['total_amount'] ?? 0),
        ];
    }, is_array($rows) ? $rows : []);
}

function client_summary($pdo, int $clientId): array {
    $summary = [
        'total_orders' => 0,
        'total_spent' => 0,
        'avg_ticket' => 0,
        'pending_orders' => 0,
    ];

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total_orders, COALESCE(SUM(total_amount), 0) AS total_spent, COALESCE(AVG(total_amount), 0) AS avg_ticket FROM orders WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $row = $stmt->fetch();
        if ($row) {
            $summary['total_orders'] = (int)($row['total_orders'] ?? 0);
            $summary['total_spent'] = (float)($row['total_spent'] ?? 0);
            $summary['avg_ticket'] = (float)($row['avg_ticket'] ?? 0);
        }
    } catch (Exception $ignored) {
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE client_id = ? AND status IN ('pending', 'processing', 'confirmed')");
        $stmt->execute([$clientId]);
        $summary['pending_orders'] = (int)$stmt->fetchColumn();
    } catch (Exception $ignored) {
    }

    return $summary;
}

function order_monthly_stats($pdo, ?int $year, ?int $clientId = null): array {
    $sql = "
        SELECT
            EXTRACT(MONTH FROM created_at)::int AS month_num,
            COUNT(*) AS total_orders,
            COALESCE(SUM(total_amount), 0) AS total_amount
        FROM orders
        WHERE created_at IS NOT NULL
    ";

    $params = [];

    if ($year && $year > 0) {
        $sql .= " AND EXTRACT(YEAR FROM created_at) = ?";
        $params[] = $year;
    }

    if ($clientId) {
        $sql .= " AND client_id = ?";
        $params[] = $clientId;
    }

    $sql .= " GROUP BY month_num ORDER BY month_num ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

function order_yearly_stats($pdo, ?int $clientId = null): array {
    $sql = "
        SELECT
            EXTRACT(YEAR FROM created_at)::int AS year_val,
            COUNT(*) AS total_orders,
            COALESCE(SUM(total_amount), 0) AS total_amount
        FROM orders
        WHERE created_at IS NOT NULL
    ";

    $params = [];

    if ($clientId) {
        $sql .= " AND client_id = ?";
        $params[] = $clientId;
    }

    $sql .= " GROUP BY year_val ORDER BY year_val DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

try {
    switch ($action) {
        case 'purchase-stats':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $year = isset($_GET['year']) ? (int)$_GET['year'] : null;
            $clientId = is_admin_user() ? null : get_current_client_id($pdo);
            if (!$clientId && !is_admin_user()) {
                $response = ['success' => true, 'stats' => [], 'category_stats' => []];
                break;
            }

            $stats = order_monthly_stats($pdo, $year, $clientId);
            
            $catSql = "
                SELECT 
                    COALESCE(p.category, 'General') AS category, 
                    SUM(oi.quantity)::int AS total_qty, 
                    SUM(oi.line_total)::float AS total_amount
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                JOIN orders o ON o.id = oi.order_id
                WHERE o.created_at IS NOT NULL
            ";
            $catParams = [];
            if ($year && $year > 0) {
                $catSql .= " AND EXTRACT(YEAR FROM o.created_at) = ?";
                $catParams[] = $year;
            }
            if ($clientId) {
                $catSql .= " AND o.client_id = ?";
                $catParams[] = $clientId;
            }
            $catSql .= " GROUP BY p.category ORDER BY total_amount DESC";
            $catStmt = $pdo->prepare($catSql);
            $catStmt->execute($catParams);
            $catStats = $catStmt->fetchAll() ?: [];

            $response = [
                'success' => true,
                'stats' => $stats,
                'category_stats' => $catStats
            ];
            break;

        case 'available-years':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $currentYear = (int)date('Y');
            $previousYear = $currentYear - 1;
            $allowPastYears = $currentYear > 2026;

            if (is_admin_user()) {
                $years = [];
                try {
                    $yearRows = order_yearly_stats($pdo, null);
                    foreach ($yearRows as $row) {
                        $yearValue = (int)($row['year_val'] ?? 0);
                        if ($yearValue > 0) {
                            $years[] = $yearValue;
                        }
                    }
                } catch (Exception $ignored) {
                    $years = [];
                }

                $years[] = $currentYear;
                if ($allowPastYears) {
                    $years[] = $previousYear;
                }

                $years = array_values(array_unique(array_map('intval', $years)));
                rsort($years);

                $response = ['success' => true, 'years' => $years];
                break;
            }

            $clientId = get_current_client_id($pdo);
            if (!$clientId) {
                $fallbackYears = [$currentYear];
                if ($allowPastYears) {
                    $fallbackYears[] = $previousYear;
                }
                $response = ['success' => true, 'years' => $fallbackYears];
                break;
            }

            $years = client_available_years($pdo, $clientId);
            $years[] = $currentYear;
            if ($allowPastYears) {
                $years[] = $previousYear;
            }
            $years = array_values(array_unique(array_map('intval', $years)));
            rsort($years);

            $response = ['success' => true, 'years' => $years];
            break;

        case 'my-summary':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $clientId = get_current_client_id($pdo);
            if (!$clientId) {
                $response = ['success' => true, 'summary' => client_summary($pdo, 0)];
                break;
            }

            $response = ['success' => true, 'summary' => client_summary($pdo, $clientId)];
            break;

        case 'predictions':
            require_admin();
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $predictions = $analyticsController->generatePredictions();
            $response = ['success' => true, 'predictions' => $predictions];

            log_action(
                $_SESSION['user_id'],
                'GENERATE_PREDICTIONS',
                'Se generaron ' . count($predictions) . ' predicciones',
                getTrusSIDBug()
            );
            break;

        case 'dashboard-metrics':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $metrics = $analyticsController->getDashboardMetrics();
            $response = ['success' => true, 'metrics' => $metrics];
            break;

        case 'export':
            require_admin();
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $format = $_GET['format'] ?? 'csv';
            
            // Fetch data for report
            $stmt = $pdo->prepare("
                SELECT 
                    o.id AS order_id,
                    o.created_at,
                    o.total_amount,
                    c.company_name,
                    u.first_name || ' ' || u.last_name AS client_name
                FROM orders o
                LEFT JOIN clients c ON o.client_id = c.id
                LEFT JOIN users u ON c.user_id = u.id
                ORDER BY o.created_at DESC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();

            $filename = 'truper_report_' . date('Y-m-d_H-i') . '.' . $format;
            $filepath = '../exports/' . $filename;
            
            if (!is_dir('../exports')) {
                mkdir('../exports', 0777, true);
            } else {
                // Clear files older than 7 days
                $files = glob('../exports/truper_report_*');
                if (is_array($files)) {
                    $cutoff = time() - (7 * 24 * 60 * 60);
                    foreach ($files as $file) {
                        if (is_file($file) && filemtime($file) < $cutoff) {
                            @unlink($file);
                        }
                    }
                }
            }

            $fp = fopen($filepath, 'w');
            if ($fp) {
                // BOM for Excel UTF-8
                fputs($fp, $bom = chr(0xEF).chr(0xBB).chr(0xBF));
                
                // Headers
                fputcsv($fp, ['ID Pedido', 'Fecha', 'Total', 'Empresa', 'Cliente']);
                
                // Data
                foreach ($data as $row) {
                    fputcsv($fp, [
                        $row['order_id'],
                        $row['created_at'],
                        $row['total_amount'],
                        $row['company_name'],
                        $row['client_name']
                    ]);
                }
                fclose($fp);
                
                $response = [
                    'success' => true,
                    'file_url' => 'exports/' . $filename,
                    'filename' => $filename
                ];
            } else {
                $response = ['success' => false, 'message' => 'No se pudo generar el archivo'];
            }
            break;

        case 'ticket-export':
            require_admin();
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
            $exportType = $_GET['type'] ?? 'client'; // 'client', 'supplier', o 'both'
            
            if ($year < 2000) {
                $year = (int)date('Y');
            }
            if ($month < 1 || $month > 12) {
                $month = (int)date('m');
            }

            $ticketData = $analyticsController->getTicketsHistory($year, $month);
            $clientTickets = $ticketData['tickets'] ?? [];
            $supplierTickets = ($exportType === 'supplier' || $exportType === 'both') ? ($ticketData['supplier_tickets'] ?? []) : [];

            $monthNames = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
            $filename = 'historial_tickets_' . $year . '_' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '.xls';

            // Clean and disable any output buffer to ensure clean file download
            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo "\xEF\xBB\xBF";
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><title>Historial de Tickets</title></head><body>';

            // TABLA DE TICKETS DE CLIENTE
            if ($exportType === 'client' || $exportType === 'both') {
                echo '<table border="1" style="margin-bottom: 20px;">';
                echo '<tr><th colspan="7" style="background-color: #FF7F00; color: white; padding: 10px;">Historial de Tickets Cliente - ' . htmlspecialchars(($monthNames[$month] ?? 'Mes') . ' ' . $year, ENT_QUOTES, 'UTF-8') . '</th></tr>';
                echo '<tr style="background-color: #f0f0f0;"><th>Folio</th><th>Cliente</th><th>Tipo</th><th>Monto</th><th>Estado Pago</th><th>Fecha</th><th>Artículos</th></tr>';

                if (empty($clientTickets)) {
                    echo '<tr><td colspan="7" style="text-align: center; padding: 10px;">Sin tickets de cliente en este período</td></tr>';
                }

                foreach ($clientTickets as $ticket) {
                    $typeLabel = [
                        'sale' => 'Venta',
                        'return' => 'Devolución',
                        'adjustment' => 'Ajuste',
                        'credit' => 'Crédito'
                    ][$ticket['ticket_type'] ?? ''] ?? ($ticket['ticket_type'] ?? '');
                    $statusLabel = ($ticket['payment_status'] ?? '') === 'completed' ? 'Pagado' : 'Pendiente';
                    $customerName = $ticket['customer_name'] ?? 'Sin nombre';
                    $email = $ticket['email'] ?? '';
                    $clientCell = $email !== '' ? ($customerName . ' (' . $email . ')') : $customerName;

                    echo '<tr>';
                    echo '<td>' . htmlspecialchars((string)($ticket['folio'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars($clientCell, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars(number_format((float)($ticket['total_amount'] ?? 0), 2, '.', ','), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars((string)($ticket['issued_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars((string)($ticket['item_count'] ?? 0), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }

            // TABLA DE TICKETS DE PROVEEDOR
            if ($exportType === 'supplier' || $exportType === 'both') {
                echo '<table border="1">';
                echo '<tr><th colspan="7" style="background-color: #4CAF50; color: white; padding: 10px;">Historial de Órdenes de Proveedor - ' . htmlspecialchars(($monthNames[$month] ?? 'Mes') . ' ' . $year, ENT_QUOTES, 'UTF-8') . '</th></tr>';
                echo '<tr style="background-color: #f0f0f0;"><th>Folio</th><th>Proveedor</th><th>Tipo</th><th>Monto</th><th>Estado Pago</th><th>Fecha</th><th>Artículos</th></tr>';

                if (empty($supplierTickets)) {
                    echo '<tr><td colspan="7" style="text-align: center; padding: 10px;">Sin órdenes de proveedor en este período</td></tr>';
                }

                foreach ($supplierTickets as $ticket) {
                    $statusLabel = ($ticket['payment_status'] ?? '') === 'completed' ? 'Pagado' : 'Pendiente';
                    $supplierName = $ticket['customer_name'] ?? 'Sin nombre';

                    echo '<tr>';
                    echo '<td>' . htmlspecialchars((string)($ticket['folio'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>Compra</td>';
                    echo '<td>' . htmlspecialchars(number_format((float)($ticket['total_amount'] ?? 0), 2, '.', ','), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars((string)($ticket['issued_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars((string)($ticket['item_count'] ?? 0), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }

            echo '</body></html>';
            exit;

        case 'yearly-stats':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $clientId = is_admin_user() ? null : get_current_client_id($pdo);
            if (!$clientId && !is_admin_user()) {
                $response = ['success' => true, 'stats' => []];
                break;
            }

            $stats = order_yearly_stats($pdo, $clientId);
            $response = ['success' => true, 'stats' => $stats];
            break;

        case 'calendar-data':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
            $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            
            $clientId = is_admin_user() ? null : get_current_client_id($pdo);
            
            $sql = "
                SELECT 
                    EXTRACT(DAY FROM created_at)::int as day,
                    COUNT(*) as count,
                    COALESCE(SUM(total_amount), 0) as total
                FROM orders
                WHERE EXTRACT(MONTH FROM created_at) = ?
                AND EXTRACT(YEAR FROM created_at) = ?
            ";
            
            $params = [$month, $year];
            if ($clientId) {
                $sql .= " AND client_id = ?";
                $params[] = $clientId;
            }
            $sql .= " GROUP BY day ORDER BY day ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $response = ['success' => true, 'days' => $stmt->fetchAll()];
            break;

        case 'seasonal-report':
            require_admin();
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $stats = $analyticsController->getPurchaseStatistics(null, date('Y'));
            $report = [];
            foreach ($stats as $row) {
                $season = $row['season'] ?: 'Sin temporada';
                $productName = (string)($row['name'] ?? ('Producto #' . ($row['product_id'] ?? 'N/A')));
                $amount = (float)($row['total_amount'] ?? 0);
                $quantity = (int)($row['total_quantity'] ?? 0);

                if (!isset($report[$season])) {
                    $report[$season] = [
                        'total_amount' => 0,
                        'total_quantity' => 0,
                        'top_products' => [],
                        'factors' => ['Temporada histórica']
                    ];
                }

                $report[$season]['total_amount'] += $amount;
                $report[$season]['total_quantity'] += $quantity;
                if (!isset($report[$season]['top_products'][$productName])) {
                    $report[$season]['top_products'][$productName] = 0;
                }
                $report[$season]['top_products'][$productName] += $quantity;
            }

            foreach ($report as $season => $seasonData) {
                arsort($seasonData['top_products']);
                $top = [];
                $count = 0;
                foreach ($seasonData['top_products'] as $name => $qty) {
                    $top[] = ['name' => $name, 'quantity' => (int)$qty];
                    $count++;
                    if ($count >= 5) {
                        break;
                    }
                }
                $report[$season]['top_products'] = $top;
            }
            $response = ['success' => true, 'report' => $report];
            break;

        case 'client-analytics':
            require_admin();
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $firstNameExpr = db_column_exists('users', 'first_name') ? "COALESCE(u.first_name, '')" : "''";
            $lastNameExpr = db_column_exists('users', 'last_name') ? "COALESCE(u.last_name, '')" : "''";
            $nameExpr = "TRIM(" . $firstNameExpr . " || ' ' || " . $lastNameExpr . ")";
            $pointsExpr = db_column_exists('users', 'loyalty_points') ? "COALESCE(u.loyalty_points, 0)" : (db_column_exists('users', 'points') ? "COALESCE(u.points, 0)" : "0");
            $activeExpr = db_column_exists('users', 'is_active') ? "COALESCE(u.is_active, true)" : (db_column_exists('users', 'active') ? "(COALESCE(u.active, 1) = 1)" : "true");

            $sql = "
                SELECT
                    u.id,
                    " . $nameExpr . " AS name,
                    " . $pointsExpr . " AS loyalty_points,
                    " . $activeExpr . " AS is_active,
                    COALESCE(COUNT(o.id), 0) AS order_count,
                    COALESCE(SUM(o.total_amount), 0) AS total_spent
                FROM users u
                LEFT JOIN clients c ON c.user_id = u.id
                LEFT JOIN orders o ON o.client_id = c.id
                WHERE u.role = 'client'
                GROUP BY u.id, " . $nameExpr . ", " . $pointsExpr . ", " . $activeExpr . "
                ORDER BY total_spent DESC
                LIMIT 100
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            $response = ['success' => true, 'clients' => $stmt->fetchAll()];
            break;

        case 'ticket-history':
            require_admin();
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

            $result = $analyticsController->getTicketsHistory($year, $month);
            $response = ['success' => true, 'data' => $result];
            break;

        case 'ticket-years':
            require_admin();
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $response = ['success' => true, 'years' => $analyticsController->getTicketAvailableYears()];
            break;

        case 'archive-tickets':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $year = isset($_POST['year']) ? (int)$_POST['year'] : null;
            $month = isset($_POST['month']) ? (int)$_POST['month'] : null;

            $result = $analyticsController->archiveTicketsOfMonth($year, $month);
            $response = $result;

            if ($result['success']) {
                log_action(
                    $_SESSION['user_id'],
                    'ARCHIVE_TICKETS',
                    'Se archivaron ' . ($result['archived_count'] ?? 0) . ' tickets de ' . ($result['year'] ?? 'N/A') . '-' . str_pad($result['month'] ?? 0, 2, '0', STR_PAD_LEFT),
                    getTrusSIDBug()
                );
            }
            break;

        case 'save-monthly-pdf':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $rawInput = file_get_contents('php://input');
            $decodedInput = json_decode($rawInput, true);
            $input = is_array($decodedInput) ? $decodedInput : (is_array($_POST) ? $_POST : []);

            $year = isset($input['year']) ? (int)$input['year'] : null;
            $month = isset($input['month']) ? (int)$input['month'] : null;
            $pdfBase64 = $input['pdf_data'] ?? null;

            if (!$year || !$month || !$pdfBase64) {
                $response = ['success' => false, 'message' => 'Datos insuficientes (año, mes o pdf_data faltantes)'];
                break;
            }

            // Crear carpeta destino
            $dir = __DIR__ . '/../archivos_mensuales';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $filename = 'reporte_mes_' . $year . '_' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '.pdf';
            $filepath = $dir . '/' . $filename;

            // Guardar archivo
            $binaryData = base64_decode($pdfBase64);
            if ($binaryData === false) {
                $response = ['success' => false, 'message' => 'Error al decodificar PDF Base64'];
                break;
            }

            if (file_put_contents($filepath, $binaryData) === false) {
                $response = ['success' => false, 'message' => 'Error al guardar archivo en el servidor'];
                break;
            }

            $response = [
                'success' => true,
                'message' => 'PDF de reporte guardado correctamente',
                'filename' => $filename
            ];
            break;

        case 'list-monthly-pdfs':
            require_admin();
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $dir = __DIR__ . '/../archivos_mensuales';
            $filesList = [];

            if (is_dir($dir)) {
                $files = glob($dir . '/reporte_mes_*.pdf');
                if (is_array($files)) {
                    foreach ($files as $file) {
                        $basename = basename($file);
                        
                        // Extraer año y mes: reporte_mes_YYYY_MM.pdf
                        if (preg_match('/reporte_mes_(\d{4})_(\d{2})\.pdf$/', $basename, $matches)) {
                            $year = (int)$matches[1];
                            $month = (int)$matches[2];
                            
                            $monthNames = [
                                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                            ];
                            
                            $readableName = ($monthNames[$month] ?? 'Mes') . ' ' . $year;
                            $filesList[] = [
                                'filename' => $basename,
                                'readable_name' => $readableName,
                                'year' => $year,
                                'month' => $month,
                                'url' => 'archivos_mensuales/' . $basename,
                                'size' => filesize($file),
                                'created_at' => filemtime($file)
                            ];
                        }
                    }
                }
            }

            // Ordenar por año y mes descendente
            usort($filesList, function($a, $b) {
                if ($a['year'] !== $b['year']) {
                    return $b['year'] - $a['year'];
                }
                return $b['month'] - $a['month'];
            });

            $response = [
                'success' => true,
                'files' => $filesList
            ];
            break;

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }

} catch (Exception $e) {
    error_log("Analytics API Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
?>
