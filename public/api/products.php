<?php
/**
 * API para búsqueda de productos por código de barras
 */

require_once '../../config/config.php';
require_once '../../src/models/Product.php';

ensure_xlsx_products_seeded();

header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

function display_product_code($sku) {
    return preg_replace('/^\s*XLS-/i', '', (string)$sku);
}

function normalize_product_sku_for_response(array $product) {
    if (array_key_exists('sku', $product)) {
        $product['sku'] = display_product_code($product['sku']);
    }
    return $product;
}

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
                if (is_array($product)) {
                    $product = normalize_product_sku_for_response($product);
                }
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
            if (is_array($products)) {
                $products = array_map('normalize_product_sku_for_response', $products);
            }
            $response = ['success' => true, 'products' => $products];
            break;

        case 'list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $category = sanitize($_GET['category'] ?? '');

            $products = [];
            $queries = [];
            if ($category !== '') {
                $queries[] = [
                    "SELECT id, name, sku, COALESCE(unit_price, sell_price, 0) AS unit_price, category FROM products WHERE is_active = true AND category = ? ORDER BY name LIMIT 200",
                    [$category]
                ];
                $queries[] = [
                    "SELECT id, name, sku, COALESCE(sell_price, unit_price, 0) AS unit_price, category FROM products WHERE active = 1 AND category = ? ORDER BY name LIMIT 200",
                    [$category]
                ];
                $queries[] = [
                    "SELECT id, name, sku, COALESCE(unit_price, sell_price, 0) AS unit_price, category FROM products WHERE category = ? ORDER BY name LIMIT 200",
                    [$category]
                ];
            } else {
                $queries[] = [
                    "SELECT id, name, sku, COALESCE(unit_price, sell_price, 0) AS unit_price, category FROM products WHERE is_active = true ORDER BY name LIMIT 200",
                    []
                ];
                $queries[] = [
                    "SELECT id, name, sku, COALESCE(sell_price, unit_price, 0) AS unit_price, category FROM products WHERE active = 1 ORDER BY name LIMIT 200",
                    []
                ];
                $queries[] = [
                    "SELECT id, name, sku, COALESCE(unit_price, sell_price, 0) AS unit_price, category FROM products ORDER BY name LIMIT 200",
                    []
                ];
            }

            foreach ($queries as $querySpec) {
                try {
                    $stmt = $pdo->prepare($querySpec[0]);
                    $stmt->execute($querySpec[1]);
                    $products = $stmt->fetchAll();
                    if (is_array($products)) {
                        break;
                    }
                } catch (Exception $ignored) {
                    $products = [];
                }
            }

            if (empty($products)) {
                $seed = get_xlsx_seed_products();
                if (!empty($seed)) {
                    if ($category !== '') {
                        $products = array_values(array_filter($seed, function ($item) use ($category) {
                            return strcasecmp((string)($item['category'] ?? ''), $category) === 0;
                        }));
                    } else {
                        $products = $seed;
                    }

                    $products = array_map(function ($p) {
                        return [
                            'id' => $p['id'],
                            'name' => $p['name'],
                            'sku' => display_product_code($p['sku']),
                            'unit_price' => $p['unit_price'],
                            'category' => $p['category']
                        ];
                    }, $products);
                }
            }

            if (is_array($products)) {
                $products = array_map('normalize_product_sku_for_response', $products);
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
