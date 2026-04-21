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
$rawInput = file_get_contents('php://input');
$decodedInput = json_decode($rawInput, true);
$input = is_array($decodedInput) ? $decodedInput : (is_array($_POST) ? $_POST : []);

function display_product_code($sku) {
    return preg_replace('/^\s*XLS-/i', '', (string)$sku);
}

function normalize_product_sku_for_response(array $product) {
    if (array_key_exists('sku', $product)) {
        $product['sku'] = display_product_code($product['sku']);
    }
    return $product;
}

function normalize_bool_products_api($value, bool $default = false): bool {
    if ($value === null) {
        return $default;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return ((int)$value) !== 0;
    }

    $raw = trim((string)$value);
    if ($raw === '') {
        return $default;
    }

    return in_array(strtolower($raw), ['1', 'true', 't', 'yes', 'y', 'on'], true);
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
            
            // Registrar escaneo en logs
            log_action(
                $_SESSION['user_id'] ?? null,
                'BARCODE_SCAN',
                'Código escaneado: ' . ($input['barcode'] ?? ''),
                getTrusSIDBug()
            );
            
            $response = ['success' => true, 'message' => 'Escaneo registrado'];
            break;

        case 'list-all':
            // For admin visibility control
            require_admin();
            $queries = [
                ["SELECT id, name, sku, unit_price, is_active FROM products ORDER BY name LIMIT 500", []],
                ["SELECT id, name, sku, sell_price AS unit_price, active AS is_active FROM products ORDER BY name LIMIT 500", []]
            ];
            $products = [];
            foreach ($queries as $q) {
                try {
                    $stmt = $pdo->prepare($q[0]);
                    $stmt->execute($q[1]);
                    $products = $stmt->fetchAll();
                    if (!empty($products)) break;
                } catch (Exception $e) {}
            }
            $response = ['success' => true, 'items' => $products ?: []];
            break;

        case 'toggle-visibility':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            $id = (int)($input['id'] ?? 0);
            $is_active = normalize_bool_products_api($input['is_active'] ?? null, true);
            
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'ID inválido'];
                break;
            }

            // Try multiple column names (compatibility)
            $updated = false;
            try {
                $stmt = $pdo->prepare("UPDATE products SET is_active = ? WHERE id = ?");
                $stmt->execute([$is_active ? true : false, $id]);
                $updated = $stmt->rowCount() > 0;
            } catch (Exception $e1) {
                try {
                    $stmt = $pdo->prepare("UPDATE products SET active = ? WHERE id = ?");
                    $stmt->execute([$is_active ? 1 : 0, $id]);
                    $updated = $stmt->rowCount() > 0;
                } catch (Exception $e2) {}
            }

            $response = [
                'success' => $updated,
                'message' => $updated ? ($is_active ? 'Producto visible' : 'Producto oculto') : 'No se pudo actualizar'
            ];
            break;

        case 'preview-price-adjustment':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $type = $input['type'] ?? 'percentage';
            $value = (float)($input['value'] ?? 0);
            $exclude_skus = $input['exclude_skus'] ?? [];

            $stmt = $pdo->prepare("SELECT id, name, sku, unit_price FROM products WHERE is_active = true ORDER BY name LIMIT 500");
            if (!$stmt->execute()) {
                $stmt = $pdo->prepare("SELECT id, name, sku, sell_price AS unit_price FROM products ORDER BY name LIMIT 500");
                $stmt->execute();
            }
            $products = $stmt->fetchAll();

            $preview = [];
            $count = 0;
            foreach ($products as $p) {
                $sku = strtoupper(preg_replace('/^XLS-/i', '', (string)$p['sku']));
                if (in_array($sku, array_map('strtoupper', $exclude_skus), true)) continue;

                $current = (float)$p['unit_price'];
                $new = $type === 'percentage' ? round($current * (1 + $value / 100), 0) : round($current + $value, 0);
                
                $count++;
                if (count($preview) < 5) {
                    $preview[] = [
                        'name' => $p['name'],
                        'current_price' => $current,
                        'new_price' => $new
                    ];
                }
            }

            $response = ['success' => true, 'preview' => $preview, 'count' => $count];
            break;

        case 'apply-price-adjustment':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $type = $input['type'] ?? 'percentage';
            $value = (float)($input['value'] ?? 0);
            $exclude_skus = $input['exclude_skus'] ?? [];
            $affect_count = 0;

            $stmt = $pdo->prepare("SELECT id, sku, unit_price FROM products WHERE is_active = true");
            if (!$stmt->execute()) {
                $stmt = $pdo->prepare("SELECT id, sku, sell_price AS unit_price FROM products");
                $stmt->execute();
            }
            $products = $stmt->fetchAll();

            foreach ($products as $p) {
                $sku = strtoupper(preg_replace('/^XLS-/i', '', (string)$p['sku']));
                if (in_array($sku, array_map('strtoupper', $exclude_skus), true)) continue;

                $current = (float)$p['unit_price'];
                $new = $type === 'percentage' ? round($current * (1 + $value / 100), 0) : round($current + $value, 0);
                
                try {
                    $upstmt = $pdo->prepare("UPDATE products SET unit_price = ? WHERE id = ?");
                    $upstmt->execute([$new, (int)$p['id']]);
                    $affect_count++;
                } catch (Exception $e1) {
                    try {
                        $upstmt = $pdo->prepare("UPDATE products SET sell_price = ? WHERE id = ?");
                        $upstmt->execute([$new, (int)$p['id']]);
                        $affect_count++;
                    } catch(Exception $e2) {}
                }
            }

            $response = [
                'success' => true,
                'message' => "Precios actualizados en {$affect_count} productos",
                'count' => $affect_count
            ];
            break;

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }

} catch (Exception $e) {
    error_log("Products API Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
    if (($_SESSION['role'] ?? '') === 'admin') {
        $response['debug'] = [
            'action' => (string)$action,
            'detail' => (string)$e->getMessage()
        ];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
