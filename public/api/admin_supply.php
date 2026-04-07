<?php
require_once '../../config/config.php';
require_admin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'stock';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

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
}

ensure_admin_supply_tables($pdo);

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

ensure_products_extra_columns($pdo);

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
    $queries = [];

    if (db_column_exists('products', 'is_active')) {
        $queries[] = "SELECT id, sku, name, category, stock_quantity, reorder_level, COALESCE(unit_price, sell_price, 0) AS unit_price, COALESCE(image_url, 'images/products/default-product.svg') AS image_url FROM products WHERE is_active = true ORDER BY stock_quantity ASC, name ASC LIMIT 500";
    }
    if (db_column_exists('products', 'active')) {
        $queries[] = "SELECT id, sku, name, category, stock_quantity, reorder_level, COALESCE(unit_price, sell_price, 0) AS unit_price, COALESCE(image_url, 'images/products/default-product.svg') AS image_url FROM products WHERE active = 1 ORDER BY stock_quantity ASC, name ASC LIMIT 500";
    }

    $queries[] = "SELECT id, sku, name, category, stock_quantity, reorder_level, COALESCE(unit_price, sell_price, 0) AS unit_price, COALESCE(image_url, 'images/products/default-product.svg') AS image_url FROM products ORDER BY stock_quantity ASC, name ASC LIMIT 500";

    foreach ($queries as $sql) {
        try {
            $stmt = $pdo->query($sql);
            $items = $stmt ? $stmt->fetchAll() : [];
            if (is_array($items) && count($items) > 0) {
                return $items;
            }
        } catch (Exception $ignored) {
        }
    }

    return [];
}

$response = ['success' => false, 'message' => 'Accion no reconocida'];

try {
    switch ($action) {
        case 'product-create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $sku = sanitize($_POST['sku'] ?? ($input['sku'] ?? ''));
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
            if ($price < 0) {
                $response = ['success' => false, 'message' => 'Precio inválido'];
                break;
            }

            try {
                $check = $pdo->prepare('SELECT 1 FROM products WHERE sku = ? LIMIT 1');
                $check->execute([$sku]);
                if ((bool)$check->fetchColumn()) {
                    $response = ['success' => false, 'message' => 'Ya existe un producto con ese SKU'];
                    break;
                }
            } catch (Exception $ignoredCheck) {
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

        case 'product-images':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $response = ['success' => true, 'images' => list_available_product_images($pdo)];
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
            $stmt = $pdo->prepare("SELECT ps.id, ps.product_id, p.sku, p.name AS product_name, ps.supplier_name, ps.supplier_sku, ps.unit_cost FROM product_suppliers ps JOIN products p ON p.id = ps.product_id WHERE ps.supplier_name = ? ORDER BY p.name ASC");
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
} catch (Exception $e) {
    error_log('admin_supply API error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error interno del servidor'];
}

echo json_encode($response);
