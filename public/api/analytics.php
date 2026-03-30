<?php
/**
 * API de Estadísticas y Análisis
 */

require_once '../../config/config.php';
require_once '../../src/controllers/AnalyticsController.php';

require_admin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

$analyticsController = new AnalyticsController($pdo);
$response = [];

try {
    switch ($action) {
        case 'purchase-stats':
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

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }

} catch (Exception $e) {
    error_log("Analytics API Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
?>
