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

function normalize_bool_admin_supply($value, bool $default = false): bool {
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

function normalize_sku_admin_supply($value): string {
    $sku = trim((string)$value);
    return preg_replace('/\D+/', '', $sku) ?: '';
}

function is_valid_numeric_sku_admin_supply(string $sku): bool {
    return (bool)preg_match('/^\d{5}$/', $sku);
}

function first_existing_column_admin_supply(string $table, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (db_column_exists($table, (string)$candidate)) {
            return (string)$candidate;
        }
    }
    return null;
}

function sku_column_for_table_admin_supply(string $table): ?string {
    return first_existing_column_admin_supply($table, ['sku', 'product_code', 'code', 'codigo']);
}

function name_column_for_table_admin_supply(string $table): ?string {
    return first_existing_column_admin_supply($table, ['name', 'product_name', 'nombre', 'title']);
}

function ensure_products_name_column_admin_supply($pdo): ?string {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (id SERIAL PRIMARY KEY)");
    } catch (Exception $ignored) {
    }

    $resolved = name_column_for_table_admin_supply('products');
    if ($resolved !== null) {
        return $resolved;
    }

    try {
        $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE LOWER(table_name) = 'products' AND LOWER(column_name) IN ('name','product_name','nombre','title') ORDER BY CASE LOWER(column_name) WHEN 'name' THEN 1 WHEN 'product_name' THEN 2 WHEN 'nombre' THEN 3 WHEN 'title' THEN 4 ELSE 10 END LIMIT 1");
        $stmt->execute();
        $col = $stmt->fetchColumn();
        if (is_string($col) && trim($col) !== '') {
            return trim($col);
        }
    } catch (Exception $ignored) {
    }

    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS name VARCHAR(255)");
    } catch (Exception $ignored) {
    }

    $resolved = name_column_for_table_admin_supply('products');
    if ($resolved !== null) {
        return $resolved;
    }

    if (db_column_exists('products', 'description')) {
        return 'description';
    }

    if (db_column_exists('products', 'category')) {
        return 'category';
    }

    return null;
}

function set_marketplace_visibility_compatible($pdo, int $id, bool $isVisible): void {
    if ($id <= 0) {
        throw new Exception('ID de artículo CE inválido');
    }

    // Try 'is_active' column first
    try {
        $stmt = $pdo->prepare('UPDATE marketplace_ce_products SET is_active = ? WHERE id = ?');
        $stmt->execute([$isVisible ? 1 : 0, $id]);
        if ($stmt->rowCount() > 0) {
            return;
        }
    } catch (Exception $e) {
        // Column may not exist, try next
    }

    // Try 'active' column second
    try {
        $stmt = $pdo->prepare('UPDATE marketplace_ce_products SET active = ? WHERE id = ?');
        $stmt->execute([$isVisible ? 1 : 0, $id]);
        if ($stmt->rowCount() > 0) {
            return;
        }
    } catch (Exception $e) {
        // Column may not exist, try next
    }

    throw new Exception('No existe columna de visibilidad (is_active o active) en marketplace_ce_products');
}

