<?php
/**
 * API para búsqueda de productos por código de barras
 */

require_once '../../config/config.php';
require_once '../../src/models/Product.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

$productModel = new Product($pdo);
$response = [];

try {
    switch ($action) {
        case 'by-barcode':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $barcode = sanitize($_GET['barcode'] ?? '');
            
            if (empty($barcode)) {
                $response = ['success' => false, 'message' => 'Código de barras requerido'];
                break;
            }

            $product = $productModel->getByBarcode($barcode);
            
            if ($product) {
                $response = ['success' => true, 'product' => $product];
            } else {
                $response = ['success' => false, 'message' => 'Producto no encontrado'];
            }
            break;

        case 'search':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $term = sanitize($_GET['q'] ?? '');
            
            if (strlen($term) < 2) {
                $response = ['success' => false, 'message' => 'Término de búsqueda muy corto'];
                break;
            }

            $products = $productModel->search($term);
            $response = ['success' => true, 'products' => $products];
            break;

        case 'list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $category = sanitize($_GET['category'] ?? '');
            
            if ($category) {
                $products = $productModel->getByCategory($category);
            } else {
                $products = $productModel->getAll();
            }
            
            $response = ['success' => true, 'products' => $products];
            break;

        case 'log-scan':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            // Registrar escaneo en logs
            log_action(
                $_SESSION['user_id'] ?? null,
                'BARCODE_SCAN',
                'Código escaneado: ' . ($input['barcode'] ?? ''),
                getTrusSIDBug()
            );
            
            $response = ['success' => true, 'message' => 'Escaneo registrado'];
            break;

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }

} catch (Exception $e) {
    error_log("Products API Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
?>
