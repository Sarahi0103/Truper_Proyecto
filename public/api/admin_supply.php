<?php
require_once '../../config/config.php';
ini_set('display_errors', '0');
ob_start();

require_admin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'stock';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function normalize_date_value($value): ?string {
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }

    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }

    $ts = strtotime($raw);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return null;
}

function normalize_sku_admin_supply($value): string {
    $sku = trim((string)$value);
    return preg_replace('/\D+/', '', $sku) ?: '';
}

function is_valid_numeric_sku_admin_supply(string $sku): bool {
    return (bool)preg_match('/^\d{5}$/', $sku);
}

function normalized_sku_exists_in_table_admin_supply($pdo, string $table, string $sku, int $excludeId = 0): bool {
    if ($sku === '') {
        return false;
    }

    if (!in_array($table, ['products', 'marketplace_ce_products'], true)) {
        return false;
    }

    try {
        if ($excludeId > 0) {
            $stmt = $pdo->prepare("SELECT id, sku FROM {$table} WHERE id <> ?");
            $stmt->execute([max(0, $excludeId)]);
        } else {
            $stmt = $pdo->query("SELECT id, sku FROM {$table}");
        }

        $rows = $stmt ? $stmt->fetchAll() : [];
        foreach ($rows as $row) {
            $existingSku = normalize_sku_admin_supply($row['sku'] ?? '');
            if ($existingSku !== '' && $existingSku === $sku) {
                return true;
            }
        }

        return false;
    } catch (Exception $ignored) {
        return false;
    }
}

function product_sku_exists_admin_supply($pdo, string $sku, int $excludeId = 0): bool {
    if ($sku === '') {
        return false;
    }

    return normalized_sku_exists_in_table_admin_supply($pdo, 'products', $sku, $excludeId);
}

function marketplace_sku_exists_admin_supply($pdo, string $sku, int $excludeId = 0): bool {
    if ($sku === '') {
        return false;
    }

    return normalized_sku_exists_in_table_admin_supply($pdo, 'marketplace_ce_products', $sku, $excludeId);
}

function seed_sku_exists_admin_supply(string $sku): bool {
    if ($sku === '' || !function_exists('get_xlsx_seed_products')) {
        return false;
    }

    try {
        $items = get_xlsx_seed_products();
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            $seedSku = normalize_sku_admin_supply($item['sku'] ?? '');
            if ($seedSku !== '' && $seedSku === $sku) {
                return true;
            }
        }
    } catch (Exception $ignored) {
        return false;
    }

    return false;
}

function sku_usage_admin_supply($pdo, string $sku, int $excludeMarketplaceId = 0, int $excludeProductId = 0): array {
    return [
        'in_products' => product_sku_exists_admin_supply($pdo, $sku, $excludeProductId),
        'in_marketplace' => marketplace_sku_exists_admin_supply($pdo, $sku, $excludeMarketplaceId),
        'in_seed' => seed_sku_exists_admin_supply($sku),
    ];
}

function record_matches_normalized_sku_admin_supply($pdo, string $table, int $id, string $sku): bool {
    if ($id <= 0 || $sku === '') {
        return false;
    }

    if (!in_array($table, ['products', 'marketplace_ce_products'], true)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT sku FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $existingSku = normalize_sku_admin_supply((string)$stmt->fetchColumn());
        return $existingSku !== '' && $existingSku === $sku;
    } catch (Exception $ignored) {
        return false;
    }
}