function normalized_sku_exists_in_table_admin_supply($pdo, string $table, string $sku, int $excludeId = 0): bool {
    if ($sku === '') {
        return false;
    }

    if (!in_array($table, ['products', 'marketplace_ce_products'], true)) {
        return false;
    }

    $skuColumn = sku_column_for_table_admin_supply($table);
    if ($skuColumn === null) {
        return false;
    }

    try {
        if ($excludeId > 0) {
            $stmt = $pdo->prepare("SELECT id, {$skuColumn} AS sku FROM {$table} WHERE id <> ?");
            $stmt->execute([max(0, $excludeId)]);
        } else {
            $stmt = $pdo->query("SELECT id, {$skuColumn} AS sku FROM {$table}");
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

    $skuColumn = sku_column_for_table_admin_supply($table);
    if ($skuColumn === null) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT {$skuColumn} AS sku FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $existingSku = normalize_sku_admin_supply((string)$stmt->fetchColumn());
        return $existingSku !== '' && $existingSku === $sku;
    } catch (Exception $ignored) {
        return false;
    }
}

function insert_category_and_get_id_admin_supply($pdo, string $name, int $sortOrder, bool $isActive): int {
    // PostgreSQL supports RETURNING; MySQL/MariaDB may not.
    try {
        $stmt = $pdo->prepare("INSERT INTO product_categories (name, sort_order, is_active) VALUES (?, ?, ?) RETURNING id");
        $stmt->execute([$name, $sortOrder, $isActive]);
        $createdId = (int)$stmt->fetchColumn();
        if ($createdId > 0) {
            return $createdId;
        }
    } catch (Exception $ignored) {
        // Fallback path below.
    }

    $stmt = $pdo->prepare("INSERT INTO product_categories (name, sort_order, is_active) VALUES (?, ?, ?)");
    $stmt->execute([$name, $sortOrder, $isActive]);

    try {
        $lastId = (int)$pdo->lastInsertId();
        if ($lastId > 0) {
            return $lastId;
        }
    } catch (Exception $ignored) {
    }

    $findStmt = $pdo->prepare("SELECT id FROM product_categories WHERE LOWER(name) = LOWER(?) ORDER BY id DESC LIMIT 1");
    $findStmt->execute([$name]);
    return (int)$findStmt->fetchColumn();
}

function ensure_product_categories_runtime_admin_supply($pdo): void {
    $created = false;

    // PostgreSQL style.
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_categories (
            id SERIAL PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE,
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active BOOLEAN NOT NULL DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $created = true;
    } catch (Exception $ignored) {
    }

    // MySQL/MariaDB style fallback.
    if (!$created) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS product_categories (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL UNIQUE,
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_active BOOLEAN NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $created = true;
        } catch (Exception $ignored) {
        }
    }

    // SQLite style fallback.
    if (!$created) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS product_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )");
            $created = true;
        } catch (Exception $ignored) {
        }
    }

    if (!$created) {
        throw new Exception('No se pudo inicializar la tabla de categorías');
    }

    // Best-effort column normalization for legacy environments.
    // Use direct ALTER statements only when a column is missing to avoid engine-specific syntax issues.
    if (!db_column_exists('product_categories', 'sort_order')) {
        try { $pdo->exec("ALTER TABLE product_categories ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0"); } catch (Exception $ignored) {}
    }
    if (!db_column_exists('product_categories', 'is_active')) {
        try { $pdo->exec("ALTER TABLE product_categories ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT true"); } catch (Exception $ignored) {}
    }
    if (!db_column_exists('product_categories', 'created_at')) {
        try { $pdo->exec("ALTER TABLE product_categories ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $ignored) {}
    }
    if (!db_column_exists('product_categories', 'updated_at')) {
        try { $pdo->exec("ALTER TABLE product_categories ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $ignored) {}
    }

    try {
        $seedCount = (int)$pdo->query("SELECT COUNT(*) FROM product_categories")->fetchColumn();
        if ($seedCount === 0) {
            $seedStmt = $pdo->prepare("INSERT INTO product_categories (name, sort_order, is_active) VALUES (?, ?, true)");
            $seedStmt->execute(['Material eléctrico', 10]);
            $seedStmt->execute(['Fontanería', 20]);
            $seedStmt->execute(['Cerrajería', 30]);
            $seedStmt->execute(['Herrería', 40]);
        }
    } catch (Exception $ignored) {
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
        category VARCHAR(220),
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

    try {
        $pdo->exec("ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS category VARCHAR(220)");
    } catch (Exception $ignored) {}
    
}

function ensure_products_extra_columns($pdo): void {
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS sku VARCHAR(100)");
        $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS name VARCHAR(255)");
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

function ensure_products_sku_integrity_admin_supply($pdo): void {
    if (!db_table_exists('products')) {
        return;
    }

    // Ensure sku column exists in legacy schemas.
    if (!db_column_exists('products', 'sku')) {
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN sku VARCHAR(100)");
        } catch (Exception $ignored) {
        }
    }

    if (!db_column_exists('products', 'sku')) {
        // If sku still does not exist, keep compatibility mode and avoid crashing.
        return;
    }

    $sourceColumn = null;
    foreach (['product_code', 'code', 'codigo', 'barcode'] as $candidateColumn) {
        if (db_column_exists('products', $candidateColumn)) {
            $sourceColumn = $candidateColumn;
            break;
        }
    }

    try {
        $selectSql = 'SELECT id, COALESCE(sku, \'\') AS sku';
        if ($sourceColumn !== null) {
            $selectSql .= ', COALESCE(' . $sourceColumn . ', \'\') AS source_code';
        } else {
            $selectSql .= ", '' AS source_code";
        }
        $selectSql .= ' FROM products ORDER BY id ASC';

        $rowsStmt = $pdo->query($selectSql);
        $rows = $rowsStmt ? $rowsStmt->fetchAll() : [];
        if (!is_array($rows) || empty($rows)) {
            return;
        }

        $used = [];
        foreach ($rows as $row) {
            $existing = normalize_sku_admin_supply($row['sku'] ?? '');
            if (is_valid_numeric_sku_admin_supply($existing) && !isset($used[$existing])) {
                $used[$existing] = true;
            }
        }

        $updates = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $current = normalize_sku_admin_supply($row['sku'] ?? '');
            if (is_valid_numeric_sku_admin_supply($current) && !isset($updates[$id])) {
                continue;
            }

            $candidate = normalize_sku_admin_supply($row['source_code'] ?? '');
            if (!is_valid_numeric_sku_admin_supply($candidate)) {
                $candidate = str_pad((string)($id % 100000), 5, '0', STR_PAD_LEFT);
            }

            if (!is_valid_numeric_sku_admin_supply($candidate)) {
                $candidate = '00000';
            }

            $attempts = 0;
            while (isset($used[$candidate]) && $attempts < 100000) {
                $next = (((int)$candidate) + 1) % 100000;
                $candidate = str_pad((string)$next, 5, '0', STR_PAD_LEFT);
                $attempts += 1;
            }

            if (isset($used[$candidate])) {
                // Extremely unlikely; keep going without hard fail.
                continue;
            }

            $used[$candidate] = true;
            $updates[$id] = $candidate;
        }

        if (!empty($updates)) {
            $upd = $pdo->prepare('UPDATE products SET sku = ? WHERE id = ?');
            foreach ($updates as $id => $skuValue) {
                try {
                    $upd->execute([$skuValue, (int)$id]);
                } catch (Exception $ignored) {
                }
            }
        }

        try {
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_products_sku_admin_supply ON products (sku)');
        } catch (Exception $ignored) {
        }
    } catch (Exception $ignored) {
    }
}

function ensure_products_name_integrity_admin_supply($pdo): void {
    if (!db_table_exists('products')) {
        return;
    }

    if (!db_column_exists('products', 'name')) {
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN name VARCHAR(255)");
        } catch (Exception $ignored) {
        }
    }

    if (!db_column_exists('products', 'name')) {
        return;
    }

    $sourceColumn = null;
    foreach (['product_name', 'nombre', 'title', 'description'] as $candidateColumn) {
        if (db_column_exists('products', $candidateColumn)) {
            $sourceColumn = $candidateColumn;
            break;
        }
    }

    try {
        $sql = 'SELECT id, COALESCE(name, \'\') AS current_name';
        if ($sourceColumn !== null) {
            $sql .= ', COALESCE(' . $sourceColumn . ', \'\') AS source_name';
        } else {
            $sql .= ", '' AS source_name";
        }
        $sql .= ' FROM products ORDER BY id ASC';

        $rowsStmt = $pdo->query($sql);
        $rows = $rowsStmt ? $rowsStmt->fetchAll() : [];
        if (!is_array($rows) || empty($rows)) {
            return;
        }

        $upd = $pdo->prepare('UPDATE products SET name = ? WHERE id = ?');
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $currentName = trim((string)($row['current_name'] ?? ''));
            if ($currentName !== '') {
                continue;
            }

            $candidateName = trim((string)($row['source_name'] ?? ''));
            if ($candidateName === '') {
                $candidateName = 'Producto ' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
            }

            try {
                $upd->execute([$candidateName, $id]);
            } catch (Exception $ignored) {
            }
        }
    } catch (Exception $ignored) {
    }
}

function ensure_marketplace_integrity_admin_supply($pdo): void {
    if (!db_table_exists('marketplace_ce_products')) {
        return;
    }

    // Best-effort ensure core columns exist.
    $alterStatements = [
        "ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS sku VARCHAR(100)",
        "ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS name VARCHAR(220)",
        "ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS description TEXT",
        "ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS condition_label VARCHAR(80) DEFAULT 'Seminuevo'",
        "ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS unit_price DECIMAL(12,2) DEFAULT 0",
        "ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS stock_quantity INTEGER DEFAULT 1",
        "ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS image_url TEXT",
        "ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS category VARCHAR(220)",
        "ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true"
    ];
    foreach ($alterStatements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $ignored) {
        }
    }

    $skuColumn = sku_column_for_table_admin_supply('marketplace_ce_products');
    $nameColumn = name_column_for_table_admin_supply('marketplace_ce_products');

    if ($skuColumn !== null) {
        $sourceSkuColumn = first_existing_column_admin_supply('marketplace_ce_products', ['product_code', 'code', 'codigo', 'barcode']);
        try {
            $sql = 'SELECT id, COALESCE(' . $skuColumn . ', \'\') AS current_sku';
            if ($sourceSkuColumn !== null && $sourceSkuColumn !== $skuColumn) {
                $sql .= ', COALESCE(' . $sourceSkuColumn . ', \'\') AS source_sku';
            } else {
                $sql .= ", '' AS source_sku";
            }
            $sql .= ' FROM marketplace_ce_products ORDER BY id ASC';

            $rows = ($pdo->query($sql) ?: null);
            $rows = $rows ? $rows->fetchAll() : [];

            $used = [];
            foreach ($rows as $row) {
                $existing = normalize_sku_admin_supply($row['current_sku'] ?? '');
                if (is_valid_numeric_sku_admin_supply($existing) && !isset($used[$existing])) {
                    $used[$existing] = true;
                }
            }

            $upd = $pdo->prepare('UPDATE marketplace_ce_products SET ' . $skuColumn . ' = ? WHERE id = ?');
            foreach ($rows as $row) {
                $id = (int)($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $current = normalize_sku_admin_supply($row['current_sku'] ?? '');
                if (is_valid_numeric_sku_admin_supply($current) && !isset($used[$current])) {
                    $used[$current] = true;
                    continue;
                }

                $candidate = normalize_sku_admin_supply($row['source_sku'] ?? '');
                if (!is_valid_numeric_sku_admin_supply($candidate)) {
                    $candidate = str_pad((string)((90000 + $id) % 100000), 5, '0', STR_PAD_LEFT);
                }

                $attempts = 0;
                while (isset($used[$candidate]) && $attempts < 100000) {
                    $next = (((int)$candidate) + 1) % 100000;
                    $candidate = str_pad((string)$next, 5, '0', STR_PAD_LEFT);
                    $attempts += 1;
                }

                if (isset($used[$candidate])) {
                    continue;
                }

                $used[$candidate] = true;
                try {
                    $upd->execute([$candidate, $id]);
                } catch (Exception $ignored) {
                }
            }
        } catch (Exception $ignored) {
        }
    }

    if ($nameColumn !== null) {
        $sourceNameColumn = first_existing_column_admin_supply('marketplace_ce_products', ['product_name', 'nombre', 'title', 'description']);
        if ($sourceNameColumn !== null) {
            try {
                $sql = 'UPDATE marketplace_ce_products SET ' . $nameColumn . ' = ' . $sourceNameColumn . ' WHERE COALESCE(' . $nameColumn . ", '') = ''";
                $pdo->exec($sql);
            } catch (Exception $ignored) {
            }
        }
    }

    try {
        if ($skuColumn !== null) {
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_marketplace_ce_sku_admin_supply ON marketplace_ce_products (' . $skuColumn . ')');
        }
    } catch (Exception $ignored) {
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

function normalize_gallery_base_name_admin_supply(string $base): string {
    $clean = preg_replace('/\+(FC1|E1|D1)$/i', '', $base);
    return trim((string)$clean, '-_ ');
}

function product_gallery_dir_admin_supply(string $sku): string {
    return __DIR__ . '/../images/products/by_code/' . $sku;
}

function list_product_gallery_images_admin_supply(string $sku): array {
    if (!is_valid_numeric_sku_admin_supply($sku)) {
        return [];
    }

    $dir = product_gallery_dir_admin_supply($sku);
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
    if (empty($files)) {
        return [];
    }

    usort($files, function ($a, $b) {
        $scoreA = admin_supply_image_priority_score($a);
        $scoreB = admin_supply_image_priority_score($b);
        if ($scoreA === $scoreB) {
            return strcmp((string)$a, (string)$b);
        }
        return $scoreA <=> $scoreB;
    });

    return array_map(function ($path) use ($sku) {
        return 'images/products/by_code/' . $sku . '/' . basename((string)$path);
    }, $files);
}

function store_product_image_for_sku_admin_supply(array $file, string $sku): string {
    if (!is_valid_numeric_sku_admin_supply($sku)) {
        throw new Exception('SKU inválido para galería');
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('Archivo de imagen inválido');
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

    $targetDir = product_gallery_dir_admin_supply($sku);
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($original, PATHINFO_FILENAME));
    $safeBase = normalize_gallery_base_name_admin_supply((string)$safeBase);
    if ($safeBase === '') {
        $safeBase = 'product';
    }

    $existing = list_product_gallery_images_admin_supply($sku);
    $suffix = empty($existing) ? '+FC1' : '';

    $filename = $safeBase . $suffix . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $targetPath)) {
        throw new Exception('No se pudo guardar la imagen');
    }

    return 'images/products/by_code/' . $sku . '/' . $filename;
}

function set_product_main_image_by_sku_admin_supply($pdo, string $sku, string $imageUrl): void {
    if (!is_valid_numeric_sku_admin_supply($sku) || trim($imageUrl) === '') {
        return;
    }

    $tables = ['products', 'marketplace_ce_products'];
    foreach ($tables as $table) {
        if (!db_table_exists($table) || !db_column_exists($table, 'image_url')) {
            continue;
        }

        $skuColumn = sku_column_for_table_admin_supply($table);
        if ($skuColumn === null) {
            continue;
        }

        try {
            $stmt = $pdo->query("SELECT id, {$skuColumn} AS sku FROM {$table}");
            $rows = $stmt ? $stmt->fetchAll() : [];
            foreach ($rows as $row) {
                $existing = normalize_sku_admin_supply($row['sku'] ?? '');
                if ($existing !== $sku) {
                    continue;
                }

                $sets = ['image_url = ?'];
                $values = [$imageUrl];
                if (db_column_exists($table, 'updated_at')) {
                    $sets[] = 'updated_at = CURRENT_TIMESTAMP';
                }

                $values[] = (int)($row['id'] ?? 0);
                $upd = $pdo->prepare('UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE id = ?');
                $upd->execute($values);
            }
        } catch (Exception $ignored) {
        }
    }
}

function reorder_product_gallery_images_admin_supply($pdo, string $sku, array $orderedImages): array {
    if (!is_valid_numeric_sku_admin_supply($sku)) {
        throw new Exception('SKU inválido');
    }

    $dir = product_gallery_dir_admin_supply($sku);
    if (!is_dir($dir)) {
        return [];
    }

    $currentImages = list_product_gallery_images_admin_supply($sku);
    if (count($currentImages) <= 1) {
        return $currentImages;
    }

    $prefix = 'images/products/by_code/' . $sku . '/';
    $currentMap = [];
    foreach ($currentImages as $img) {
        $fileName = basename((string)$img);
        $fullPath = $dir . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($fullPath)) {
            $currentMap[$img] = $fullPath;
        }
    }

    $normalizedOrder = [];
    foreach ($orderedImages as $candidate) {
        $webPath = trim((string)$candidate);
        if ($webPath === '' || strpos($webPath, $prefix) !== 0) {
            continue;
        }
        if (!isset($currentMap[$webPath])) {
            continue;
        }
        if (!in_array($webPath, $normalizedOrder, true)) {
            $normalizedOrder[] = $webPath;
        }
    }

    foreach (array_keys($currentMap) as $existingPath) {
        if (!in_array($existingPath, $normalizedOrder, true)) {
            $normalizedOrder[] = $existingPath;
        }
    }

    if (empty($normalizedOrder)) {
        return $currentImages;
    }

    $tempEntries = [];
    foreach ($normalizedOrder as $index => $webPath) {
        $sourcePath = $currentMap[$webPath] ?? '';
        if ($sourcePath === '' || !is_file($sourcePath)) {
            continue;
        }

        $ext = strtolower((string)pathinfo($sourcePath, PATHINFO_EXTENSION));
        $tempName = '__tmp__' . $index . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
        $tempPath = $dir . DIRECTORY_SEPARATOR . $tempName;
        if (!@rename($sourcePath, $tempPath)) {
            throw new Exception('No se pudo preparar el reordenamiento de imágenes');
        }

        $tempEntries[] = [
            'temp_path' => $tempPath,
            'original_web' => $webPath,
            'index' => $index
        ];
    }

    foreach ($tempEntries as $entry) {
        $index = (int)$entry['index'];
        $tempPath = (string)$entry['temp_path'];
        $originalBase = pathinfo(basename((string)$entry['original_web']), PATHINFO_FILENAME);
        $base = normalize_gallery_base_name_admin_supply((string)$originalBase);
        if ($base === '') {
            $base = 'product';
        }

        $ext = strtolower((string)pathinfo($tempPath, PATHINFO_EXTENSION));
        $suffix = $index === 0 ? '+FC1' : ('+O' . str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT));
        $targetName = $base . $suffix . '.' . $ext;
        $targetPath = $dir . DIRECTORY_SEPARATOR . $targetName;

        $counter = 1;
        while (file_exists($targetPath)) {
            $targetName = $base . $suffix . '-' . $counter . '.' . $ext;
            $targetPath = $dir . DIRECTORY_SEPARATOR . $targetName;
            $counter += 1;
        }

        if (!@rename($tempPath, $targetPath)) {
            throw new Exception('No se pudo aplicar el nuevo orden de imágenes');
        }
    }

    $images = list_product_gallery_images_admin_supply($sku);
    if (!empty($images)) {
        set_product_main_image_by_sku_admin_supply($pdo, $sku, $images[0]);
    }

    return $images;
}

