<?php
/**
 * API para búsqueda de productos por código de barras
 */

require_once '../../config/config.php';
require_once '../../src/models/Product.php';


header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$decodedInput = json_decode($rawInput, true);
$input = is_array($decodedInput) ? $decodedInput : (is_array($_POST) ? $_POST : []);

function display_product_code($sku) {
    return preg_replace('/^\s*XLS-/i', '', (string)$sku);
}

function image_priority_score_products_api($fileName) {
    $name = strtoupper((string)pathinfo((string)$fileName, PATHINFO_FILENAME));
    if (preg_match('/\+FC1$/', $name)) {
        return 0;
    }
    if (preg_match('/\+E1$/', $name)) {
        return 1;
    }
    if (preg_match('/\+D1$/', $name)) {
        return 2;
    }
    if (preg_match('/\+O\d+$/', $name)) {
        return 3;
    }
    if (strpos($name, '+') === false) {
        return 50;
    }
    return 90;
}

function resolve_product_image_from_catalog($sku, $currentImage = ''): string {
    $currentImage = trim((string)$currentImage);
    if ($currentImage !== '' && strcasecmp($currentImage, 'images/products/default-product.svg') !== 0) {
        return $currentImage;
    }

    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $baseDir = __DIR__ . '/../images/products/by_code';
        if (is_dir($baseDir)) {
            $dirs = scandir($baseDir);
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }

                $fullDir = $baseDir . '/' . $dir;
                if (!is_dir($fullDir)) {
                    continue;
                }

                $matches = glob($fullDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
                if (empty($matches)) {
                    continue;
                }

                usort($matches, function ($a, $b) {
                    $scoreA = image_priority_score_products_api($a);
                    $scoreB = image_priority_score_products_api($b);
                    if ($scoreA === $scoreB) {
                        return strcmp((string)$a, (string)$b);
                    }
                    return $scoreA <=> $scoreB;
                });

                $cache[$dir] = 'images/products/by_code/' . $dir . '/' . basename((string)$matches[0]);
            }
        }
    }

    $normalizedSku = display_product_code($sku);
    return $cache[$normalizedSku] ?? 'images/products/default-product.svg';
}

