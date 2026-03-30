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

try {
    switch ($action) {
        case 'purchase-stats':
            require_admin();
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $product_id = $_GET['product_id'] ?? null;
            $year = $_GET['year'] ?? null;

            $stats = $analyticsController->getPurchaseStatistics($product_id, $year);
            $response = ['success' => true, 'stats' => $stats];
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
