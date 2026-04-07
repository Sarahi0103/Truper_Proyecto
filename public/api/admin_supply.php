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

$response = ['success' => false, 'message' => 'Accion no reconocida'];

try {
    switch ($action) {
        case 'product-create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $sku = sanitize($_POST['sku'] ?? '');
            $name = sanitize($_POST['name'] ?? '');
            $category = sanitize($_POST['category'] ?? 'General');
            $description = sanitize($_POST['description'] ?? '');
            $barcode = sanitize($_POST['barcode'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $stockQty = (int)($_POST['stock_quantity'] ?? 50);
            $reorder = (int)($_POST['reorder_level'] ?? 10);

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

            $imageUrl = 'images/products/default-product.svg';
            if (isset($_FILES['image'])) {
                $imageUrl = store_product_image($_FILES['image']);
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

        case 'stock':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $stmt = $pdo->query("SELECT id, sku, name, category, stock_quantity, reorder_level, unit_price, COALESCE(image_url, 'images/products/default-product.svg') AS image_url FROM products WHERE is_active = true ORDER BY stock_quantity ASC, name ASC");
            $items = $stmt->fetchAll();
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
            foreach ($items as $it) {
                $qty = (int)($it['quantity'] ?? 0);
                $cost = (float)($it['estimated_cost'] ?? 0);
                $total += $qty * $cost;
            }

            $folio = 'PROV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $stmt = $pdo->prepare("INSERT INTO supplier_orders (folio, supplier_name, expected_date, items_json, total_estimated, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$folio, $supplier_name, $expected_date, json_encode($items, JSON_UNESCAPED_UNICODE), $total, $_SESSION['user_id']]);
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
