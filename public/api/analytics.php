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

try {
    switch ($action) {
        case 'purchase-stats':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $year = isset($_GET['year']) ? (int)$_GET['year'] : null;
            if (is_admin_user()) {
                $product_id = $_GET['product_id'] ?? null;
                $stats = $analyticsController->getPurchaseStatistics($product_id, $year);
            } else {
                $clientId = get_current_client_id($pdo);
                if (!$clientId) {
                    $response = ['success' => true, 'stats' => []];
                    break;
                }
                $stats = client_purchase_stats($pdo, $clientId, $year);
            }
            $response = ['success' => true, 'stats' => $stats];
            break;

        case 'available-years':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            if (is_admin_user()) {
                $years = [];
                try {
                    $stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) AS year_value FROM orders WHERE created_at IS NOT NULL ORDER BY year_value DESC");
                    $rows = $stmt ? $stmt->fetchAll() : [];
                    foreach ($rows as $row) {
                        $yearValue = (int)($row['year_value'] ?? 0);
                        if ($yearValue > 0) {
                            $years[] = $yearValue;
                        }
                    }
                } catch (Exception $ignored) {
                    $years = [];
                }
                if (empty($years)) {
                    $years[] = (int)date('Y');
                }
                $response = ['success' => true, 'years' => array_values(array_unique($years))];
                break;
            }

            $clientId = get_current_client_id($pdo);
            if (!$clientId) {
                $response = ['success' => true, 'years' => [(int)date('Y')]];
                break;
            }

            $response = ['success' => true, 'years' => client_available_years($pdo, $clientId)];
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
            // Aquí se implementaría la lógica de exportación
            $response = [
                'success' => true,
                'file_url' => '/truper_platform/exports/report.' . $format,
                'filename' => 'truper_report_' . date('Y-m-d') . '.' . $format
            ];
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
                if (!isset($report[$season])) {
                    $report[$season] = [
                        'total_amount' => 0,
                        'total_quantity' => 0,
                        'top_products' => [],
                        'factors' => ['Temporada histórica']
                    ];
                }

                $report[$season]['total_quantity'] += (int) ($row['total_quantity'] ?? 0);
            }
            $response = ['success' => true, 'report' => $report];
            break;

        case 'client-analytics':
            require_admin();
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $stmt = $pdo->prepare("
                SELECT
                    u.id,
                    CONCAT(u.first_name, ' ', u.last_name) AS name,
                    u.loyalty_points,
                    u.is_active,
                    COALESCE(COUNT(o.id), 0) AS order_count,
                    COALESCE(SUM(o.total_amount), 0) AS total_spent
                FROM users u
                LEFT JOIN clients c ON c.user_id = u.id
                LEFT JOIN orders o ON o.client_id = c.id
                WHERE u.role = 'client'
                GROUP BY u.id, u.first_name, u.last_name, u.loyalty_points, u.is_active
                ORDER BY total_spent DESC
                LIMIT 100
            ");
            $stmt->execute();

            $response = ['success' => true, 'clients' => $stmt->fetchAll()];
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