function set_gallery_cover_image_admin_supply(string $sku, string $imageWebPath): string {
    if (!is_valid_numeric_sku_admin_supply($sku)) {
        throw new Exception('SKU inválido');
    }

    $prefix = 'images/products/by_code/' . $sku . '/';
    if (strpos($imageWebPath, $prefix) !== 0) {
        throw new Exception('Imagen inválida para este SKU');
    }

    $filename = basename($imageWebPath);
    $dir = product_gallery_dir_admin_supply($sku);
    $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($fullPath)) {
        throw new Exception('No se encontró la imagen');
    }

    $currentImages = list_product_gallery_images_admin_supply($sku);
    foreach ($currentImages as $img) {
        $imgName = basename($img);
        $imgFull = $dir . DIRECTORY_SEPARATOR . $imgName;
        if (!is_file($imgFull)) {
            continue;
        }

        $ext = pathinfo($imgName, PATHINFO_EXTENSION);
        $base = pathinfo($imgName, PATHINFO_FILENAME);
        $normalizedBase = normalize_gallery_base_name_admin_supply($base);
        if ($normalizedBase !== $base) {
            $newName = $normalizedBase . '.' . $ext;
            $newFull = $dir . DIRECTORY_SEPARATOR . $newName;
            if (!file_exists($newFull) && $newFull !== $imgFull) {
                @rename($imgFull, $newFull);
            }
        }
    }

    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $normalizedBase = normalize_gallery_base_name_admin_supply($base);
    $coverName = $normalizedBase . '+FC1.' . $ext;
    $coverFull = $dir . DIRECTORY_SEPARATOR . $coverName;

    if ($coverFull !== $fullPath) {
        if (file_exists($coverFull)) {
            @unlink($coverFull);
        }
        if (!@rename($fullPath, $coverFull)) {
            throw new Exception('No se pudo asignar la portada');
        }
    }

    return 'images/products/by_code/' . $sku . '/' . $coverName;
}