function normalize_product_sku_for_response(array $product) {
    if (array_key_exists('sku', $product)) {
        $product['sku'] = display_product_code($product['sku']);
    }

    foreach (['name', 'description', 'technical_specs', 'category'] as $textField) {
        if (array_key_exists($textField, $product)) {
            $product[$textField] = decode_legacy_entities((string)$product[$textField]);
        }
    }

    $product['image_url'] = resolve_product_image_from_catalog(
        $product['sku'] ?? '',
        $product['image_url'] ?? ''
    );

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

            $channel = sanitize($_GET['channel'] ?? ($_GET['mode'] ?? 'all'));
            $include_inactive_categories = isset($_GET['include_inactive_categories']) || isset($_GET['all']);
            if ($include_inactive_categories) {
                $search = "%$term%";
                if ($channel === 'online') {
                    $searchPriceExpr = db_column_exists('products', 'price_online')
                        ? "COALESCE(NULLIF(price_online,0), unit_price, sell_price, 0)"
                        : "COALESCE(unit_price, sell_price, 0)";
                    $channelFilter = "AND (CASE WHEN show_in_online IS NULL THEN 1 WHEN LOWER(CAST(show_in_online AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
                } elseif ($channel === 'pos') {
                    $searchPriceExpr = db_column_exists('products', 'price_pos')
                        ? "COALESCE(NULLIF(price_pos,0), unit_price, sell_price, 0)"
                        : "COALESCE(unit_price, sell_price, 0)";
                    $channelFilter = "AND (CASE WHEN show_in_pos IS NULL THEN 1 WHEN LOWER(CAST(show_in_pos AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
                } else {
                    $searchPriceExpr = "COALESCE(NULLIF(price_online,0), NULLIF(price_pos,0), unit_price, sell_price, 0)";
                    $channelFilter = "";
                }
                $queries = [
                    ["SELECT id, name, sku, {$searchPriceExpr} AS unit_price, category, COALESCE(image_url, 'images/products/default-product.svg') AS image_url FROM products WHERE is_active = true {$channelFilter} AND (name ILIKE ? OR sku ILIKE ? OR barcode ILIKE ?) ORDER BY name LIMIT 200", [$search, $search, $search]],
                    ["SELECT id, name, sku, {$searchPriceExpr} AS unit_price, category, COALESCE(image_url, 'images/products/default-product.svg') AS image_url FROM products WHERE CAST(active AS text) IN ('1', 'true', 't') {$channelFilter} AND (name ILIKE ? OR sku ILIKE ? OR barcode ILIKE ?) ORDER BY name LIMIT 200", [$search, $search, $search]]
                ];
                $products = [];
                foreach ($queries as $qSpec) {
                    try {
                        $stmt = $pdo->prepare($qSpec[0]);
                        $stmt->execute($qSpec[1]);
                        $products = $stmt->fetchAll();
                        if (is_array($products)) {
                            break;
                        }
                    } catch (Exception $e) {}
                }
            } else {
                $products = $productModel->search($term, $channel);
            }

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

            $channel = sanitize($_GET['channel'] ?? ($_GET['mode'] ?? 'all'));
            if ($channel === 'online') {
                $onlineCond = db_column_exists('products', 'show_in_online')
                    ? "(CASE WHEN show_in_online IS NULL THEN (CASE WHEN is_active IS NULL THEN true WHEN LOWER(CAST(is_active AS TEXT)) IN ('1','t','true') THEN true ELSE false END) WHEN LOWER(CAST(show_in_online AS TEXT)) IN ('1','t','true') THEN true ELSE false END) = true"
                    : "is_active = true";
            } elseif ($channel === 'pos') {
                $onlineCond = db_column_exists('products', 'show_in_pos')
                    ? "(CASE WHEN show_in_pos IS NULL THEN (CASE WHEN is_active IS NULL THEN true WHEN LOWER(CAST(is_active AS TEXT)) IN ('1','t','true') THEN true ELSE false END) WHEN LOWER(CAST(show_in_pos AS TEXT)) IN ('1','t','true') THEN true ELSE false END) = true"
                    : "is_active = true";
            } else {
                $onlineCond = "(CASE WHEN is_active IS NULL THEN true WHEN LOWER(CAST(is_active AS TEXT)) IN ('1','t','true') THEN true ELSE false END) = true";
            }

            // Precio según canal: usar price_online / price_pos si aplica y existe
            if ($channel === 'online' && db_column_exists('products', 'price_online')) {
                $channelPriceExpr = "COALESCE(NULLIF(price_online,0), unit_price, sell_price, 0)";
            } elseif ($channel === 'pos' && db_column_exists('products', 'price_pos')) {
                $channelPriceExpr = "COALESCE(NULLIF(price_pos,0), unit_price, sell_price, 0)";
            } else {
                $channelPriceExpr = "COALESCE(unit_price, sell_price, 0)";
            }

            $products = [];
            $queries = [];
            if ($category !== '') {
                $categoryCond = "";
                if (!$include_inactive_categories) {
                    $categoryCond = "AND NOT EXISTS (SELECT 1 FROM product_categories pc WHERE LOWER(pc.name) = LOWER(products.category) AND pc.is_active = false)";
                }
                $queries[] = [
                    "SELECT id, name, sku, {$channelPriceExpr} AS unit_price, category, COALESCE(image_url, 'images/products/default-product.svg') AS image_url FROM products WHERE {$onlineCond} AND category = ? {$categoryCond} ORDER BY name LIMIT 200",
                    [$category]
                ];
                $queries[] = [
                    "SELECT id, name, sku, COALESCE(sell_price, unit_price, 0) AS unit_price, category, COALESCE(image_url, 'images/products/default-product.svg') AS image_url FROM products WHERE CAST(active AS text) IN ('1', 'true', 't') AND category = ? {$categoryCond} ORDER BY name LIMIT 200",
                    [$category]
                ];
            } else {
                $categoryCond = "";
                if (!$include_inactive_categories) {
                    $categoryCond = "AND NOT EXISTS (SELECT 1 FROM product_categories pc WHERE LOWER(pc.name) = LOWER(products.category) AND pc.is_active = false)";
                }
                $queries[] = [
                    "SELECT id, name, sku, {$channelPriceExpr} AS unit_price, category, COALESCE(image_url, 'images/products/default-product.svg') AS image_url FROM products WHERE {$onlineCond} {$categoryCond} ORDER BY name LIMIT 200",
                    []
                ];
                $queries[] = [
                    "SELECT id, name, sku, COALESCE(sell_price, unit_price, 0) AS unit_price, category, COALESCE(image_url, 'images/products/default-product.svg') AS image_url FROM products WHERE CAST(active AS text) IN ('1', 'true', 't') {$categoryCond} ORDER BY name LIMIT 200",
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
                $seed = function_exists('get_xlsx_seed_products') ? get_xlsx_seed_products() : [];
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
                            'category' => decode_legacy_entities($p['category']),
                            'image_url' => (string)($p['image_url'] ?? 'images/products/default-product.svg')
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
            $showOnlineCol = db_column_exists('products', 'show_in_online')
                ? "(CASE WHEN show_in_online IS NULL THEN 1 WHEN LOWER(CAST(show_in_online AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) AS show_in_online"
                : "1 AS show_in_online";
            $showPosCol = db_column_exists('products', 'show_in_pos')
                ? "(CASE WHEN show_in_pos IS NULL THEN 1 WHEN LOWER(CAST(show_in_pos AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) AS show_in_pos"
                : "1 AS show_in_pos";
            $queries = [
                ["SELECT id, name, sku, unit_price, (CASE WHEN is_active IS NULL THEN 1 WHEN LOWER(CAST(is_active AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) AS is_active, {$showOnlineCol}, {$showPosCol} FROM products ORDER BY name LIMIT 500", []],
                ["SELECT id, name, sku, sell_price AS unit_price, (CASE WHEN active = 1 THEN 1 ELSE 0 END) AS is_active, {$showOnlineCol}, {$showPosCol} FROM products ORDER BY name LIMIT 500", []]
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
            // Canal opcional: 'online' | 'pos' | 'all' (default). Permite ocultar por canal.
            $channel = strtolower(trim((string)($input['channel'] ?? 'all')));
            if (!in_array($channel, ['online', 'pos', 'all'], true)) {
                $channel = 'all';
            }

            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'ID inválido'];
                break;
            }

            $updated = false;
            $valBool = $is_active ? true : false;
            $valInt = $is_active ? 1 : 0;
            $hasOnline = db_column_exists('products', 'show_in_online');
            $hasPos = db_column_exists('products', 'show_in_pos');

            try {
                if (($channel === 'online' || $channel === 'all') && $hasOnline) {
                    $stmt = $pdo->prepare("UPDATE products SET show_in_online = ? WHERE id = ?");
                    $stmt->execute([$valBool, $id]);
                }
                if (($channel === 'pos' || $channel === 'all') && $hasPos) {
                    $stmt = $pdo->prepare("UPDATE products SET show_in_pos = ? WHERE id = ?");
                    $stmt->execute([$valBool, $id]);
                }
                // is_active refleja si el producto está visible en al menos un canal
                if ($channel === 'all') {
                    $stmt = $pdo->prepare("UPDATE products SET is_active = ? WHERE id = ?");
                    $stmt->execute([$valBool, $id]);
                } elseif ($hasOnline || $hasPos) {
                    $stmt = $pdo->prepare("UPDATE products SET is_active = (COALESCE(show_in_online, true) OR COALESCE(show_in_pos, true)) WHERE id = ?");
                    $stmt->execute([$id]);
                }
                $updated = true;
            } catch (Exception $e1) {
                try {
                    $stmt = $pdo->prepare("UPDATE products SET active = ? WHERE id = ?");
                    $stmt->execute([$valInt, $id]);
                    $updated = true;
                } catch (Exception $e2) {}
            }

            $channelLabel = $channel === 'online' ? 'Tienda en Línea' : ($channel === 'pos' ? 'Tienda Local (POS)' : 'todos los canales');
            $response = [
                'success' => $updated,
                'message' => $updated
                    ? ($is_active ? "Producto visible en {$channelLabel}" : "Producto oculto en {$channelLabel}")
                    : 'No se pudo actualizar'
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
            $target = $input['target'] ?? 'stock';
            $filter_mode = $input['filter_mode'] ?? 'exclude';

            $table = $target === 'marketplace' ? 'marketplace_ce_products' : 'products';

            $netPriceSelect = db_column_exists($table, 'net_price')
                ? "COALESCE(net_price, unit_price, 0) AS net_price"
                : "unit_price AS net_price";
            $discountSelect = db_column_exists($table, 'discount_percentage')
                ? "COALESCE(discount_percentage, 0) AS discount_percentage"
                : "0 AS discount_percentage";

            if ($target === 'marketplace') {
                $stmt = $pdo->prepare("SELECT id, name, sku, unit_price, {$netPriceSelect}, {$discountSelect} FROM marketplace_ce_products WHERE is_active = true ORDER BY name LIMIT 500");
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare("SELECT id, name, sku, unit_price, {$netPriceSelect}, {$discountSelect} FROM products WHERE is_active = true ORDER BY name LIMIT 500");
                if (!$stmt->execute()) {
                    $stmt = $pdo->prepare("SELECT id, name, sku, sell_price AS unit_price, sell_price AS net_price, 0 AS discount_percentage FROM products ORDER BY name LIMIT 500");
                    $stmt->execute();
                }
            }
            $products = $stmt->fetchAll();

            $preview = [];
            $count = 0;
            foreach ($products as $p) {
                $sku = strtoupper(preg_replace('/^XLS-/i', '', (string)$p['sku']));
                $in_list = in_array($sku, array_map('strtoupper', $exclude_skus), true);
                if ($filter_mode === 'include') {
                    if (!$in_list) continue;
                } else {
                    if ($in_list) continue;
                }

                $current = (float)$p['unit_price'];
                $net_price = (float)($p['net_price'] ?? $current);
                $discount_percentage = (float)($p['discount_percentage'] ?? 0);

                if ($type === 'discount') {
                    $new_discount = $value;
                    $new_net = $net_price;
                    $new = round($new_net * (1 - $new_discount / 100), 2);
                } else {
                    $new_discount = $discount_percentage;
                    $new_net = $type === 'percentage' ? round($net_price * (1 + $value / 100), 2) : round($net_price + $value, 2);
                    $new = round($new_net * (1 - $new_discount / 100), 2);
                }
                
                $count++;
                if (count($preview) < 5) {
                    $preview[] = [
                        'name' => $p['name'],
                        'sku' => $p['sku'],
                        'current_price' => $current,
                        'net_price' => $net_price,
                        'discount_percentage' => $discount_percentage,
                        'new_price' => $new,
                        'new_net_price' => $new_net,
                        'new_discount' => $new_discount
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
            $target = $input['target'] ?? 'stock';
            $filter_mode = $input['filter_mode'] ?? 'exclude';
            $affect_count = 0;

            $table = $target === 'marketplace' ? 'marketplace_ce_products' : 'products';

            $netPriceSelect = db_column_exists($table, 'net_price')
                ? "COALESCE(net_price, unit_price, 0) AS net_price"
                : "unit_price AS net_price";
            $discountSelect = db_column_exists($table, 'discount_percentage')
                ? "COALESCE(discount_percentage, 0) AS discount_percentage"
                : "0 AS discount_percentage";

            $stmt = $pdo->prepare("SELECT id, sku, unit_price, {$netPriceSelect}, {$discountSelect} FROM {$table}");
            if ($target === 'stock') {
                if (!$stmt->execute()) {
                    $stmt = $pdo->prepare("SELECT id, sku, sell_price AS unit_price, sell_price AS net_price, 0 AS discount_percentage FROM products");
                    $stmt->execute();
                }
            } else {
                $stmt->execute();
            }
            $products = $stmt->fetchAll();

            foreach ($products as $p) {
                $sku = strtoupper(preg_replace('/^XLS-/i', '', (string)$p['sku']));
                $in_list = in_array($sku, array_map('strtoupper', $exclude_skus), true);
                if ($filter_mode === 'include') {
                    if (!$in_list) continue;
                } else {
                    if ($in_list) continue;
                }

                $current = (float)$p['unit_price'];
                $net_price = (float)($p['net_price'] ?? $current);
                $discount_percentage = (float)($p['discount_percentage'] ?? 0);

                if ($type === 'discount') {
                    $new_discount = $value;
                    $new_net = $net_price;
                    $new = round($new_net * (1 - $new_discount / 100), 2);
                } else {
                    $new_discount = $discount_percentage;
                    $new_net = $type === 'percentage' ? round($net_price * (1 + $value / 100), 2) : round($net_price + $value, 2);
                    $new = round($new_net * (1 - $new_discount / 100), 2);
                }

                $setParts = [];
                $params = [];

                if (db_column_exists($table, 'unit_price')) {
                    $setParts[] = "unit_price = ?";
                    $params[] = $new;
                } elseif (db_column_exists($table, 'sell_price')) {
                    $setParts[] = "sell_price = ?";
                    $params[] = $new;
                }

                if (db_column_exists($table, 'net_price')) {
                    $setParts[] = "net_price = ?";
                    $params[] = $new_net;
                }

                if (db_column_exists($table, 'discount_percentage')) {
                    $setParts[] = "discount_percentage = ?";
                    $params[] = $new_discount;
                }

                if (!empty($setParts)) {
                    $params[] = (int)$p['id'];
                    $upstmt = $pdo->prepare("UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE id = ?");
                    $upstmt->execute($params);
                    $affect_count++;
                }
            }

            $response = [
                'success' => true,
                'message' => "Precios actualizados en {$affect_count} productos (" . ($target === 'marketplace' ? 'Marketplace' : 'Stock') . ")",
                'count' => $affect_count
            ];
            break;

        case 'revert-to-net-prices':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $target = $input['target'] ?? 'stock';
            $exclude_skus = $input['exclude_skus'] ?? [];
            $filter_mode = $input['filter_mode'] ?? 'exclude';
            $affect_count = 0;

            $table = $target === 'marketplace' ? 'marketplace_ce_products' : 'products';

            // Fetch all products that have a net_price
            $stmt = $pdo->prepare("SELECT id, sku FROM {$table} WHERE net_price IS NOT NULL");
            if ($target === 'stock') {
                if (!$stmt->execute()) {
                    $stmt = $pdo->prepare("SELECT id, sku FROM products");
                    $stmt->execute();
                }
            } else {
                $stmt->execute();
            }
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $sku = strtoupper(preg_replace('/^XLS-/i', '', (string)$item['sku']));
                $in_list = in_array($sku, array_map('strtoupper', $exclude_skus), true);
                if ($filter_mode === 'include') {
                    if (!$in_list) continue;
                } else {
                    if ($in_list) continue;
                }

                $setParts = [];
                $params = [];

                if (db_column_exists($table, 'unit_price')) {
                    $setParts[] = "unit_price = net_price";
                } elseif (db_column_exists($table, 'sell_price')) {
                    $setParts[] = "sell_price = net_price";
                }

                if (db_column_exists($table, 'discount_percentage')) {
                    $setParts[] = "discount_percentage = 0";
                }

                if (!empty($setParts)) {
                    $params[] = (int)$item['id'];
                    $upstmt = $pdo->prepare("UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE id = ?");
                    $upstmt->execute($params);
                    $affect_count++;
                }
            }

            $response = [
                'success' => true,
                'message' => "Precios neto restaurados en {$affect_count} productos (" . ($target === 'marketplace' ? 'Marketplace' : 'Stock') . ")",
                'count' => $affect_count
            ];
            break;

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }

} catch (Exception $e) {
    error_log("Products API Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
    if (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'employee') {
        $response['debug'] = [
            'action' => (string)$action,
            'detail' => (string)$e->getMessage()
        ];
    }
}

// Clear persistent cache on successful POST operations (writes)
if ($method === 'POST' && isset($response) && is_array($response) && ($response['success'] ?? false) === true) {
    if (function_exists('cache_clear')) {
        cache_clear();
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