function ensure_admin_supply_tables($pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_calendar (
        id SERIAL PRIMARY KEY,
        supplier_name VARCHAR(180) NOT NULL,
        visit_datetime TIMESTAMP NOT NULL,
        notes TEXT,
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_orders (
        id SERIAL PRIMARY KEY,
        folio VARCHAR(50) UNIQUE NOT NULL,
        supplier_name VARCHAR(180) NOT NULL,
        expected_date DATE NOT NULL,
        items_json TEXT NOT NULL,
        total_estimated DECIMAL(12, 2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'pending',
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS transaction_history (
        id SERIAL PRIMARY KEY,
        transaction_type VARCHAR(40) NOT NULL,
        reference_folio VARCHAR(80) NOT NULL,
        data_json TEXT,
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS product_suppliers (
        id SERIAL PRIMARY KEY,
        product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
        supplier_name VARCHAR(180) NOT NULL,
        supplier_sku VARCHAR(100),
        unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS homepage_updates (
        id SERIAL PRIMARY KEY,
        update_type VARCHAR(20) NOT NULL DEFAULT 'noticia',
        title VARCHAR(220) NOT NULL,
        body TEXT NOT NULL,
        image_url TEXT,
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_active BOOLEAN NOT NULL DEFAULT true,
        created_by INTEGER REFERENCES users(id),
        updated_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CHECK (update_type IN ('noticia', 'promocion', 'evento'))
    )");
    
    // Add image_url column if it doesn't exist (migration for existing tables)
    try {
        $pdo->exec("ALTER TABLE homepage_updates ADD COLUMN IF NOT EXISTS image_url TEXT");
    } catch (Exception $ignored) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS product_categories (
        id SERIAL PRIMARY KEY,
        name VARCHAR(120) NOT NULL UNIQUE,
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_active BOOLEAN NOT NULL DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed initial categories if table is empty.
    try {
        $seedCount = (int)$pdo->query("SELECT COUNT(*) FROM product_categories")->fetchColumn();
        if ($seedCount === 0) {
            $seedStmt = $pdo->prepare("INSERT INTO product_categories (name, sort_order, is_active) VALUES (?, ?, true)");
            $seedStmt->execute(['Material eléctrico', 10]);
            $seedStmt->execute(['Fontanería', 20]);
            $seedStmt->execute(['Cerrajería', 30]);
            $seedStmt->execute(['Herrería', 40]);
        }
    } catch (Exception $ignored) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_ce_products (
        id SERIAL PRIMARY KEY,
        sku VARCHAR(100) UNIQUE NOT NULL,
        name VARCHAR(220) NOT NULL,
        description TEXT NOT NULL,
        condition_label VARCHAR(80) NOT NULL DEFAULT 'Seminuevo',
        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        stock_quantity INTEGER NOT NULL DEFAULT 1,
        image_url TEXT,
        is_active BOOLEAN NOT NULL DEFAULT true,
        created_by INTEGER REFERENCES users(id),
        updated_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
}

function ensure_products_extra_columns($pdo): void {
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS technical_specs TEXT");
        $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS image_url TEXT");
        $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS variants_json TEXT");
        $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS stock_quantity INTEGER DEFAULT 0");
        $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS reorder_level INTEGER DEFAULT 10");
        $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true");
    } catch (Exception $ignored) {
        // En esquemas legados puede fallar; se maneja con inserciones alternativas.
    }
}

function store_product_image(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'images/products/default-product.svg';
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new Exception('Archivo de imagen inválido');
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $original = (string)($file['name'] ?? 'image');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new Exception('Formato de imagen no permitido');
    }

    $targetDir = __DIR__ . '/../images/products';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($original, PATHINFO_FILENAME));
    $safeBase = trim((string)$safeBase, '-');
    if ($safeBase === '') {
        $safeBase = 'product';
    }

    $filename = $safeBase . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $targetPath)) {
        throw new Exception('No se pudo guardar la imagen');
    }

    return 'images/products/' . $filename;
}

function normalize_uploaded_files(array $files): array {
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [$files];
    }

    $normalized = [];
    $count = count($files['name']);
    for ($index = 0; $index < $count; $index += 1) {
        $normalized[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return $normalized;
}

function create_product_compatible($pdo, array $payload): void {
    $columns = [];
    $values = [];

    $required = ['sku', 'name'];
    foreach ($required as $col) {
        if (!db_column_exists('products', $col)) {
            throw new Exception('La tabla products no tiene la columna requerida: ' . $col);
        }
        $columns[] = $col;
        $values[] = $payload[$col];
    }

    if (db_column_exists('products', 'description')) {
        $columns[] = 'description';
        $values[] = $payload['description'];
    }
    if (db_column_exists('products', 'category')) {
        $columns[] = 'category';
        $values[] = $payload['category'];
    }
    if (db_column_exists('products', 'barcode')) {
        $columns[] = 'barcode';
        $values[] = $payload['barcode'];
    }
    if (db_column_exists('products', 'image_url')) {
        $columns[] = 'image_url';
        $values[] = $payload['image_url'];
    }
    if (db_column_exists('products', 'technical_specs')) {
        $columns[] = 'technical_specs';
        $values[] = 'N/A';
    }
    if (db_column_exists('products', 'variants_json')) {
        $columns[] = 'variants_json';
        $values[] = '[]';
    }
    if (db_column_exists('products', 'stock_quantity')) {
        $columns[] = 'stock_quantity';
        $values[] = (int)$payload['stock_quantity'];
    }
    if (db_column_exists('products', 'reorder_level')) {
        $columns[] = 'reorder_level';
        $values[] = (int)$payload['reorder_level'];
    }

    if (db_column_exists('products', 'unit_price')) {
        $columns[] = 'unit_price';
        $values[] = (float)$payload['price'];
    } elseif (db_column_exists('products', 'sell_price')) {
        $columns[] = 'sell_price';
        $values[] = (float)$payload['price'];
    }

    if (db_column_exists('products', 'is_active')) {
        $columns[] = 'is_active';
        $values[] = true;
    } elseif (db_column_exists('products', 'active')) {
        $columns[] = 'active';
        $values[] = 1;
    }

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

function update_product_compatible($pdo, int $id, array $payload): void {
    if ($id <= 0) {
        throw new Exception('ID de producto inválido');
    }

    $sets = [];
    $values = [];

    if (db_column_exists('products', 'sku')) { $sets[] = 'sku = ?'; $values[] = $payload['sku']; }
    if (db_column_exists('products', 'name')) { $sets[] = 'name = ?'; $values[] = $payload['name']; }
    if (db_column_exists('products', 'description')) { $sets[] = 'description = ?'; $values[] = $payload['description']; }
    if (db_column_exists('products', 'category')) { $sets[] = 'category = ?'; $values[] = $payload['category']; }
    if (db_column_exists('products', 'barcode')) { $sets[] = 'barcode = ?'; $values[] = $payload['barcode']; }
    if (db_column_exists('products', 'image_url')) { $sets[] = 'image_url = ?'; $values[] = $payload['image_url']; }
    if (db_column_exists('products', 'stock_quantity')) { $sets[] = 'stock_quantity = ?'; $values[] = (int)$payload['stock_quantity']; }
    if (db_column_exists('products', 'reorder_level')) { $sets[] = 'reorder_level = ?'; $values[] = (int)$payload['reorder_level']; }
    if (db_column_exists('products', 'unit_price')) { $sets[] = 'unit_price = ?'; $values[] = (float)$payload['price']; }
    elseif (db_column_exists('products', 'sell_price')) { $sets[] = 'sell_price = ?'; $values[] = (float)$payload['price']; }
    if (db_column_exists('products', 'updated_at')) { $sets[] = 'updated_at = CURRENT_TIMESTAMP'; }

    if (empty($sets)) {
        throw new Exception('No hay columnas disponibles para actualizar el producto');
    }

    $values[] = $id;
    $stmt = $pdo->prepare('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($values);
}

function deactivate_product_compatible($pdo, int $id): void {
    if ($id <= 0) {
        throw new Exception('ID de producto inválido');
    }

    if (db_column_exists('products', 'is_active')) {
        $stmt = $pdo->prepare('UPDATE products SET is_active = false WHERE id = ?');
        $stmt->execute([$id]);
        return;
    }

    if (db_column_exists('products', 'active')) {
        $stmt = $pdo->prepare('UPDATE products SET active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);
}

function ensure_products_seeded_for_admin_supply($pdo): void {
    if (!function_exists('get_xlsx_seed_products')) {
        return;
    }

    try {
        $countStmt = $pdo->query('SELECT COUNT(*) FROM products');
        $count = (int)$countStmt->fetchColumn();
        if ($count >= 10) {
            return;
        }

        $seedProducts = get_xlsx_seed_products();
        if (!is_array($seedProducts) || empty($seedProducts)) {
            return;
        }

        foreach ($seedProducts as $seed) {
            $sku = (string)($seed['sku'] ?? '');
            if ($sku === '' || product_sku_exists_admin_supply($pdo, normalize_sku_admin_supply($sku))) {
                continue;
            }

            try {
                create_product_compatible($pdo, [
                    'sku' => $sku,
                    'name' => (string)($seed['name'] ?? 'Producto'),
                    'category' => (string)($seed['category'] ?? 'General'),
                    'description' => (string)($seed['description'] ?? ''),
                    'barcode' => (string)($seed['barcode'] ?? ''),
                    'price' => (float)($seed['unit_price'] ?? 0),
                    'stock_quantity' => (int)($seed['stock_quantity'] ?? 50),
                    'reorder_level' => 10,
                    'image_url' => (string)($seed['image_url'] ?? 'images/products/default-product.svg')
                ]);
            } catch (Exception $ignored) {
                // Continue trying next seed row.
            }
        }
    } catch (Exception $ignored) {
        // Best-effort seeding only.
    }
}

function bootstrap_admin_supply_schema($pdo): void {
    try {
        ensure_admin_supply_tables($pdo);
    } catch (Throwable $e) {
        error_log('admin_supply bootstrap warning (tables): ' . $e->getMessage());
    }

    try {
        ensure_products_extra_columns($pdo);
    } catch (Throwable $e) {
        error_log('admin_supply bootstrap warning (product columns): ' . $e->getMessage());
    }

    try {
        if (function_exists('ensure_xlsx_products_seeded')) {
            ensure_xlsx_products_seeded();
        }
    } catch (Throwable $e) {
        error_log('admin_supply bootstrap warning (xlsx seed): ' . $e->getMessage());
    }

    try {
        ensure_products_seeded_for_admin_supply($pdo);
    } catch (Throwable $e) {
        error_log('admin_supply bootstrap warning (admin seed ensure): ' . $e->getMessage());
    }
}

bootstrap_admin_supply_schema($pdo);

function list_available_product_images($pdo): array {
    $images = ['images/products/default-product.svg'];

    $dir = __DIR__ . '/../images/products';
    if (is_dir($dir)) {
        $entries = scandir($dir);
        if (is_array($entries)) {
            foreach ($entries as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
                    $images[] = 'images/products/' . $name;
                }
            }
        }
    }

    try {
        if (db_column_exists('products', 'image_url')) {
            $stmt = $pdo->query("SELECT DISTINCT image_url FROM products WHERE image_url IS NOT NULL AND image_url <> '' LIMIT 500");
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                if (!empty($row['image_url'])) {
                    $images[] = (string)$row['image_url'];
                }
            }
        }
    } catch (Exception $ignored) {
    }

    $images = array_values(array_unique($images));
    sort($images);
    return $images;
}

function list_stock_products_compatible($pdo): array {
    $categorySelect = db_column_exists('products', 'category')
        ? "COALESCE(category, 'General') AS category"
        : "'General' AS category";
    $descriptionSelect = db_column_exists('products', 'description')
        ? "COALESCE(description, '') AS description"
        : "'' AS description";
    $stockSelect = db_column_exists('products', 'stock_quantity')
        ? "COALESCE(stock_quantity, 0) AS stock_quantity"
        : "0 AS stock_quantity";
    $reorderSelect = db_column_exists('products', 'reorder_level')
        ? "COALESCE(reorder_level, 10) AS reorder_level"
        : "10 AS reorder_level";
    $imageSelect = db_column_exists('products', 'image_url')
        ? "COALESCE(image_url, 'images/products/default-product.svg') AS image_url"
        : "'images/products/default-product.svg' AS image_url";
    $priceSelect = db_column_exists('products', 'unit_price')
        ? "COALESCE(unit_price, 0) AS unit_price"
        : (db_column_exists('products', 'sell_price') ? "COALESCE(sell_price, 0) AS unit_price" : "0 AS unit_price");
    $isActiveSelect = db_column_exists('products', 'is_active')
        ? "COALESCE(is_active, true) AS is_active"
        : (db_column_exists('products', 'active') ? "(active = 1) AS is_active" : "true AS is_active");

    $queries = [];

    if (db_column_exists('products', 'is_active')) {
        $queries[] = "SELECT id, sku, name, {$descriptionSelect}, {$categorySelect}, {$stockSelect}, {$reorderSelect}, {$priceSelect}, {$imageSelect}, {$isActiveSelect} FROM products WHERE is_active = true ORDER BY stock_quantity ASC, name ASC LIMIT 500";
    }
    if (db_column_exists('products', 'active')) {
        $queries[] = "SELECT id, sku, name, {$descriptionSelect}, {$categorySelect}, {$stockSelect}, {$reorderSelect}, {$priceSelect}, {$imageSelect}, {$isActiveSelect} FROM products WHERE active = 1 ORDER BY stock_quantity ASC, name ASC LIMIT 500";
    }

    $queries[] = "SELECT id, sku, name, {$descriptionSelect}, {$categorySelect}, {$stockSelect}, {$reorderSelect}, {$priceSelect}, {$imageSelect}, {$isActiveSelect} FROM products ORDER BY stock_quantity ASC, name ASC LIMIT 500";

    $items = [];
    foreach ($queries as $sql) {
        try {
            $stmt = $pdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll() : [];
            if (is_array($rows) && count($rows) > 0) {
                $items = $rows;
                break;
            }
        } catch (Exception $ignored) {
        }
    }

    $existingSkus = [];
    foreach ($items as $row) {
        $normalized = normalize_sku_admin_supply($row['sku'] ?? '');
        if ($normalized !== '') {
            $existingSkus[$normalized] = true;
        }
    }

    if (function_exists('get_xlsx_seed_products')) {
        try {
            $seed = get_xlsx_seed_products();
            if (is_array($seed)) {
                foreach ($seed as $seedItem) {
                    $seedSku = normalize_sku_admin_supply($seedItem['sku'] ?? '');
                    if ($seedSku === '' || isset($existingSkus[$seedSku])) {
                        continue;
                    }

                    $items[] = [
                        'id' => (int)($seedItem['id'] ?? 0),
                        'sku' => (string)($seedItem['sku'] ?? ''),
                        'name' => (string)($seedItem['name'] ?? ''),
                        'description' => (string)($seedItem['description'] ?? ''),
                        'category' => (string)($seedItem['category'] ?? 'General'),
                        'stock_quantity' => (int)($seedItem['stock_quantity'] ?? 50),
                        'reorder_level' => (int)($seedItem['reorder_level'] ?? 10),
                        'unit_price' => (float)($seedItem['unit_price'] ?? 0),
                        'image_url' => (string)($seedItem['image_url'] ?? 'images/products/default-product.svg'),
                        'is_active' => true,
                        'seed_only' => true
                    ];

                    $existingSkus[$seedSku] = true;
                }
            }
        } catch (Throwable $ignored) {
        }
    }

    usort($items, function ($a, $b) {
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });

    return $items;
}

function ensure_numeric_client_user_code_admin_supply($pdo, int $userId): string {
    if (!db_column_exists('users', 'user_code')) {
        return str_pad((string)$userId, 9, '0', STR_PAD_LEFT);
    }

    try {
        $stmt = $pdo->prepare("SELECT user_code FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $raw = (string)$stmt->fetchColumn();
        $digits = preg_replace('/\D+/', '', $raw) ?: '';
        if ($digits !== '') {
            if ($digits !== $raw) {
                $fix = $pdo->prepare("UPDATE users SET user_code = ? WHERE id = ?");
                $fix->execute([$digits, $userId]);
            }
            return $digits;
        }
    } catch (Exception $ignored) {
    }

    $code = (string)random_int(100000000, 999999999);
    try {
        $update = $pdo->prepare("UPDATE users SET user_code = ? WHERE id = ?");
        $update->execute([$code, $userId]);
    } catch (Exception $ignored) {
    }

    return $code;
}

$response = ['success' => false, 'message' => 'Accion no reconocida'];

try {
    switch ($action) {
        case 'product-sku-check':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $sku = normalize_sku_admin_supply($_GET['sku'] ?? '');
            if ($sku === '') {
                $response = ['success' => false, 'message' => 'SKU requerido'];
                break;
            }

            if (!is_valid_numeric_sku_admin_supply($sku)) {
                $response = [
                    'success' => true,
                    'available' => false,
                    'message' => 'El código debe tener exactamente 5 números',
                    'sku' => $sku
                ];
                break;
            }

            $id = (int)($_GET['id'] ?? 0);
            $usage = sku_usage_admin_supply($pdo, $sku, 0, $id);
            $sameRecord = record_matches_normalized_sku_admin_supply($pdo, 'products', $id, $sku);
            $seedConflict = $usage['in_seed'] && !$sameRecord;
            $exists = $usage['in_products'] || $usage['in_marketplace'] || $seedConflict;
            $message = 'Código disponible';
            if ($exists) {
                $message = $usage['in_products']
                    ? 'Ya existe un producto con ese código'
                        : ($usage['in_marketplace']
                        ? 'Ya existe un artículo CE con ese código'
                        : 'Ese código ya existe en el catálogo base');
            }
            $response = [
                'success' => true,
                'available' => !$exists,
                'message' => $message,
                'sku' => $sku
            ];
            break;

        case 'marketplace-sku-check':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $sku = normalize_sku_admin_supply($_GET['sku'] ?? '');
            $id = (int)($_GET['id'] ?? 0);
            if ($sku === '') {
                $response = ['success' => false, 'message' => 'SKU requerido'];
                break;
            }

            if (!is_valid_numeric_sku_admin_supply($sku)) {
                $response = [
                    'success' => true,
                    'available' => false,
                    'message' => 'El código debe tener exactamente 5 números',
                    'sku' => $sku
                ];
                break;
            }

            $usage = sku_usage_admin_supply($pdo, $sku, $id);
            $sameRecord = record_matches_normalized_sku_admin_supply($pdo, 'marketplace_ce_products', $id, $sku);
            $seedConflict = $usage['in_seed'] && !$sameRecord;
            $exists = $usage['in_products'] || $usage['in_marketplace'] || $seedConflict;
            $message = 'Código disponible';
            if ($exists) {
                $message = $usage['in_marketplace']
                    ? 'Ya existe un artículo CE con ese código'
                        : ($usage['in_products']
                        ? 'Ya existe un producto con ese código'
                        : 'Ese código ya existe en el catálogo base');
            }
            $response = [
                'success' => true,
                'available' => !$exists,
                'message' => $message,
                'sku' => $sku
            ];
            break;

        case 'product-create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $sku = normalize_sku_admin_supply(sanitize($_POST['sku'] ?? ($input['sku'] ?? '')));
            $name = sanitize($_POST['name'] ?? ($input['name'] ?? ''));
            $category = sanitize($_POST['category'] ?? ($input['category'] ?? 'General'));
            $description = sanitize($_POST['description'] ?? ($input['description'] ?? ''));
            $barcode = sanitize($_POST['barcode'] ?? ($input['barcode'] ?? ''));
            $price = (float)($_POST['price'] ?? ($input['price'] ?? 0));
            $stockQty = (int)($_POST['stock_quantity'] ?? ($input['stock_quantity'] ?? 50));
            $reorder = (int)($_POST['reorder_level'] ?? ($input['reorder_level'] ?? 10));

            if ($sku === '' || $name === '') {
                $response = ['success' => false, 'message' => 'SKU y nombre son obligatorios'];
                break;
            }
            if (!is_valid_numeric_sku_admin_supply($sku)) {
                $response = ['success' => false, 'message' => 'El código del producto debe tener exactamente 5 números'];
                break;
            }
            if ($price < 0) {
                $response = ['success' => false, 'message' => 'Precio inválido'];
                break;
            }

            $usage = sku_usage_admin_supply($pdo, $sku, 0, 0);
            $seedConflict = $usage['in_seed'];
            if ($usage['in_products'] || $usage['in_marketplace'] || $seedConflict) {
                $response = [
                    'success' => false,
                    'message' => $usage['in_products']
                        ? 'Ya existe un producto con ese código'
                        : ($usage['in_marketplace']
                            ? 'Ese código ya está registrado en Marketplace CE'
                            : 'Ese código ya existe en el catálogo base')
                ];
                break;
            }

            $imageUrl = sanitize($_POST['image_url'] ?? ($input['image_url'] ?? 'images/products/default-product.svg'));
            if (isset($_FILES['image'])) {
                $imageUrl = store_product_image($_FILES['image']);
            } elseif (isset($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {
                $uploadedFiles = normalize_uploaded_files($_FILES['images']);
                foreach ($uploadedFiles as $uploadedFile) {
                    if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $imageUrl = store_product_image($uploadedFile);
                        break;
                    }
                }
            }

            create_product_compatible($pdo, [
                'sku' => $sku,
                'name' => $name,
                'category' => $category,
                'description' => $description,
                'barcode' => $barcode,
                'price' => $price,
                'stock_quantity' => max(0, $stockQty),
                'reorder_level' => max(0, $reorder),
                'image_url' => $imageUrl
            ]);

            log_action(
                $_SESSION['user_id'],
                'ADMIN_CREATE_PRODUCT',
                'Producto creado por admin: ' . $sku,
                getTrusSIDBug()
            );

            $response = [
                'success' => true,
                'message' => 'Producto registrado correctamente',
                'product' => [
                    'sku' => $sku,
                    'name' => $name,
                    'category' => $category,
                    'price' => $price,
                    'image_url' => $imageUrl
                ]
            ];
            break;

        case 'product-save':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($input['id'] ?? 0);
            $sku = normalize_sku_admin_supply(sanitize($input['sku'] ?? ''));
            $name = sanitize($input['name'] ?? '');
            $category = sanitize($input['category'] ?? 'General');
            $description = sanitize($input['description'] ?? '');
            $price = (float)($input['price'] ?? 0);
            $stockQty = (int)($input['stock_quantity'] ?? 50);
            $reorder = (int)($input['reorder_level'] ?? 10);
            $imageUrl = sanitize($input['image_url'] ?? 'images/products/default-product.svg');
            $isVisible = (int)(isset($input['is_visible']) ? (bool)$input['is_visible'] : true);

            if ($sku === '' || $name === '') {
                $response = ['success' => false, 'message' => 'SKU y nombre son obligatorios'];
                break;
            }
            if (!is_valid_numeric_sku_admin_supply($sku)) {
                $response = ['success' => false, 'message' => 'El código del producto debe tener exactamente 5 números'];
                break;
            }
            if ($price < 0 || $stockQty < 0 || $reorder < 0) {
                $response = ['success' => false, 'message' => 'Valores numéricos inválidos'];
                break;
            }

            $usage = sku_usage_admin_supply($pdo, $sku, 0, $id);
            $sameRecord = record_matches_normalized_sku_admin_supply($pdo, 'products', $id, $sku);
            $seedConflict = $usage['in_seed'] && !$sameRecord;
            if ($usage['in_products'] || $usage['in_marketplace'] || $seedConflict) {
                $response = [
                    'success' => false,
                    'message' => $usage['in_products']
                        ? 'Ya existe un producto con ese código'
                        : ($usage['in_marketplace']
                            ? 'Ese código ya está registrado en Marketplace CE'
                            : 'Ese código ya existe en el catálogo base')
                ];
                break;
            }

            if ($id > 0) {
                $check = $pdo->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
                $check->execute([$id]);
                if (!(int)$check->fetchColumn()) {
                    $response = ['success' => false, 'message' => 'Producto no encontrado'];
                    break;
                }

                update_product_compatible($pdo, $id, [
                    'sku' => $sku,
                    'name' => $name,
                    'category' => $category,
                    'description' => $description,
                    'price' => $price,
                    'stock_quantity' => max(0, $stockQty),
                    'reorder_level' => max(0, $reorder),
                    'image_url' => $imageUrl,
                    'is_active' => $isVisible
                ]);

                $response = [
                    'success' => true,
                    'message' => 'Producto actualizado correctamente',
                    'product' => [
                        'id' => $id,
                        'sku' => $sku,
                        'name' => $name
                    ]
                ];
                break;
            }

            create_product_compatible($pdo, [
                'sku' => $sku,
                'name' => $name,
                'category' => $category,
                'description' => $description,
                'price' => $price,
                'stock_quantity' => max(0, $stockQty),
                'reorder_level' => max(0, $reorder),
                'image_url' => $imageUrl,
                'is_active' => $isVisible
            ]);

            $response = [
                'success' => true,
                'message' => 'Producto registrado correctamente',
                'product' => [
                    'sku' => $sku,
                    'name' => $name,
                    'category' => $category,
                    'price' => $price,
                    'image_url' => $imageUrl
                ]
            ];
            break;

        case 'product-delete':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'Producto inválido'];
                break;
            }

            deactivate_product_compatible($pdo, $id);
            $response = ['success' => true, 'message' => 'Producto desactivado'];
            break;

        case 'product-image-upload':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $uploaded = [];
            $fileInput = $_FILES['images'] ?? $_FILES['image'] ?? null;
            if (!$fileInput) {
                $response = ['success' => false, 'message' => 'Selecciona una o varias imágenes'];
                break;
            }

            $files = isset($fileInput['name']) && is_array($fileInput['name']) ? normalize_uploaded_files($fileInput) : [$fileInput];
            foreach ($files as $file) {
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }
                $uploaded[] = store_product_image($file);
            }

            if (empty($uploaded)) {
                $response = ['success' => false, 'message' => 'No se pudieron subir las imágenes'];
                break;
            }

            $response = [
                'success' => true,
                'message' => 'Imágenes cargadas correctamente',
                'images' => list_available_product_images($pdo),
                'uploaded' => $uploaded
            ];
            break;

        case 'client-update':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $clientId = (int)($input['id'] ?? 0);
            $firstName = sanitize($input['first_name'] ?? '');
            $lastName = sanitize($input['last_name'] ?? '');
            $phone = sanitize($input['phone'] ?? '');
            $email = sanitize($input['email'] ?? '');
            $companyName = sanitize($input['company_name'] ?? '');
            $birthdate = normalize_date_value($input['birthdate'] ?? null);
            $hasIsActive = array_key_exists('is_active', $input);
            $isActive = $hasIsActive ? !empty($input['is_active']) : null;

            if ($clientId <= 0) {
                $response = ['success' => false, 'message' => 'Cliente inválido'];
                break;
            }

            if ($firstName === '' || $lastName === '' || $phone === '') {
                $response = ['success' => false, 'message' => 'Nombre, apellido y teléfono son obligatorios'];
                break;
            }

            if ($email === '') {
                $digits = preg_replace('/\D+/', '', $phone);
                $suffix = $digits !== '' ? substr($digits, -8) : (string)$clientId;
                $email = 'cliente.' . $suffix . '@truper.local';
            }

            if (db_column_exists('users', 'role')) {
                $checkUser = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'client' LIMIT 1");
                $checkUser->execute([$clientId]);
            } else {
                $checkUser = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
                $checkUser->execute([$clientId]);
            }

            if (!(int)$checkUser->fetchColumn()) {
                $response = ['success' => false, 'message' => 'Cliente no encontrado'];
                break;
            }

            $sets = [];
            $vals = [];
            if (db_column_exists('users', 'first_name')) { $sets[] = 'first_name = ?'; $vals[] = $firstName; }
            if (db_column_exists('users', 'last_name')) { $sets[] = 'last_name = ?'; $vals[] = $lastName; }
            if (db_column_exists('users', 'name')) { $sets[] = 'name = ?'; $vals[] = trim($firstName . ' ' . $lastName); }
            if (db_column_exists('users', 'email')) { $sets[] = 'email = ?'; $vals[] = $email; }
            if (db_column_exists('users', 'phone')) { $sets[] = 'phone = ?'; $vals[] = $phone; }
            if (db_column_exists('users', 'birthdate')) { $sets[] = 'birthdate = ?'; $vals[] = $birthdate; }
            elseif (db_column_exists('users', 'birthday')) { $sets[] = 'birthday = ?'; $vals[] = $birthdate; }
            if ($hasIsActive && db_column_exists('users', 'is_active')) { $sets[] = 'is_active = ?'; $vals[] = $isActive; }
            if ($hasIsActive && db_column_exists('users', 'active')) { $sets[] = 'active = ?'; $vals[] = $isActive ? 1 : 0; }
            if (db_column_exists('users', 'updated_at')) { $sets[] = 'updated_at = CURRENT_TIMESTAMP'; }

            if (empty($sets)) {
                $response = ['success' => false, 'message' => 'No hay columnas para actualizar'];
                break;
            }

            $vals[] = $clientId;
            $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $stmt->execute($vals);

            $code = ensure_numeric_client_user_code_admin_supply($pdo, $clientId);

            if (db_table_exists('clients')) {
                try {
                    $clientSets = [];
                    $clientVals = [];
                    if (db_column_exists('clients', 'company_name')) { $clientSets[] = 'company_name = ?'; $clientVals[] = $companyName; }
                    if (db_column_exists('clients', 'client_code')) { $clientSets[] = 'client_code = ?'; $clientVals[] = $code; }
                    if (db_column_exists('clients', 'updated_at')) { $clientSets[] = 'updated_at = CURRENT_TIMESTAMP'; }

                    if (!empty($clientSets)) {
                        $clientVals[] = $clientId;
                        $clientStmt = $pdo->prepare('UPDATE clients SET ' . implode(', ', $clientSets) . ' WHERE user_id = ?');
                        $clientStmt->execute($clientVals);
                    }
                } catch (Exception $ignored) {
                }
            }

            $response = [
                'success' => true,
                'message' => 'Cliente actualizado correctamente',
                'client' => [
                    'id' => $clientId,
                    'email' => $email,
                    'phone' => $phone,
                    'user_code' => $code,
                    'is_active' => $hasIsActive ? $isActive : null
                ]
            ];
            break;

        case 'product-images':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $response = ['success' => true, 'images' => list_available_product_images($pdo)];
            break;

        case 'categories-list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $onlyActive = isset($_GET['active']) && $_GET['active'] === '1';
            if ($onlyActive) {
                $stmt = $pdo->query("SELECT id, name, sort_order, is_active FROM product_categories WHERE is_active = true ORDER BY sort_order ASC, name ASC");
            } else {
                $stmt = $pdo->query("SELECT id, name, sort_order, is_active FROM product_categories ORDER BY sort_order ASC, name ASC");
            }
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'categories-save':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($input['id'] ?? 0);
            $name = trim((string)($input['name'] ?? ''));
            $sortOrder = (int)($input['sort_order'] ?? 0);
            $isActive = isset($input['is_active']) ? !empty($input['is_active']) : true;

            if ($name === '') {
                $response = ['success' => false, 'message' => 'El nombre de la categoría es obligatorio'];
                break;
            }

            $duplicateStmt = $pdo->prepare("SELECT id FROM product_categories WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1");
            $duplicateStmt->execute([$name, $id]);
            if ($duplicateStmt->fetch()) {
                $response = ['success' => false, 'message' => 'Ya existe una categoría con ese nombre'];
                break;
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE product_categories SET name = ?, sort_order = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$name, $sortOrder, $isActive, $id]);
                $response = ['success' => true, 'message' => 'Categoría actualizada'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO product_categories (name, sort_order, is_active) VALUES (?, ?, ?)");
                $stmt->execute([$name, $sortOrder, $isActive]);
                $response = ['success' => true, 'message' => 'Categoría creada'];
            }
            break;

        case 'categories-delete':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($input['id'] ?? 0);
            $name = trim((string)($input['name'] ?? ''));

            if ($id <= 0 && $name === '') {
                $response = ['success' => false, 'message' => 'Categoría inválida'];
                break;
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE product_categories SET is_active = false, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$id]);
            } elseif ($name !== '') {
                $stmt = $pdo->prepare("UPDATE product_categories SET is_active = false, updated_at = CURRENT_TIMESTAMP WHERE LOWER(name) = LOWER(?)");
                $stmt->execute([$name]);
            }
            $response = ['success' => true, 'message' => 'Categoría desactivada'];
            break;

        case 'marketplace-list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $onlyActive = isset($_GET['active']) && $_GET['active'] === '1';
            if ($onlyActive) {
                $stmt = $pdo->query("SELECT id, sku, name, description, condition_label, unit_price, stock_quantity, image_url, is_active, created_at, updated_at FROM marketplace_ce_products WHERE is_active = true ORDER BY created_at DESC");
            } else {
                $stmt = $pdo->query("SELECT id, sku, name, description, condition_label, unit_price, stock_quantity, image_url, is_active, created_at, updated_at FROM marketplace_ce_products ORDER BY created_at DESC");
            }
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'marketplace-save':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($_POST['id'] ?? ($input['id'] ?? 0));
            $sku = normalize_sku_admin_supply(sanitize($_POST['sku'] ?? ($input['sku'] ?? '')));
            $name = sanitize($_POST['name'] ?? ($input['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ($input['description'] ?? '')));
            $conditionLabel = sanitize($_POST['condition_label'] ?? ($input['condition_label'] ?? 'Seminuevo'));
            $unitPrice = (float)($_POST['unit_price'] ?? ($input['unit_price'] ?? 0));
            $stockQuantity = (int)($_POST['stock_quantity'] ?? ($input['stock_quantity'] ?? 1));
            $isActive = isset($_POST['is_active']) ? !empty($_POST['is_active']) : (isset($input['is_active']) ? !empty($input['is_active']) : true);

            if ($sku === '' || $name === '' || $description === '') {
                $response = ['success' => false, 'message' => 'SKU, nombre y descripción son obligatorios'];
                break;
            }
            if (!is_valid_numeric_sku_admin_supply($sku)) {
                $response = ['success' => false, 'message' => 'El código SKU CE debe tener exactamente 5 números'];
                break;
            }

            if ($unitPrice < 0 || $stockQuantity < 0) {
                $response = ['success' => false, 'message' => 'Precio o stock inválido'];
                break;
            }

            $allowedConditions = ['Seminuevo', 'Usado', 'Reacondicionado'];
            if (!in_array($conditionLabel, $allowedConditions, true)) {
                $conditionLabel = 'Seminuevo';
            }

            $usage = sku_usage_admin_supply($pdo, $sku, $id);
            $sameRecord = record_matches_normalized_sku_admin_supply($pdo, 'marketplace_ce_products', $id, $sku);
            $seedConflict = $usage['in_seed'] && !$sameRecord;
            if ($usage['in_products'] || $usage['in_marketplace'] || $seedConflict) {
                $response = [
                    'success' => false,
                    'message' => $usage['in_marketplace']
                        ? 'Ya existe un artículo CE con ese código'
                        : ($usage['in_products']
                            ? 'Ese código ya está registrado en productos'
                            : 'Ese código ya existe en el catálogo base')
                ];
                break;
            }

            $imageUrl = sanitize($_POST['image_url'] ?? ($input['image_url'] ?? 'images/products/default-product.svg'));
            if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $imageUrl = store_product_image($_FILES['image']);
            }

            if ($id > 0) {
                if (!isset($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    $stmt = $pdo->prepare("UPDATE marketplace_ce_products SET sku = ?, name = ?, description = ?, condition_label = ?, unit_price = ?, stock_quantity = ?, is_active = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$sku, $name, $description, $conditionLabel, $unitPrice, max(0, $stockQuantity), $isActive, $_SESSION['user_id'], $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE marketplace_ce_products SET sku = ?, name = ?, description = ?, condition_label = ?, unit_price = ?, stock_quantity = ?, image_url = ?, is_active = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$sku, $name, $description, $conditionLabel, $unitPrice, max(0, $stockQuantity), $imageUrl, $isActive, $_SESSION['user_id'], $id]);
                }
                $response = ['success' => true, 'message' => 'Artículo CE actualizado'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO marketplace_ce_products (sku, name, description, condition_label, unit_price, stock_quantity, image_url, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$sku, $name, $description, $conditionLabel, $unitPrice, max(0, $stockQuantity), $imageUrl, $isActive, $_SESSION['user_id'], $_SESSION['user_id']]);
                $response = ['success' => true, 'message' => 'Artículo CE creado'];
            }
            break;

        case 'marketplace-delete':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'Artículo inválido'];
                break;
            }

            $stmt = $pdo->prepare("UPDATE marketplace_ce_products SET is_active = false, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $id]);
            $response = ['success' => true, 'message' => 'Artículo CE desactivado'];
            break;

        case 'updates-list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $onlyActive = isset($_GET['active']) && $_GET['active'] === '1';
            if ($onlyActive) {
                $stmt = $pdo->query("SELECT id, update_type, title, body, image_url, sort_order, is_active, created_at, updated_at FROM homepage_updates WHERE is_active = true ORDER BY sort_order ASC, id DESC LIMIT 40");
            } else {
                $stmt = $pdo->query("SELECT id, update_type, title, body, image_url, sort_order, is_active, created_at, updated_at FROM homepage_updates ORDER BY sort_order ASC, id DESC LIMIT 120");
            }

            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'updates-save':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($input['id'] ?? 0);
            $type = sanitize($input['update_type'] ?? 'noticia');
            $title = trim((string)($input['title'] ?? ''));
            $body = trim((string)($input['body'] ?? ''));
            $sortOrder = (int)($input['sort_order'] ?? 0);
            $isActive = isset($input['is_active']) ? !empty($input['is_active']) : true;

            $allowedTypes = ['noticia', 'promocion', 'evento'];
            if (!in_array($type, $allowedTypes, true)) {
                $type = 'noticia';
            }

            if ($title === '' || $body === '') {
                $response = ['success' => false, 'message' => 'Titulo y contenido son obligatorios'];
                break;
            }

            // Handle image upload if present
            $imageUrl = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $imageUrl = store_product_image($_FILES['image']);
                } catch (Exception $e) {
                    $response = ['success' => false, 'message' => 'Error al cargar imagen: ' . $e->getMessage()];
                    break;
                }
            }

            if ($id > 0) {
                // If updating, preserve existing image if no new one is provided
                if ($imageUrl === null) {
                    $stmt = $pdo->prepare("UPDATE homepage_updates SET update_type = ?, title = ?, body = ?, sort_order = ?, is_active = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$type, $title, $body, $sortOrder, $isActive, $_SESSION['user_id'], $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE homepage_updates SET update_type = ?, title = ?, body = ?, image_url = ?, sort_order = ?, is_active = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$type, $title, $body, $imageUrl, $sortOrder, $isActive, $_SESSION['user_id'], $id]);
                }
                $response = ['success' => true, 'message' => 'Publicacion actualizada'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO homepage_updates (update_type, title, body, image_url, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$type, $title, $body, $imageUrl, $sortOrder, $isActive, $_SESSION['user_id'], $_SESSION['user_id']]);
                $response = ['success' => true, 'message' => 'Publicacion creada'];
            }
            break;

        case 'updates-delete':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'Registro invalido'];
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM homepage_updates WHERE id = ?");
            $stmt->execute([$id]);
            $response = ['success' => true, 'message' => 'Publicacion eliminada'];
            break;

        case 'supplier-product-create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $productId = (int)($input['product_id'] ?? 0);
            $supplierName = sanitize($input['supplier_name'] ?? '');
            $supplierSku = sanitize($input['supplier_sku'] ?? '');
            $unitCost = (float)($input['unit_cost'] ?? 0);

            if ($productId <= 0 || $supplierName === '' || $unitCost < 0) {
                $response = ['success' => false, 'message' => 'Datos incompletos para asignar proveedor'];
                break;
            }

            $stmt = $pdo->prepare("INSERT INTO product_suppliers (product_id, supplier_name, supplier_sku, unit_cost, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$productId, $supplierName, $supplierSku, $unitCost, $_SESSION['user_id']]);
            $response = ['success' => true, 'message' => 'Asignación producto-proveedor registrada'];
            break;

        case 'supplier-products-list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $stmt = $pdo->query("SELECT ps.id, ps.product_id, p.sku, p.name AS product_name, ps.supplier_name, ps.supplier_sku, ps.unit_cost FROM product_suppliers ps JOIN products p ON p.id = ps.product_id ORDER BY ps.supplier_name ASC, p.name ASC");
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'supplier-products-by-supplier':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $supplierName = sanitize($_GET['supplier_name'] ?? '');
            if ($supplierName === '') {
                $response = ['success' => true, 'items' => []];
                break;
            }
            $stmt = $pdo->prepare("SELECT ps.id, ps.product_id, p.sku, p.name AS product_name, ps.supplier_name, ps.supplier_sku, ps.unit_cost FROM product_suppliers ps JOIN products p ON p.id = ps.product_id WHERE LOWER(TRIM(ps.supplier_name)) = LOWER(TRIM(?)) ORDER BY p.name ASC");
            $stmt->execute([$supplierName]);
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'stock':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $items = list_stock_products_compatible($pdo);
            $response = ['success' => true, 'items' => $items];
            break;

        case 'calendar-list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $stmt = $pdo->query("SELECT id, supplier_name, visit_datetime, notes FROM supplier_calendar ORDER BY visit_datetime ASC LIMIT 200");
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'calendar-create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $supplier_name = sanitize($input['supplier_name'] ?? '');
            $visit_datetime = $input['visit_datetime'] ?? '';
            $notes = sanitize($input['notes'] ?? '');
            if ($supplier_name === '' || $visit_datetime === '') {
                $response = ['success' => false, 'message' => 'Proveedor y fecha son obligatorios'];
                break;
            }
            $stmt = $pdo->prepare("INSERT INTO supplier_calendar (supplier_name, visit_datetime, notes, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$supplier_name, $visit_datetime, $notes, $_SESSION['user_id']]);
            $response = ['success' => true, 'message' => 'Visita registrada'];
            break;

        case 'supplier-order-create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $supplier_name = sanitize($input['supplier_name'] ?? '');
            $expected_date = $input['expected_date'] ?? '';
            $items = $input['items'] ?? [];
            if ($supplier_name === '' || $expected_date === '' || !is_array($items) || count($items) === 0) {
                $response = ['success' => false, 'message' => 'Datos incompletos'];
                break;
            }

            $total = 0;
            $normalizedItems = [];
            foreach ($items as $it) {
                $qty = (int)($it['quantity'] ?? 0);
                $cost = (float)($it['estimated_cost'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $supplierProductId = (int)($it['supplier_product_id'] ?? 0);
                $sku = sanitize($it['sku'] ?? '');
                $productName = sanitize($it['product_name'] ?? '');

                if ($supplierProductId > 0) {
                    $sp = $pdo->prepare("SELECT p.sku, p.name AS product_name, ps.supplier_sku FROM product_suppliers ps JOIN products p ON p.id = ps.product_id WHERE ps.id = ? LIMIT 1");
                    $sp->execute([$supplierProductId]);
                    $spRow = $sp->fetch();
                    if ($spRow) {
                        $sku = (string)($spRow['sku'] ?? $sku);
                        $productName = (string)($spRow['product_name'] ?? $productName);
                    }
                }

                if ($sku === '' && $productName === '') {
                    continue;
                }

                $total += $qty * $cost;
                $normalizedItems[] = [
                    'supplier_product_id' => $supplierProductId,
                    'sku' => $sku,
                    'product_name' => $productName,
                    'quantity' => $qty,
                    'estimated_cost' => $cost
                ];
            }

            if (count($normalizedItems) === 0) {
                $response = ['success' => false, 'message' => 'Agrega al menos un item valido'];
                break;
            }

            $folio = 'PROV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $stmt = $pdo->prepare("INSERT INTO supplier_orders (folio, supplier_name, expected_date, items_json, total_estimated, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$folio, $supplier_name, $expected_date, json_encode($normalizedItems, JSON_UNESCAPED_UNICODE), $total, $_SESSION['user_id']]);
            $orderId = (int)$pdo->lastInsertId();

            $h = $pdo->prepare("INSERT INTO transaction_history (transaction_type, reference_folio, data_json, created_by) VALUES ('supplier_order', ?, ?, ?)");
            $h->execute([$folio, json_encode(['supplier_name' => $supplier_name, 'expected_date' => $expected_date, 'total' => $total], JSON_UNESCAPED_UNICODE), $_SESSION['user_id']]);

            $response = [
                'success' => true,
                'message' => 'Orden a proveedor creada',
                'folio' => $folio,
                'order_id' => $orderId,
                'ticket_url' => '/ticket_supplier.php?id=' . $orderId
            ];
            break;

        case 'supplier-order-list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $stmt = $pdo->query("SELECT id, folio, supplier_name, expected_date, total_estimated, status, created_at FROM supplier_orders ORDER BY created_at DESC LIMIT 200");
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'history':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $stmt = $pdo->query("SELECT id, transaction_type, reference_folio, data_json, created_at FROM transaction_history ORDER BY created_at DESC LIMIT 300");
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        default:
            $response = ['success' => false, 'message' => 'Accion no reconocida'];
    }
} catch (Throwable $e) {
    error_log('admin_supply API error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error interno del servidor'];
}

restore_error_handler();

$buffer = ob_get_clean();
if (!empty($buffer)) {
    error_log('admin_supply API buffered output: ' . trim($buffer));
}

echo json_encode($response);