function create_product_compatible($pdo, array $payload): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (id SERIAL PRIMARY KEY)");
    } catch (Exception $ignored) {
    }

    $columns = [];
    $values = [];

    $skuColumn = sku_column_for_table_admin_supply('products');
    $nameColumn = ensure_products_name_column_admin_supply($pdo);

    if ($skuColumn === null) {
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS sku VARCHAR(100)");
        } catch (Exception $ignored) {
        }
        $skuColumn = sku_column_for_table_admin_supply('products');
    }

    if ($skuColumn !== null) {
        $columns[] = $skuColumn;
        $values[] = $payload['sku'];
    }
    if ($nameColumn !== null) {
        $columns[] = $nameColumn;
        $values[] = $payload['name'];
    }

    if (db_column_exists('products', 'description') && $nameColumn !== 'description') {
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
        $values[] = normalize_bool_admin_supply($payload['is_active'] ?? null, true);
    } elseif (db_column_exists('products', 'active')) {
        $columns[] = 'active';
        $values[] = normalize_bool_admin_supply($payload['is_active'] ?? null, true) ? 1 : 0;
    }

    if (count($columns) === 0) {
        throw new Exception('No hay columnas disponibles para insertar en products');
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

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (id SERIAL PRIMARY KEY)");
    } catch (Exception $ignored) {
    }

    $sets = [];
    $values = [];

    $skuColumn = sku_column_for_table_admin_supply('products');
    $nameColumn = ensure_products_name_column_admin_supply($pdo);

    if ($skuColumn === null) {
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS sku VARCHAR(100)");
        } catch (Exception $ignored) {
        }
        $skuColumn = sku_column_for_table_admin_supply('products');
    }

    if ($skuColumn !== null) { $sets[] = $skuColumn . ' = ?'; $values[] = $payload['sku']; }
    if ($nameColumn !== null) { $sets[] = $nameColumn . ' = ?'; $values[] = $payload['name']; }
    if (db_column_exists('products', 'description') && $nameColumn !== 'description') { $sets[] = 'description = ?'; $values[] = $payload['description']; }
    if (db_column_exists('products', 'category')) { $sets[] = 'category = ?'; $values[] = $payload['category']; }
    if (db_column_exists('products', 'barcode')) { $sets[] = 'barcode = ?'; $values[] = $payload['barcode'] ?? null; }
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
    
    // Handle visibility separately to avoid type errors
    if (array_key_exists('is_active', $payload)) {
        set_product_visibility_compatible($pdo, $id, normalize_bool_admin_supply($payload['is_active'] ?? null, true));
    }
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
        ensure_products_sku_integrity_admin_supply($pdo);
    } catch (Throwable $e) {
        error_log('admin_supply bootstrap warning (sku integrity): ' . $e->getMessage());
    }

    try {
        ensure_products_name_integrity_admin_supply($pdo);
    } catch (Throwable $e) {
        error_log('admin_supply bootstrap warning (name integrity): ' . $e->getMessage());
    }

    try {
        ensure_marketplace_integrity_admin_supply($pdo);
    } catch (Throwable $e) {
        error_log('admin_supply bootstrap warning (marketplace integrity): ' . $e->getMessage());
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

    $appendImage = function ($value) use (&$images) {
        $path = trim((string)$value);
        if ($path === '') {
            return;
        }

        // Accept local relative paths and absolute URLs used in content updates.
        $isUrl = preg_match('/^https?:\/\//i', $path) === 1;
        $hasImageExt = preg_match('/\.(svg|png|jpe?g|webp|gif)(\?.*)?$/i', $path) === 1;
        if ($isUrl || $hasImageExt || strpos($path, 'images/') === 0 || strpos($path, 'img/') === 0) {
            $images[] = $path;
        }
    };

    $scanDir = function (string $dirPath, string $webPrefix) use (&$appendImage) {
        if (!is_dir($dirPath)) {
            return;
        }

        $entries = scandir($dirPath);
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $full = $dirPath . DIRECTORY_SEPARATOR . $name;
            if (!is_file($full)) {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
                $appendImage(rtrim($webPrefix, '/') . '/' . $name);
            }
        }
    };

    $scanDir(__DIR__ . '/../images/products', 'images/products');
    $scanDir(__DIR__ . '/../images', 'images');

    try {
        if (db_column_exists('products', 'image_url')) {
            $stmt = $pdo->query("SELECT DISTINCT image_url FROM products WHERE image_url IS NOT NULL AND image_url <> '' LIMIT 500");
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                if (!empty($row['image_url'])) {
                    $appendImage((string)$row['image_url']);
                }
            }
        }
    } catch (Exception $ignored) {
    }

    try {
        if (db_table_exists('marketplace_ce_products') && db_column_exists('marketplace_ce_products', 'image_url')) {
            $stmt = $pdo->query("SELECT DISTINCT image_url FROM marketplace_ce_products WHERE image_url IS NOT NULL AND image_url <> '' LIMIT 500");
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                if (!empty($row['image_url'])) {
                    $appendImage((string)$row['image_url']);
                }
            }
        }
    } catch (Exception $ignored) {
    }

    try {
        if (db_table_exists('homepage_updates') && db_column_exists('homepage_updates', 'image_url')) {
            $stmt = $pdo->query("SELECT DISTINCT image_url FROM homepage_updates WHERE image_url IS NOT NULL AND image_url <> '' LIMIT 500");
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                if (!empty($row['image_url'])) {
                    $appendImage((string)$row['image_url']);
                }
            }
        }
    } catch (Exception $ignored) {
    }

    if (function_exists('get_xlsx_seed_products')) {
        try {
            $seedItems = get_xlsx_seed_products();
            if (is_array($seedItems)) {
                foreach ($seedItems as $seed) {
                    $appendImage($seed['image_url'] ?? '');
                }
            }
        } catch (Throwable $ignored) {
        }
    }

    $images = array_values(array_unique($images));
    sort($images);
    return $images;
}

function admin_supply_image_priority_score($filePath): int {
    $name = strtoupper((string)pathinfo((string)$filePath, PATHINFO_FILENAME));
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

function resolve_admin_supply_image_by_sku($rawSku): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $baseDir = __DIR__ . '/../images/products/by_code';
        if (is_dir($baseDir)) {
            $dirs = scandir($baseDir);
            if (is_array($dirs)) {
                foreach ($dirs as $dir) {
                    if ($dir === '.' || $dir === '..') {
                        continue;
                    }

                    $fullDir = $baseDir . '/' . $dir;
                    if (!is_dir($fullDir)) {
                        continue;
                    }

                    $matches = glob($fullDir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
                    if (empty($matches)) {
                        continue;
                    }

                    usort($matches, function ($a, $b) {
                        $scoreA = admin_supply_image_priority_score($a);
                        $scoreB = admin_supply_image_priority_score($b);
                        if ($scoreA === $scoreB) {
                            return strcmp((string)$a, (string)$b);
                        }
                        return $scoreA <=> $scoreB;
                    });

                    $cache[(string)$dir] = 'images/products/by_code/' . $dir . '/' . basename((string)$matches[0]);
                }
            }
        }
    }

    $sku = normalize_sku_admin_supply($rawSku);
    if ($sku === '') {
        return null;
    }

    return $cache[$sku] ?? null;
}

function apply_catalog_image_fallback_admin_supply(array $item): array {
    $current = trim((string)($item['image_url'] ?? ''));
    $needsFallback = ($current === '' || strcasecmp($current, 'images/products/default-product.svg') === 0);
    if (!$needsFallback) {
        return $item;
    }

    $resolved = resolve_admin_supply_image_by_sku($item['sku'] ?? '');
    if ($resolved !== null && $resolved !== '') {
        $item['image_url'] = $resolved;
    }

    return $item;
}

function list_stock_products_compatible($pdo): array {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (id SERIAL PRIMARY KEY)");
    } catch (Exception $ignored) {
    }

    $skuColumn = sku_column_for_table_admin_supply('products');
    $skuSelect = $skuColumn !== null ? "{$skuColumn} AS sku" : "'' AS sku";
    $nameColumn = name_column_for_table_admin_supply('products');
    if ($nameColumn === null && db_column_exists('products', 'description')) {
        $nameColumn = 'description';
    }
    $nameSelect = $nameColumn !== null ? "COALESCE({$nameColumn}, '') AS name" : "'' AS name";
    $nameOrderExpr = $nameColumn !== null ? $nameColumn . ' ASC' : 'id ASC';
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
        ? "(CASE WHEN is_active IS NULL THEN 1 WHEN LOWER(CAST(is_active AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) AS is_active"
        : (db_column_exists('products', 'active') ? "(CASE WHEN active = 1 THEN 1 ELSE 0 END) AS is_active" : "1 AS is_active");

    // Admin inventory must include both visible and hidden products so
    // the UI can toggle between Ocultar/Activar without items disappearing.
    $queries = [
        "SELECT id, {$skuSelect}, {$nameSelect}, {$descriptionSelect}, {$categorySelect}, {$stockSelect}, {$reorderSelect}, {$priceSelect}, {$imageSelect}, {$isActiveSelect} FROM products ORDER BY stock_quantity ASC, {$nameOrderExpr} LIMIT 500"
    ];

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
        $row = apply_catalog_image_fallback_admin_supply($row);
        $normalized = normalize_sku_admin_supply($row['sku'] ?? '');
        if ($normalized !== '') {
            $existingSkus[$normalized] = true;
        }
    }

    // Keep resolved image fallback for DB-backed products.
    $items = array_map('apply_catalog_image_fallback_admin_supply', $items);

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

                    $items[count($items) - 1] = apply_catalog_image_fallback_admin_supply($items[count($items) - 1]);

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
    // CSRF validation for POST requests to write endpoints
    $write_actions = ['product-save', 'product-delete', 'marketplace-save', 'marketplace-delete', 'stock-update', 'toggle-visibility'];
    if ($method === 'POST' && in_array($action, $write_actions, true)) {
        require_csrf_token();
    }
    
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
            $allowSeedSku = in_array((string)($_GET['allow_seed'] ?? '0'), ['1', 'true', 'TRUE', 'yes', 'on'], true);
            $usage = sku_usage_admin_supply($pdo, $sku, 0, $id);
            $sameRecord = record_matches_normalized_sku_admin_supply($pdo, 'products', $id, $sku);
            $seedConflict = $usage['in_seed'] && !$sameRecord && !$allowSeedSku;
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
            $allowSeedSku = in_array((string)($_POST['allow_seed_sku'] ?? ($input['allow_seed_sku'] ?? '0')), ['1', 'true', 'TRUE', 'yes', 'on'], true);

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
            if ($usage['in_products'] || $usage['in_marketplace'] || ($usage['in_seed'] && !$allowSeedSku)) {
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
            $barcode = sanitize($input['barcode'] ?? '');
            $price = (float)($input['price'] ?? 0);
            $stockQty = (int)($input['stock_quantity'] ?? 50);
            $reorder = (int)($input['reorder_level'] ?? 10);
            $imageUrl = sanitize($input['image_url'] ?? 'images/products/default-product.svg');
            $isVisible = normalize_bool_admin_supply($input['is_visible'] ?? null, true);
            $allowSeedSku = in_array((string)($input['allow_seed_sku'] ?? '0'), ['1', 'true', 'TRUE', 'yes', 'on'], true);

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
            $seedConflict = $usage['in_seed'] && !$sameRecord && !$allowSeedSku;
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
                    'barcode' => $barcode,
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
                'barcode' => $barcode,
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

        case 'product-visibility':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($input['id'] ?? 0);
            $isVisible = normalize_bool_admin_supply($input['is_visible'] ?? null, true);
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'Producto inválido'];
                break;
            }

            set_product_visibility_compatible($pdo, $id, $isVisible);

            $response = [
                'success' => true,
                'message' => $isVisible ? 'Producto visible en tienda' : 'Producto oculto en tienda'
            ];
            break;

        case 'marketplace-visibility':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($input['id'] ?? 0);
            $isVisible = normalize_bool_admin_supply($input['is_visible'] ?? null, true);
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'Artículo CE inválido'];
                break;
            }

            set_marketplace_visibility_compatible($pdo, $id, $isVisible);

            $response = [
                'success' => true,
                'message' => $isVisible ? 'Artículo CE visible' : 'Artículo CE oculto'
            ];
            break;

        case 'marketplace-image-upload':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $sku = normalize_sku_admin_supply($_POST['sku'] ?? ($input['sku'] ?? ''));
            if (!is_valid_numeric_sku_admin_supply($sku)) {
                $response = ['success' => false, 'message' => 'SKU inválido'];
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

            // Update marketplace product with the first uploaded image
            if (!empty($uploaded)) {
                $mkImageCol = first_existing_column_admin_supply('marketplace_ce_products', ['image_url', 'image', 'photo_url']);
                if ($mkImageCol !== null) {
                    $stmt = $pdo->prepare('UPDATE marketplace_ce_products SET ' . $mkImageCol . ' = ? WHERE sku = ? LIMIT 1');
                    $stmt->execute([$uploaded[0], $sku]);
                }
            }

            $response = [
                'success' => true,
                'message' => 'Imágenes CE cargadas correctamente',
                'sku' => $sku,
                'uploaded' => $uploaded,
                'cover' => $uploaded[0] ?? null
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

        case 'product-gallery-list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $sku = normalize_sku_admin_supply($_GET['sku'] ?? '');
            if (!is_valid_numeric_sku_admin_supply($sku)) {
                $response = ['success' => false, 'message' => 'SKU inválido'];
                break;
            }

            $images = list_product_gallery_images_admin_supply($sku);
            $response = [
                'success' => true,
                'sku' => $sku,
                'images' => $images,
                'cover' => $images[0] ?? null
            ];
            break;

        case 'product-gallery-upload':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $sku = normalize_sku_admin_supply($_POST['sku'] ?? ($input['sku'] ?? ''));
            if (!is_valid_numeric_sku_admin_supply($sku)) {
                $response = ['success' => false, 'message' => 'SKU inválido'];
                break;
            }

            $fileInput = $_FILES['images'] ?? $_FILES['image'] ?? null;
            if (!$fileInput) {
                $response = ['success' => false, 'message' => 'Selecciona una o varias imágenes'];
                break;
            }

            $files = isset($fileInput['name']) && is_array($fileInput['name']) ? normalize_uploaded_files($fileInput) : [$fileInput];
            $uploaded = [];
            foreach ($files as $file) {
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }
                $uploaded[] = store_product_image_for_sku_admin_supply($file, $sku);
            }

            if (empty($uploaded)) {
                $response = ['success' => false, 'message' => 'No se pudieron subir las imágenes'];
                break;
            }

            $images = list_product_gallery_images_admin_supply($sku);
            if (!empty($images)) {
                set_product_main_image_by_sku_admin_supply($pdo, $sku, $images[0]);
            }

            $response = [
                'success' => true,
                'message' => 'Galería actualizada correctamente',
                'sku' => $sku,
                'uploaded' => $uploaded,
                'images' => $images,
                'cover' => $images[0] ?? null
            ];
            break;

        case 'product-gallery-cover':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $sku = normalize_sku_admin_supply($input['sku'] ?? '');
            $image = trim((string)($input['image'] ?? ''));
            if (!is_valid_numeric_sku_admin_supply($sku) || $image === '') {
                $response = ['success' => false, 'message' => 'SKU o imagen inválidos'];
                break;
            }

            $cover = set_gallery_cover_image_admin_supply($sku, $image);
            set_product_main_image_by_sku_admin_supply($pdo, $sku, $cover);

            $response = [
                'success' => true,
                'message' => 'Portada asignada correctamente',
                'sku' => $sku,
                'cover' => $cover,
                'images' => list_product_gallery_images_admin_supply($sku)
            ];
            break;

        case 'product-gallery-delete':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $sku = normalize_sku_admin_supply($input['sku'] ?? '');
            $image = trim((string)($input['image'] ?? ''));
            if (!is_valid_numeric_sku_admin_supply($sku) || $image === '') {
                $response = ['success' => false, 'message' => 'SKU o imagen inválidos'];
                break;
            }

            $prefix = 'images/products/by_code/' . $sku . '/';
            if (strpos($image, $prefix) !== 0) {
                $response = ['success' => false, 'message' => 'Imagen inválida para este SKU'];
                break;
            }

            $fileName = basename($image);
            $fullPath = product_gallery_dir_admin_supply($sku) . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($fullPath)) {
                $response = ['success' => false, 'message' => 'No se encontró la imagen'];
                break;
            }

            if (!@unlink($fullPath)) {
                $response = ['success' => false, 'message' => 'No se pudo eliminar la imagen'];
                break;
            }

            $images = list_product_gallery_images_admin_supply($sku);
            if (!empty($images)) {
                set_product_main_image_by_sku_admin_supply($pdo, $sku, $images[0]);
            }

            $response = [
                'success' => true,
                'message' => 'Imagen eliminada correctamente',
                'sku' => $sku,
                'images' => $images,
                'cover' => $images[0] ?? null
            ];
            break;

        case 'product-gallery-reorder':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $sku = normalize_sku_admin_supply($input['sku'] ?? '');
            $images = $input['images'] ?? [];
            if (!is_valid_numeric_sku_admin_supply($sku) || !is_array($images) || count($images) === 0) {
                $response = ['success' => false, 'message' => 'SKU o lista de imágenes inválida'];
                break;
            }

            $ordered = reorder_product_gallery_images_admin_supply($pdo, $sku, $images);
            $response = [
                'success' => true,
                'message' => 'Orden de imágenes actualizado',
                'sku' => $sku,
                'images' => $ordered,
                'cover' => $ordered[0] ?? null
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

            ensure_product_categories_runtime_admin_supply($pdo);

            $nameSelect = db_column_exists('product_categories', 'name')
                ? "name"
                : "'' AS name";
            $orderSelect = db_column_exists('product_categories', 'sort_order')
                ? "sort_order"
                : "0 AS sort_order";
            $activeSelect = db_column_exists('product_categories', 'is_active')
                ? "is_active"
                : "true AS is_active";

            $onlyActive = isset($_GET['active']) && $_GET['active'] === '1';
            if ($onlyActive) {
                if (db_column_exists('product_categories', 'is_active')) {
                    $stmt = $pdo->query("SELECT id, {$nameSelect}, {$orderSelect}, {$activeSelect} FROM product_categories WHERE is_active = true ORDER BY " . (db_column_exists('product_categories', 'sort_order') ? 'sort_order ASC, ' : '') . "name ASC");
                } else {
                    $stmt = $pdo->query("SELECT id, {$nameSelect}, {$orderSelect}, {$activeSelect} FROM product_categories ORDER BY " . (db_column_exists('product_categories', 'sort_order') ? 'sort_order ASC, ' : '') . "name ASC");
                }
            } else {
                $stmt = $pdo->query("SELECT id, {$nameSelect}, {$orderSelect}, {$activeSelect} FROM product_categories ORDER BY " . (db_column_exists('product_categories', 'sort_order') ? 'sort_order ASC, ' : '') . "name ASC");
            }
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'categories-save':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            ensure_product_categories_runtime_admin_supply($pdo);

            $id = (int)($_POST['id'] ?? ($input['id'] ?? 0));
            $name = trim((string)($_POST['name'] ?? ($input['name'] ?? '')));
            $sortOrder = (int)($_POST['sort_order'] ?? ($input['sort_order'] ?? 0));
            $isActive = normalize_bool_admin_supply($_POST['is_active'] ?? ($input['is_active'] ?? null), true);

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
                $updated = false;

                // Full update path (newest schema)
                try {
                    $stmt = $pdo->prepare("UPDATE product_categories SET name = ?, sort_order = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$name, $sortOrder, $isActive, $id]);
                    $updated = true;
                } catch (Exception $ignored) {
                }

                // Legacy fallback: without updated_at
                if (!$updated) {
                    try {
                        $stmt = $pdo->prepare("UPDATE product_categories SET name = ?, sort_order = ?, is_active = ? WHERE id = ?");
                        $stmt->execute([$name, $sortOrder, $isActive, $id]);
                        $updated = true;
                    } catch (Exception $ignored) {
                    }
                }

                // Minimal fallback: name + sort_order only
                if (!$updated) {
                    try {
                        $stmt = $pdo->prepare("UPDATE product_categories SET name = ?, sort_order = ? WHERE id = ?");
                        $stmt->execute([$name, $sortOrder, $id]);
                        $updated = true;
                    } catch (Exception $ignored) {
                    }
                }

                // Final fallback: name only
                if (!$updated) {
                    $stmt = $pdo->prepare("UPDATE product_categories SET name = ? WHERE id = ?");
                    $stmt->execute([$name, $id]);
                }

                $response = [
                    'success' => true,
                    'message' => 'Categoría actualizada',
                    'item' => [
                        'id' => $id,
                        'name' => $name,
                        'sort_order' => $sortOrder,
                        'is_active' => $isActive ? 1 : 0
                    ]
                ];
            } else {
                $createdId = 0;

                // Full insert path (newest schema)
                try {
                    $createdId = insert_category_and_get_id_admin_supply($pdo, $name, $sortOrder, $isActive);
                } catch (Exception $ignored) {
                }

                // Legacy fallback: name + sort_order
                if ($createdId <= 0) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO product_categories (name, sort_order) VALUES (?, ?)");
                        $stmt->execute([$name, $sortOrder]);
                        $createdId = (int)$pdo->lastInsertId();
                    } catch (Exception $ignored) {
                    }
                }

                // Final fallback: name only
                if ($createdId <= 0) {
                    $stmt = $pdo->prepare("INSERT INTO product_categories (name) VALUES (?)");
                    $stmt->execute([$name]);
                    $createdId = (int)$pdo->lastInsertId();
                }

                if ($createdId <= 0) {
                    try {
                        $check = $pdo->prepare("SELECT id FROM product_categories WHERE LOWER(name) = LOWER(?) ORDER BY id DESC LIMIT 1");
                        $check->execute([$name]);
                        $createdId = (int)$check->fetchColumn();
                    } catch (Exception $ignored) {
                    }
                }

                $response = [
                    'success' => true,
                    'message' => 'Categoría creada',
                    'item' => [
                        'id' => $createdId,
                        'name' => $name,
                        'sort_order' => $sortOrder,
                        'is_active' => $isActive ? 1 : 0
                    ]
                ];
            }
            break;

        case 'categories-delete':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            ensure_product_categories_runtime_admin_supply($pdo);

            $id = (int)($_POST['id'] ?? ($input['id'] ?? 0));
            $name = trim((string)($_POST['name'] ?? ($input['name'] ?? '')));

            if ($id <= 0 && $name === '') {
                $response = ['success' => false, 'message' => 'Categoría inválida'];
                break;
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM product_categories WHERE id = ?");
                $stmt->execute([$id]);
            } elseif ($name !== '') {
                $stmt = $pdo->prepare("DELETE FROM product_categories WHERE LOWER(name) = LOWER(?)");
                $stmt->execute([$name]);
            }

            if (($stmt->rowCount() ?? 0) <= 0) {
                $response = ['success' => false, 'message' => 'No se encontró la categoría para eliminar'];
                break;
            }

            $response = ['success' => true, 'message' => 'Categoría eliminada'];
            break;

        case 'marketplace-list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $mkSkuCol = sku_column_for_table_admin_supply('marketplace_ce_products');
            $mkNameCol = name_column_for_table_admin_supply('marketplace_ce_products');
            $mkCategoryCol = first_existing_column_admin_supply('marketplace_ce_products', ['category', 'categoria']);
            $mkDescriptionCol = first_existing_column_admin_supply('marketplace_ce_products', ['description', 'details', 'descripcion']);
            $mkConditionCol = first_existing_column_admin_supply('marketplace_ce_products', ['condition_label', 'condition', 'estado']);
            $mkPriceCol = first_existing_column_admin_supply('marketplace_ce_products', ['unit_price', 'sell_price', 'price']);
            $mkStockCol = first_existing_column_admin_supply('marketplace_ce_products', ['stock_quantity', 'stock']);
            $mkImageCol = first_existing_column_admin_supply('marketplace_ce_products', ['image_url', 'image', 'photo_url']);
            $mkActiveCol = first_existing_column_admin_supply('marketplace_ce_products', ['is_active', 'active']);

            $selectSku = $mkSkuCol !== null ? ('COALESCE(' . $mkSkuCol . ", '') AS sku") : "'' AS sku";
            $selectName = $mkNameCol !== null ? ('COALESCE(' . $mkNameCol . ", '') AS name") : "'' AS name";
            $selectCategory = $mkCategoryCol !== null ? ('COALESCE(' . $mkCategoryCol . ", 'Marketplace CE') AS category") : "'Marketplace CE' AS category";
            $selectDescription = $mkDescriptionCol !== null ? ('COALESCE(' . $mkDescriptionCol . ", '') AS description") : "'' AS description";
            $selectCondition = $mkConditionCol !== null ? ('COALESCE(' . $mkConditionCol . ", 'Seminuevo') AS condition_label") : "'Seminuevo' AS condition_label";
            $selectPrice = $mkPriceCol !== null ? ('COALESCE(' . $mkPriceCol . ', 0) AS unit_price') : '0 AS unit_price';
            $selectStock = $mkStockCol !== null ? ('COALESCE(' . $mkStockCol . ', 0) AS stock_quantity') : '0 AS stock_quantity';
            $selectImage = $mkImageCol !== null ? ('COALESCE(' . $mkImageCol . ", 'images/products/default-product.svg') AS image_url") : "'images/products/default-product.svg' AS image_url";
            $selectActive = $mkActiveCol !== null
                ? ("(CASE WHEN " . $mkActiveCol . " IS NULL THEN 1 WHEN LOWER(CAST(" . $mkActiveCol . " AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) AS is_active")
                : '1 AS is_active';
            $selectCreatedAt = db_column_exists('marketplace_ce_products', 'created_at') ? 'created_at' : 'NULL AS created_at';
            $selectUpdatedAt = db_column_exists('marketplace_ce_products', 'updated_at') ? 'updated_at' : 'NULL AS updated_at';
            $orderExpr = db_column_exists('marketplace_ce_products', 'created_at') ? 'created_at DESC' : 'id DESC';

            $stmt = $pdo->query('SELECT id, ' . $selectSku . ', ' . $selectName . ', ' . $selectCategory . ', ' . $selectDescription . ', ' . $selectCondition . ', ' . $selectPrice . ', ' . $selectStock . ', ' . $selectImage . ', ' . $selectActive . ', ' . $selectCreatedAt . ', ' . $selectUpdatedAt . ' FROM marketplace_ce_products ORDER BY ' . $orderExpr);
            $items = $stmt ? $stmt->fetchAll() : [];

            $onlyActive = isset($_GET['active']) && $_GET['active'] === '1';
            if ($onlyActive) {
                $items = array_values(array_filter($items, function ($row) {
                    return !in_array((string)($row['is_active'] ?? '1'), ['0', '', 'false', 'False', 'FALSE'], true);
                }));
            }

            $response = ['success' => true, 'items' => $items];
            break;

        case 'marketplace-save':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($_POST['id'] ?? ($input['id'] ?? 0));
            $sku = normalize_sku_admin_supply(sanitize($_POST['sku'] ?? ($input['sku'] ?? '')));
            $name = sanitize($_POST['name'] ?? ($input['name'] ?? ''));
            $category = sanitize($_POST['category'] ?? ($input['category'] ?? 'Marketplace CE'));
            $description = trim((string)($_POST['description'] ?? ($input['description'] ?? '')));
            $conditionLabel = sanitize($_POST['condition_label'] ?? ($input['condition_label'] ?? 'Seminuevo'));
            $unitPrice = (float)($_POST['unit_price'] ?? ($input['unit_price'] ?? 0));
            $stockQuantity = (int)($_POST['stock_quantity'] ?? ($input['stock_quantity'] ?? 1));
            $isActive = isset($_POST['is_active']) ? !empty($_POST['is_active']) : (isset($input['is_active']) ? !empty($input['is_active']) : true);

            $mkSkuCol = sku_column_for_table_admin_supply('marketplace_ce_products');
            $mkNameCol = name_column_for_table_admin_supply('marketplace_ce_products');
            $mkCategoryCol = first_existing_column_admin_supply('marketplace_ce_products', ['category', 'categoria']);
            $mkDescriptionCol = first_existing_column_admin_supply('marketplace_ce_products', ['description', 'details', 'descripcion']);
            $mkConditionCol = first_existing_column_admin_supply('marketplace_ce_products', ['condition_label', 'condition', 'estado']);
            $mkPriceCol = first_existing_column_admin_supply('marketplace_ce_products', ['unit_price', 'sell_price', 'price']);
            $mkStockCol = first_existing_column_admin_supply('marketplace_ce_products', ['stock_quantity', 'stock']);
            $mkImageCol = first_existing_column_admin_supply('marketplace_ce_products', ['image_url', 'image', 'photo_url']);
            $mkActiveCol = first_existing_column_admin_supply('marketplace_ce_products', ['is_active', 'active']);
            $mkCreatedByCol = first_existing_column_admin_supply('marketplace_ce_products', ['created_by']);
            $mkUpdatedByCol = first_existing_column_admin_supply('marketplace_ce_products', ['updated_by']);
            $mkUpdatedAtCol = first_existing_column_admin_supply('marketplace_ce_products', ['updated_at']);

            if ($mkSkuCol === null || $mkNameCol === null || $mkDescriptionCol === null) {
                $response = ['success' => false, 'message' => 'Faltan columnas obligatorias en marketplace_ce_products (sku/nombre/descripción)'];
                break;
            }

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
                $sets = [$mkSkuCol . ' = ?', $mkNameCol . ' = ?', $mkDescriptionCol . ' = ?'];
                $values = [$sku, $name, $description];

                if ($mkConditionCol !== null) { $sets[] = $mkConditionCol . ' = ?'; $values[] = $conditionLabel; }
                if ($mkPriceCol !== null) { $sets[] = $mkPriceCol . ' = ?'; $values[] = $unitPrice; }
                if ($mkStockCol !== null) { $sets[] = $mkStockCol . ' = ?'; $values[] = max(0, $stockQuantity); }
                if ($mkActiveCol !== null) { $sets[] = $mkActiveCol . ' = ?'; $values[] = $isActive ? 1 : 0; }
                if ($mkUpdatedByCol !== null) { $sets[] = $mkUpdatedByCol . ' = ?'; $values[] = (int)($_SESSION['user_id'] ?? 0); }
                if ($mkUpdatedAtCol !== null) { $sets[] = $mkUpdatedAtCol . ' = CURRENT_TIMESTAMP'; }
                if ($mkCategoryCol !== null) { $sets[] = $mkCategoryCol . ' = ?'; $values[] = $category; }
                if ($mkImageCol !== null && (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) { $sets[] = $mkImageCol . ' = ?'; $values[] = $imageUrl; }

                $values[] = $id;
                $stmt = $pdo->prepare('UPDATE marketplace_ce_products SET ' . implode(', ', $sets) . ' WHERE id = ?');
                $stmt->execute($values);
                $response = ['success' => true, 'message' => 'Artículo CE actualizado'];
            } else {
                $columns = [$mkSkuCol, $mkNameCol, $mkDescriptionCol];
                $placeholders = ['?', '?', '?'];
                $values = [$sku, $name, $description];

                if ($mkConditionCol !== null) { $columns[] = $mkConditionCol; $placeholders[] = '?'; $values[] = $conditionLabel; }
                if ($mkPriceCol !== null) { $columns[] = $mkPriceCol; $placeholders[] = '?'; $values[] = $unitPrice; }
                if ($mkStockCol !== null) { $columns[] = $mkStockCol; $placeholders[] = '?'; $values[] = max(0, $stockQuantity); }
                if ($mkImageCol !== null) { $columns[] = $mkImageCol; $placeholders[] = '?'; $values[] = $imageUrl; }
                if ($mkActiveCol !== null) { $columns[] = $mkActiveCol; $placeholders[] = '?'; $values[] = $isActive ? 1 : 0; }
                if ($mkCreatedByCol !== null) { $columns[] = $mkCreatedByCol; $placeholders[] = '?'; $values[] = (int)($_SESSION['user_id'] ?? 0); }
                if ($mkUpdatedByCol !== null) { $columns[] = $mkUpdatedByCol; $placeholders[] = '?'; $values[] = (int)($_SESSION['user_id'] ?? 0); }
                if ($mkCategoryCol !== null) { $columns[] = $mkCategoryCol; $placeholders[] = '?'; $values[] = $category; }

                $stmt = $pdo->prepare('INSERT INTO marketplace_ce_products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')');
                $stmt->execute($values);
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

            set_marketplace_visibility_compatible($pdo, $id, false);
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
            $supplierSkuColumn = sku_column_for_table_admin_supply('products');
            $supplierNameColumn = name_column_for_table_admin_supply('products');
            $skuExpr = $supplierSkuColumn !== null ? ('p.' . $supplierSkuColumn) : "''";
            $nameExpr = $supplierNameColumn !== null ? ('p.' . $supplierNameColumn) : "''";
            $orderByName = $supplierNameColumn !== null ? ('p.' . $supplierNameColumn . ' ASC') : 'p.id ASC';
            $stmt = $pdo->query("SELECT ps.id, ps.product_id, {$skuExpr} AS sku, {$nameExpr} AS product_name, ps.supplier_name, ps.supplier_sku, ps.unit_cost FROM product_suppliers ps JOIN products p ON p.id = ps.product_id ORDER BY ps.supplier_name ASC, {$orderByName}");
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
            $supplierSkuColumn = sku_column_for_table_admin_supply('products');
            $supplierNameColumn = name_column_for_table_admin_supply('products');
            $skuExpr = $supplierSkuColumn !== null ? ('p.' . $supplierSkuColumn) : "''";
            $nameExpr = $supplierNameColumn !== null ? ('p.' . $supplierNameColumn) : "''";
            $orderByName = $supplierNameColumn !== null ? ('p.' . $supplierNameColumn . ' ASC') : 'p.id ASC';
            $stmt = $pdo->prepare("SELECT ps.id, ps.product_id, {$skuExpr} AS sku, {$nameExpr} AS product_name, ps.supplier_name, ps.supplier_sku, ps.unit_cost FROM product_suppliers ps JOIN products p ON p.id = ps.product_id WHERE LOWER(TRIM(ps.supplier_name)) = LOWER(TRIM(?)) ORDER BY {$orderByName}");
            $stmt->execute([$supplierName]);
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'stock':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            // Paginación para mejorar rendimiento
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = max(10, min(100, (int)($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $per_page;
            
            // Usar caché para lista completa
            $cache_key = 'admin_stock_products_' . md5($per_page . '_' . $offset);
            $items = cache_get($cache_key);
            
            if ($items === null) {
                $all_items = list_stock_products_compatible($pdo);
                $items = array_slice($all_items, $offset, $per_page);
                cache_set($cache_key, $items, 300);
            }
            
            // Optimizar imágenes: agregar lazy loading
            $items = array_map(function($item) {
                $item['image_url_thumb'] = str_replace('.svg', '_thumb.svg', $item['image_url']);
                return $item;
            }, $items);
            
            $response = [
                'success' => true,
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => count(list_stock_products_compatible($pdo))
                ]
            ];
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
                    $supplierSkuColumn = sku_column_for_table_admin_supply('products');
                    $supplierNameColumn = name_column_for_table_admin_supply('products');
                    $skuExpr = $supplierSkuColumn !== null ? ('p.' . $supplierSkuColumn) : "''";
                    $nameExpr = $supplierNameColumn !== null ? ('p.' . $supplierNameColumn) : "''";
                    $sp = $pdo->prepare("SELECT {$skuExpr} AS sku, {$nameExpr} AS product_name, ps.supplier_sku FROM product_suppliers ps JOIN products p ON p.id = ps.product_id WHERE ps.id = ? LIMIT 1");
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
    if (($_SESSION['role'] ?? '') === 'admin') {
        $response['debug'] = [
            'action' => (string)$action,
            'detail' => (string)$e->getMessage()
        ];
    }
}

restore_error_handler();

$buffer = ob_get_clean();
if (!empty($buffer)) {
    error_log('admin_supply API buffered output: ' . trim($buffer));
}

// JSON minificado para reducir tamaño de respuesta
echo json_encode($response, JSON_UNESCAPED_UNICODE);
