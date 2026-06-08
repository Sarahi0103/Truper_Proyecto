<?php
require_once '../../config/config.php';
ini_set('display_errors', '0');
ob_start();

require_admin();
require_once __DIR__ . '/ensure-sync.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'stock';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if ($method === 'GET') {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
} else {
    header('Cache-Control: no-cache, must-revalidate');
}

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

function normalize_datetime_value($value): ?string {
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    
    $raw = str_replace('T', ' ', $raw);
    
    // Check if format is DD/MM/YYYY HH:MM(:SS)
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})(?::(\d{2}))?$/', $raw, $matches)) {
        $sec = isset($matches[6]) ? $matches[6] : '00';
        return "{$matches[3]}-{$matches[2]}-{$matches[1]} {$matches[4]}:{$matches[5]}:{$sec}";
    }
    
    $ts = strtotime($raw);
    if ($ts !== false) {
        return date('Y-m-d H:i:s', $ts);
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
    $raw = trim((string)$value);
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') return '';
    // Ensure at least 5 digits by left-padding with zeros, and limit to 6 digits.
    if (strlen($digits) < 5) {
        $digits = str_pad($digits, 5, '0', STR_PAD_LEFT);
    }
    if (strlen($digits) > 6) {
        $digits = substr($digits, 0, 6);
    }
    return $digits;
}

function is_valid_numeric_sku_admin_supply(string $sku): bool {
    // Strict numeric SKU validation: only 5 or 6 digits allowed
    $sku = trim((string)$sku);
    return (bool)preg_match('/^\d{5,6}$/', $sku);
}

// Validation for CREATING new products: only 5-6 digit numeric SKUs
function is_valid_numeric_sku_for_creation_admin_supply(string $sku): bool {
    return (bool)preg_match('/^\d{5,6}$/', trim($sku));
}

// Validation for DELETING products: accept any valid SKU (numeric or with letters)
function is_valid_sku_for_deletion_admin_supply(string $sku): bool {
    $sku = trim($sku);
    return strlen($sku) > 0 && !preg_match('/[<>"%{}|\\^`\[\]]/', $sku);
}

function normalize_category_admin_supply($value): string {
    $text = trim((string)$value);
    $text = mb_strtolower($text, 'UTF-8');
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    if ($normalized === false) {
        $normalized = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }
    return $normalized;
}

// Path helper: ensure API actions operate on project images directory (project_root/images)

function images_root_admin_supply(): string {
    // __DIR__ is /var/www/html/public/api
    // Prefer: /var/www/html/public/images (Docker standard)
    // Fallback: /var/www/html/images (legacy)
    
    // Go up: /var/www/html/public/api -> /var/www/html/public
    $publicDir = dirname(__DIR__); // /var/www/html/public
    
    // Try preferred path first: /var/www/html/public/images
    $preferredPath = $publicDir . '/images';
    
    // If preferred path doesn't exist, try creating it
    if (!is_dir($preferredPath)) {
        try {
            ensure_directory_exists($preferredPath, 0777);
            return $preferredPath;
        } catch (Exception $e) {
            // Fall through to try legacy path
        }
    }
    
    // If we got here and preferred path exists or was created successfully, use it
    if (is_dir($preferredPath)) {
        return $preferredPath;
    }
    
    // Fallback: try legacy path /var/www/html/images
    $projectRoot = dirname($publicDir); // /var/www/html
    $legacyPath = $projectRoot . '/images';
    try {
        ensure_directory_exists($legacyPath, 0777);
        return $legacyPath;
    } catch (Exception $e) {
        // Return preferred anyway and let the caller handle errors
        return $preferredPath;
    }
}

function image_storage_roots_admin_supply(): array {
    $roots = [images_root_admin_supply()];

    $publicDir = dirname(__DIR__);
    $legacyPath = dirname($publicDir) . '/images';
    $roots[] = $legacyPath;

    $roots = array_values(array_unique(array_filter($roots, static function ($path) {
        return is_string($path) && $path !== '';
    })));

    return $roots;
}
// Helper function to create directories with proper permissions
function ensure_directory_exists($path, $perms = 0777) {
    // Normalize path, resolving .. and . segments safely
    $path = str_replace('\\', '/', $path);
    $isAbsolute = strlen($path) > 0 && $path[0] === '/';
    $parts = explode('/', trim($path, '/'));
    $stack = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') {
            if (!empty($stack)) array_pop($stack);
            continue;
        }
        $stack[] = $part;
    }

    $normalized = ($isAbsolute ? '/' : '') . implode('/', $stack);
    if ($normalized === '') $normalized = $isAbsolute ? '/' : '.';

    $current = $isAbsolute ? '' : '';
    $segments = explode('/', ltrim($normalized, '/'));
    foreach ($segments as $seg) {
        if ($seg === '') continue;
        $current .= '/' . $seg;
        if (!is_dir($current)) {
            $mkdir_result = @mkdir($current, $perms, true);
            if (!$mkdir_result && !is_dir($current)) {
                throw new Exception("Could not create directory: $current");
            }
        }
        @chmod($current, $perms);
    }

    return $normalized;
}

// Normalize any image path/url to a relative path starting with images/...
function normalize_image_relative_path_admin_supply(string $path): string {
    $raw = trim($path);
    if ($raw === '') {
        return '';
    }

    $parsed = parse_url($raw);
    if ($parsed !== false && isset($parsed['path'])) {
        $raw = (string)$parsed['path'];
    }

    $raw = ltrim((string)preg_replace('/\?.*$/', '', $raw), '/');
    $imagesPos = stripos($raw, 'images/');
    if ($imagesPos !== false) {
        $raw = substr($raw, $imagesPos);
    }

    return ltrim($raw, '/');
}

function first_existing_column_admin_supply(string $table, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (db_column_exists($table, (string)$candidate)) {
            return (string)$candidate;
        }
    }
    return null;
}

function force_delete_product_dependencies_admin_supply(PDO $pdo, int $productId): int {
    if ($productId <= 0) {
        return 0;
    }

    $sql = "
        SELECT tc.table_schema, tc.table_name, kcu.column_name
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu
          ON tc.constraint_name = kcu.constraint_name
         AND tc.table_schema = kcu.table_schema
        JOIN information_schema.constraint_column_usage ccu
          ON ccu.constraint_name = tc.constraint_name
         AND ccu.table_schema = tc.table_schema
        WHERE tc.constraint_type = 'FOREIGN KEY'
          AND ccu.table_name = 'products'
          AND ccu.column_name = 'id'
        ORDER BY tc.table_schema, tc.table_name
    ";

    $stmt = $pdo->query($sql);
    $refs = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    $deletedRows = 0;
    foreach ($refs as $ref) {
        $schema = (string)($ref['table_schema'] ?? '');
        $table = (string)($ref['table_name'] ?? '');
        $column = (string)($ref['column_name'] ?? '');

        if ($schema === '' || $table === '' || $column === '') {
            continue;
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)
            || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)
            || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            continue;
        }

        if (!db_table_exists($table)) {
            continue;
        }

        $deleteSql = 'DELETE FROM "' . $schema . '"."' . $table . '" WHERE "' . $column . '" = ?';
        $deleteStmt = $pdo->prepare($deleteSql);
        $deleteStmt->execute([$productId]);
        $deletedRows += (int)$deleteStmt->rowCount();
    }

    return $deletedRows;
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
        $stmt->execute([$isVisible, $id]);
        if ($stmt->rowCount() > 0) {
            return;
        }
    } catch (Exception $e) {
        try {
            $stmt = $pdo->prepare('UPDATE marketplace_ce_products SET is_active = ? WHERE id = ?');
            $stmt->execute([$isVisible ? 1 : 0, $id]);
            if ($stmt->rowCount() > 0) {
                return;
            }
        } catch (Exception $e2) {
            // Column may not exist or error, try next candidate
        }
    }

    // Try 'active' column second
    try {
        $stmt = $pdo->prepare('UPDATE marketplace_ce_products SET active = ? WHERE id = ?');
        $stmt->execute([$isVisible, $id]);
        if ($stmt->rowCount() > 0) {
            return;
        }
    } catch (Exception $e) {
        try {
            $stmt = $pdo->prepare('UPDATE marketplace_ce_products SET active = ? WHERE id = ?');
            $stmt->execute([$isVisible ? 1 : 0, $id]);
            if ($stmt->rowCount() > 0) {
                return;
            }
        } catch (Exception $e2) {
            // Column may not exist, try next candidate
        }
    }

    // Legacy schemas may use visible/is_visible columns.
    try {
        $stmt = $pdo->prepare('UPDATE marketplace_ce_products SET is_visible = ? WHERE id = ?');
        $stmt->execute([$isVisible, $id]);
        if ($stmt->rowCount() > 0) {
            return;
        }
    } catch (Exception $e) {
        try {
            $stmt = $pdo->prepare('UPDATE marketplace_ce_products SET is_visible = ? WHERE id = ?');
            $stmt->execute([$isVisible ? 1 : 0, $id]);
            if ($stmt->rowCount() > 0) {
                return;
            }
        } catch (Exception $e2) {
        }
    }

    try {
        $stmt = $pdo->prepare('UPDATE marketplace_ce_products SET visible = ? WHERE id = ?');
        $stmt->execute([$isVisible, $id]);
        if ($stmt->rowCount() > 0) {
            return;
        }
    } catch (Exception $e) {
        try {
            $stmt = $pdo->prepare('UPDATE marketplace_ce_products SET visible = ? WHERE id = ?');
            $stmt->execute([$isVisible ? 1 : 0, $id]);
            if ($stmt->rowCount() > 0) {
                return;
            }
        } catch (Exception $e2) {
        }
    }

    throw new Exception('No existe columna de visibilidad (is_active o active) en marketplace_ce_products');
}

function set_product_visibility_compatible($pdo, int $id, bool $isVisible): void {
    if ($id <= 0) {
        throw new Exception('ID de producto inválido');
    }

    try {
        $stmt = $pdo->prepare('UPDATE products SET is_active = ? WHERE id = ?');
        $stmt->execute([$isVisible, $id]);
        if ($stmt->rowCount() > 0) {
            return;
        }
    } catch (Exception $e) {
        try {
            $stmt = $pdo->prepare('UPDATE products SET is_active = ? WHERE id = ?');
            $stmt->execute([$isVisible ? 1 : 0, $id]);
            if ($stmt->rowCount() > 0) {
                return;
            }
        } catch (Exception $e2) {
        }
    }

    try {
        $stmt = $pdo->prepare('UPDATE products SET active = ? WHERE id = ?');
        $stmt->execute([$isVisible, $id]);
        if ($stmt->rowCount() > 0) {
            return;
        }
    } catch (Exception $e) {
        try {
            $stmt = $pdo->prepare('UPDATE products SET active = ? WHERE id = ?');
            $stmt->execute([$isVisible ? 1 : 0, $id]);
            if ($stmt->rowCount() > 0) {
                return;
            }
        } catch (Exception $e2) {
        }
    }

    try {
        $stmt = $pdo->prepare('UPDATE products SET is_visible = ? WHERE id = ?');
        $stmt->execute([$isVisible, $id]);
        if ($stmt->rowCount() > 0) {
            return;
        }
    } catch (Exception $e) {
        try {
            $stmt = $pdo->prepare('UPDATE products SET is_visible = ? WHERE id = ?');
            $stmt->execute([$isVisible ? 1 : 0, $id]);
            if ($stmt->rowCount() > 0) {
                return;
            }
        } catch (Exception $e2) {
        }
    }

    try {
        $stmt = $pdo->prepare('UPDATE products SET visible = ? WHERE id = ?');
        $stmt->execute([$isVisible, $id]);
        if ($stmt->rowCount() > 0) {
            return;
        }
    } catch (Exception $e) {
        try {
            $stmt = $pdo->prepare('UPDATE products SET visible = ? WHERE id = ?');
            $stmt->execute([$isVisible ? 1 : 0, $id]);
            if ($stmt->rowCount() > 0) {
                return;
            }
        } catch (Exception $e2) {
        }
    }

    throw new Exception('No existe columna de visibilidad compatible en products');
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
        // 1. Fast direct lookup using database index (0ms)
        if ($excludeId > 0) {
            $stmt = $pdo->prepare("SELECT id, {$skuColumn} AS sku FROM {$table} WHERE {$skuColumn} = ? AND id <> ? LIMIT 1");
            $stmt->execute([$sku, $excludeId]);
        } else {
            $stmt = $pdo->prepare("SELECT id, {$skuColumn} AS sku FROM {$table} WHERE {$skuColumn} = ? LIMIT 1");
            $stmt->execute([$sku]);
        }
        if ($stmt->fetch()) {
            return true;
        }

        // 2. Fallback lookup with LIKE to fetch potential prefix/suffix variations (max 100 rows)
        $likePattern = '%' . $sku . '%';
        if ($excludeId > 0) {
            $stmt = $pdo->prepare("SELECT id, {$skuColumn} AS sku FROM {$table} WHERE {$skuColumn} LIKE ? AND id <> ? LIMIT 100");
            $stmt->execute([$likePattern, $excludeId]);
        } else {
            $stmt = $pdo->prepare("SELECT id, {$skuColumn} AS sku FROM {$table} WHERE {$skuColumn} LIKE ? LIMIT 100");
            $stmt->execute([$likePattern]);
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
    // Seeder eliminado: el catálogo base ya no existe
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

function insert_category_and_get_id_admin_supply($pdo, string $name, int $sortOrder, bool $isActive, string $context = 'stock'): int {
    $name = trim((string)$name);
    if ($name === '') {
        return 0;
    }
    
    // Check for existing category with normalized name
    $nameNormalized = normalize_category_admin_supply($name);
    try {
        $stmt = $pdo->query("SELECT id, name FROM product_categories");
        $allCategories = $stmt ? $stmt->fetchAll() : [];
        foreach ($allCategories as $cat) {
            $catNormalized = normalize_category_admin_supply($cat['name']);
            if ($catNormalized === $nameNormalized) {
                return (int)$cat['id'];
            }
        }
    } catch (Exception $ignored) {
        // Continue with insertion
    }
    
    // PostgreSQL supports RETURNING; MySQL/MariaDB may not.
    // Try binding is_active as boolean first.
    try {
        $stmt = $pdo->prepare("INSERT INTO product_categories (name, sort_order, is_active, context) VALUES (?, ?, ?, ?) RETURNING id");
        $stmt->execute([$name, $sortOrder, $isActive, $context]);
        $createdId = (int)$stmt->fetchColumn();
        if ($createdId > 0) {
            return $createdId;
        }
    } catch (Exception $ignored) {
        // Fallback 1: try binding is_active as integer
        try {
            $stmt = $pdo->prepare("INSERT INTO product_categories (name, sort_order, is_active, context) VALUES (?, ?, ?, ?) RETURNING id");
            $stmt->execute([$name, $sortOrder, $isActive ? 1 : 0, $context]);
            $createdId = (int)$stmt->fetchColumn();
            if ($createdId > 0) {
                return $createdId;
            }
        } catch (Exception $ignored2) {
            // Fallback 2: Generic insert without RETURNING (try boolean first)
            try {
                $stmt = $pdo->prepare("INSERT INTO product_categories (name, sort_order, is_active, context) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $sortOrder, $isActive, $context]);
                return (int)$pdo->lastInsertId();
            } catch (Exception $e) {
                // Fallback 3: Generic insert with integer
                try {
                    $stmt = $pdo->prepare("INSERT INTO product_categories (name, sort_order, is_active, context) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $sortOrder, $isActive ? 1 : 0, $context]);
                    return (int)$pdo->lastInsertId();
                } catch (Exception $e2) {
                    // Final attempt: lookup just in case it was created concurrently
                }
            }
        }
    }
    
    try {
        $findStmt = $pdo->prepare("SELECT id FROM product_categories WHERE LOWER(name) = LOWER(?) ORDER BY id DESC LIMIT 1");
        $findStmt->execute([$name]);
        return (int)$findStmt->fetchColumn();
    } catch (Exception $finalEx) {
        return 0;
    }
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
            context VARCHAR(20) NOT NULL DEFAULT 'stock',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("ALTER TABLE product_categories ADD COLUMN IF NOT EXISTS context VARCHAR(20) DEFAULT 'stock'");
        $created = true;
    } catch (Exception $ignored) {
    }

    // MySQL/MariaDB style fallback.
    if (!$created) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS product_categories (
                id SERIAL PRIMARY KEY,
                name VARCHAR(120) NOT NULL UNIQUE,
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_active BOOLEAN NOT NULL DEFAULT 1,
                context VARCHAR(20) NOT NULL DEFAULT 'stock',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("ALTER TABLE product_categories ADD COLUMN IF NOT EXISTS context VARCHAR(20) DEFAULT 'stock'");
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
    
    // Add image_url and update_type columns if they don't exist (migration for existing tables)
    try {
        $pdo->exec("ALTER TABLE homepage_updates ADD COLUMN IF NOT EXISTS image_url TEXT");
    } catch (Exception $ignored) {}
    try {
        $pdo->exec("ALTER TABLE homepage_updates ADD COLUMN IF NOT EXISTS update_type VARCHAR(20) NOT NULL DEFAULT 'noticia'");
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
        $pdo->exec('ALTER TABLE products ALTER COLUMN image_url TYPE TEXT');
        $pdo->exec('ALTER TABLE marketplace_ce_products ALTER COLUMN image_url TYPE TEXT');
        $pdo->exec('ALTER TABLE homepage_updates ALTER COLUMN image_url TYPE TEXT');
    } catch (Exception $ignored) {
    }
}

function convert_image_to_base64_admin_supply(string $tmpName, string $mimeType): string {
    // Procesa la imagen (redimensiona/comprime) y la guarda en disk en /images/products
    // Retorna la ruta web relativa (por ejemplo: images/products/12345_abcd.webp)
    $maxWidth = 800;
    $maxHeight = 800;

    $productsDir = images_root_admin_supply() . '/products';
    if (!is_dir($productsDir)) {
        mkdir($productsDir, 0755, true);
    }

    // Si no tenemos GD, simplemente movemos/copiamos el archivo original y devolvemos la ruta
    if (!function_exists('imagecreatefromstring')) {
        $ext = 'jpg';
        if (strpos($mimeType, 'png') !== false) $ext = 'png';
        if (strpos($mimeType, 'webp') !== false) $ext = 'webp';
        if (strpos($mimeType, 'gif') !== false) $ext = 'gif';

        $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $productsDir . '/' . $filename;
        if (!@move_uploaded_file($tmpName, $destPath)) {
            // try copy as fallback
            @copy($tmpName, $destPath);
        }
        return 'images/products/' . $filename;
    }

    $imageString = file_get_contents($tmpName);
    $img = @imagecreatefromstring($imageString);
    if (!$img) {
        // Save original as fallback
        $ext = 'jpg';
        if (strpos($mimeType, 'png') !== false) $ext = 'png';
        if (strpos($mimeType, 'webp') !== false) $ext = 'webp';
        if (strpos($mimeType, 'gif') !== false) $ext = 'gif';

        $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $productsDir . '/' . $filename;
        file_put_contents($destPath, $imageString);
        return 'images/products/' . $filename;
    }

    $width = imagesx($img);
    $height = imagesy($img);

    if ($width > $maxWidth || $height > $maxHeight) {
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        $newImg = imagecreatetruecolor($newWidth, $newHeight);

        // Mantener transparencia para PNG y WEBP
        if ($mimeType === 'image/png' || $mimeType === 'image/webp' || $mimeType === 'image/gif') {
            imagealphablending($newImg, false);
            imagesavealpha($newImg, true);
            $transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
            imagefilledrectangle($newImg, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Usar interpolación de mejor calidad (cúbica en lugar de bilineal)
        imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Aplicar unsharp mask para mejorar nitidez después del remuestreo
        if (function_exists('imageconvolution')) {
            $matrix = [
                [-1, -1, -1],
                [-1, 16, -1],
                [-1, -1, -1]
            ];
            @imageconvolution($newImg, $matrix, 8, 0);
        }
        
        imagedestroy($img);
        $img = $newImg;
    }

    // Serializar a buffer y guardar archivo en disco con calidad mejorada
    ob_start();
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        if (function_exists('imagewebp')) {
            // Calidad más alta para conservar mejor detalle visual
            imagewebp($img, null, 95);
            $finalExt = 'webp';
        } else {
            // PNG sigue siendo sin pérdida; se mantiene la ruta de guardado
            imagepng($img, null, 6);
            $finalExt = 'png';
        }
    } else {
        // Calidad más alta para JPEG
        imagejpeg($img, null, 95);
        $finalExt = 'jpg';
    }
    $compressedData = ob_get_clean();
    imagedestroy($img);

    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $finalExt;
    $destPath = $productsDir . '/' . $filename;
    file_put_contents($destPath, $compressedData);

    return 'images/products/' . $filename;
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

    // Create products directory if it doesn't exist using helper
    $productsDir = images_root_admin_supply() . '/products';
    try {
        ensure_directory_exists($productsDir, 0777);
    } catch (Exception $e) {
        throw new Exception('No se pudo crear directorio de productos: ' . $e->getMessage());
    }

    $incomingHash = gallery_image_hash_admin_supply($tmp);
    if ($incomingHash !== '') {
        $existingImage = gallery_existing_image_by_hash_admin_supply($productsDir, $incomingHash);
        if ($existingImage !== null) {
            return 'images/products/' . $existingImage;
        }
    }

    // Ensure it's writable
    if (!is_writable($productsDir)) {
        @chmod($productsDir, 0777);
        if (!is_writable($productsDir)) {
            throw new Exception("Directorio no tiene permisos de escritura: $productsDir");
        }
    }

    // Generate unique filename with timestamp
    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filepath = $productsDir . '/' . $filename;

    // Validate and move file
    if (!move_uploaded_file($tmp, $filepath)) {
        throw new Exception('No se pudo guardar la imagen');
    }

    // Ensure file has correct permissions
    @chmod($filepath, 0666);

    if ($incomingHash !== '') {
        @file_put_contents($filepath . '.sha1', $incomingHash, LOCK_EX);
    }

    // Return web-accessible path
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
    return images_root_admin_supply() . '/products/gallery/' . $sku;
}

function product_gallery_dir_legacy_admin_supply(string $sku): string {
    return images_root_admin_supply() . '/products/by_code/' . $sku;
}

function list_product_gallery_files_admin_supply(string $sku): array {
    $sku = normalize_sku_admin_supply($sku);
    if ($sku === '') return [];

    $result = [];

    // Primary: new gallery/ directory (admin-uploaded images)
        $galleryDir = images_root_admin_supply() . '/products/gallery/' . $sku;
        $legacyDir = images_root_admin_supply() . '/products/by_code/' . $sku;
    if (is_dir($galleryDir)) {
        $matches = glob($galleryDir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
        if (!empty($matches) && is_array($matches)) {
            usort($matches, function ($a, $b) {
                return strcmp((string)$a, (string)$b);
            });
            foreach ($matches as $path) {
                $result[] = 'images/products/gallery/' . $sku . '/' . basename($path);
            }
        }
    }

    // Fallback: legacy by_code/ directory (catalog seed images)
    if (is_dir($legacyDir)) {
        $matches = glob($legacyDir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
        if (!empty($matches) && is_array($matches)) {
            usort($matches, function ($a, $b) {
                $scoreA = admin_supply_image_priority_score($a);
                $scoreB = admin_supply_image_priority_score($b);
                if ($scoreA === $scoreB) return strcmp((string)$a, (string)$b);
                return $scoreA <=> $scoreB;
            });
            foreach ($matches as $path) {
                $legacyPath = 'images/products/by_code/' . $sku . '/' . basename($path);
                if (!in_array($legacyPath, $result, true)) {
                    $result[] = $legacyPath;
                }
            }
        }
    }

    return $result;
}

function normalize_product_gallery_images_admin_supply(array $images): array {
    $normalized = [];
    foreach ($images as $image) {
        $value = trim((string)$image);
        if ($value === '' || strpos($value, 'default-product.svg') !== false) {
            continue;
        }
        if (!in_array($value, $normalized, true)) {
            $normalized[] = $value;
        }
    }

    return $normalized;
}

function gallery_image_hash_admin_supply(string $filePath): string {
    if ($filePath === '' || !is_file($filePath) || !is_readable($filePath)) {
        return '';
    }

    $hash = @hash_file('sha1', $filePath);
    return is_string($hash) ? trim($hash) : '';
}

function gallery_existing_image_by_hash_admin_supply(string $galleryDir, string $hash): ?string {
    $hash = trim($hash);
    if ($hash === '' || !is_dir($galleryDir)) {
        return null;
    }

    $hashFiles = glob($galleryDir . '/*.sha1');
    if (!is_array($hashFiles)) {
        return null;
    }

    foreach ($hashFiles as $hashFile) {
        if (!is_file($hashFile) || !is_readable($hashFile)) {
            continue;
        }

        $storedHash = trim((string)@file_get_contents($hashFile));
        if ($storedHash === '' || strcasecmp($storedHash, $hash) !== 0) {
            continue;
        }

        $imagePath = preg_replace('/\.sha1$/i', '', (string)$hashFile);
        if ($imagePath !== '' && is_file($imagePath)) {
            return basename($imagePath);
        }
    }

    $imageFiles = glob($galleryDir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
    if (!is_array($imageFiles)) {
        return null;
    }

    foreach ($imageFiles as $imageFile) {
        if (!is_file($imageFile) || !is_readable($imageFile)) {
            continue;
        }

        $existingHash = gallery_image_hash_admin_supply((string)$imageFile);
        if ($existingHash !== '' && strcasecmp($existingHash, $hash) === 0) {
            return basename((string)$imageFile);
        }
    }

    return null;
}

function canonical_product_gallery_path_admin_supply(string $sku, string $imagePath): string {
    $sku = normalize_sku_admin_supply($sku);
    $raw = trim((string)$imagePath);
    if (!is_valid_numeric_sku_admin_supply($sku) || $raw === '') {
        return $raw;
    }

    $relative = ltrim($raw, '/');
    $filename = basename($relative);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return $raw;
    }

    if (strpos($relative, 'images/products/gallery/' . $sku . '/') === 0) {
        return 'images/products/gallery/' . $sku . '/' . $filename;
    }

    if (strpos($relative, 'images/products/by_code/' . $sku . '/') === 0) {
        return 'images/products/gallery/' . $sku . '/' . $filename;
    }

    if (strpos($relative, 'images/products/') === 0) {
        return 'images/products/gallery/' . $sku . '/' . $filename;
    }

    return $raw;
}

function ensure_canonical_gallery_image_admin_supply(string $sku, string $imagePath): string {
    $sku = normalize_sku_admin_supply($sku);
    $raw = trim((string)$imagePath);
    if (!is_valid_numeric_sku_admin_supply($sku) || $raw === '') {
        return $raw;
    }

    $canonicalRelative = canonical_product_gallery_path_admin_supply($sku, $raw);
    $sourceRelative = normalize_image_relative_path_admin_supply($raw);
    $sourcePath = images_root_admin_supply() . '/' . preg_replace('#^images/#', '', $sourceRelative);
    if (!is_file($sourcePath)) {
        return $canonicalRelative;
    }

    if ($canonicalRelative === $raw) {
        return $raw;
    }

    $canonicalPath = images_root_admin_supply() . '/' . preg_replace('#^images/#', '', ltrim($canonicalRelative, '/'));
    $canonicalDir = dirname($canonicalPath);
    if (!is_dir($canonicalDir)) {
        @mkdir($canonicalDir, 0777, true);
    }
    @chmod($canonicalDir, 0777);

    if (!is_file($canonicalPath)) {
        @copy($sourcePath, $canonicalPath);
        @chmod($canonicalPath, 0666);
    }

    return is_file($canonicalPath) ? $canonicalRelative : $raw;
}

function normalize_and_persist_gallery_images_admin_supply($pdo, string $sku, array $images): array {
    $sku = normalize_sku_admin_supply($sku);
    if (!is_valid_numeric_sku_admin_supply($sku)) {
        return normalize_product_gallery_images_admin_supply($images);
    }

    $normalized = [];
    foreach (normalize_product_gallery_images_admin_supply($images) as $image) {
        $normalized[] = ensure_canonical_gallery_image_admin_supply($sku, $image);
    }

    return persist_product_gallery_images_admin_supply($pdo, $sku, $normalized);
}

function persist_product_gallery_images_admin_supply($pdo, string $sku, array $images): array {
    error_log("PERSIST REQ SKU: " . $sku);
    error_log("PERSIST REQ IMAGES: " . json_encode($images));
    $images = normalize_product_gallery_images_admin_supply($images);
    if (empty($images)) {
        return [];
    }

    $cover = ensure_canonical_gallery_image_admin_supply($sku, $images[0]);
    $images[0] = $cover;
    $images = normalize_product_gallery_images_admin_supply($images);
    $json = json_encode($images, JSON_UNESCAPED_UNICODE);

    foreach (['products', 'marketplace_ce_products'] as $table) {
        if (!db_table_exists($table)) {
            continue;
        }

        try {
            // Select candidates to avoid matching longer SKUs containing $sku
            $stmt = $pdo->prepare("SELECT id, sku FROM {$table} WHERE sku = ? OR sku LIKE ?");
            $stmt->execute([$sku, "%{$sku}%"]);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $matched_ids = [];
            foreach ($candidates as $cnd) {
                if (normalize_sku_admin_supply($cnd['sku']) === $sku) {
                    $matched_ids[] = $cnd['id'];
                }
            }

            if (!empty($matched_ids)) {
                $sets = ['image_url = ?'];
                $params = [$cover];
                
                if (db_column_exists($table, 'variants_json')) {
                    $sets[] = 'variants_json = ?';
                    $params[] = $json;
                }
                
                $placeholders = implode(',', array_fill(0, count($matched_ids), '?'));
                $params = array_merge($params, $matched_ids);
                
                $stmt = $pdo->prepare("UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id IN ($placeholders)");
                $res = $stmt->execute($params);
                $rowCount = $stmt->rowCount();
                error_log("UPDATE {$table} execute result: " . var_export($res, true) . ", rowCount: " . $rowCount);
            }
        } catch (Exception $ignored) {
            error_log("Error persisting gallery to {$table}: " . $ignored->getMessage());
        }
    }

    return $images;
}

function list_product_gallery_images_admin_supply(string $sku): array {
    global $pdo;
    if (!is_valid_numeric_sku_admin_supply($sku)) return [];

    try {
        $final = [];
        $needsPersist = false;
        $diskImages = list_product_gallery_files_admin_supply($sku);

        $mergeImage = function (string $value) use (&$final) {
            $value = trim($value);
            if ($value === '' || strpos($value, 'default-product.svg') !== false) {
                return;
            }

            if (!in_array($value, $final, true)) {
                $final[] = $value;
            }
        };

        $extractRowImages = function (array $row): array {
            $images = [];

            if (!empty($row['image_url'])) {
                $images[] = (string)$row['image_url'];
            }

            if (!empty($row['variants_json'])) {
                $decoded = json_decode($row['variants_json'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        $images[] = (string)$item;
                    }
                }
            }

            return normalize_product_gallery_images_admin_supply($images);
        };

        $productExists = false;
        foreach (['products', 'marketplace_ce_products'] as $table) {
            if (!db_table_exists($table)) {
                continue;
            }

            try {
                $stmt = $pdo->prepare("SELECT variants_json, image_url, sku FROM {$table} WHERE sku = ? OR sku LIKE ?");
                $stmt->execute([$sku, "%{$sku}%"]);
                $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $row = null;
                foreach ($candidates as $cnd) {
                    if (normalize_sku_admin_supply($cnd['sku']) === $sku) {
                        $row = $cnd;
                        break;
                    }
                }
                if ($row) {
                    $productExists = true;
                    $rowImages = array_map(function ($path) use ($sku) {
                        return ensure_canonical_gallery_image_admin_supply($sku, (string)$path);
                    }, $extractRowImages($row));
                    $rowImages = normalize_product_gallery_images_admin_supply($rowImages);
                    foreach ($rowImages as $rowImage) {
                        $mergeImage($rowImage);
                    }

                    if ($rowImages !== $final) {
                        $needsPersist = true;
                    }
                }
            } catch (Exception $ignored) {
            }
        }

        // Always merge on-disk gallery files so legacy images are recovered.
        // Deleted images will not reappear because the physical file is removed on delete.
        foreach ($diskImages as $fileImage) {
            $mergeImage(ensure_canonical_gallery_image_admin_supply($sku, (string)$fileImage));
        }

        if (!empty($diskImages)) {
            $needsPersist = true;
        }

        if (empty($final)) {
            return [];
        }

        if (!$needsPersist) {
            return $final;
        }

        return normalize_and_persist_gallery_images_admin_supply($pdo, $sku, $final);
    } catch (Exception $e) {}
    
    return [];
}

function list_product_gallery_uploaded_images_admin_supply(string $sku): array {
    if (!is_valid_numeric_sku_admin_supply($sku)) return [];
    return list_product_gallery_images_admin_supply($sku);
}

function delete_product_gallery_file_admin_supply(string $sku, string $imagePath): void {
    if (!is_valid_sku_for_deletion_admin_supply($sku)) {
        return;
    }

    $raw = trim($imagePath);
    if ($raw === '') {
        return;
    }

    // Normalize input: strip host/query and keep only images/... segment when present.
    $relative = normalize_image_relative_path_admin_supply($raw);

    // Extract filename
    $filename = basename($relative);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    // Candidate paths to attempt deletion across all storage roots
    $candidates = [];
    foreach (image_storage_roots_admin_supply() as $imagesRoot) {
        $candidates[] = $imagesRoot . '/products/gallery/' . $sku . '/' . $filename;
        $candidates[] = $imagesRoot . '/products/by_code/' . $sku . '/' . $filename;
    }

    // If we got a relative path, try it directly as well.
    if ($relative !== '') {
        $relativeUnderImages = preg_replace('#^images/#', '', $relative);
        foreach (image_storage_roots_admin_supply() as $imagesRoot) {
            $candidates[] = $imagesRoot . '/' . ltrim((string)$relativeUnderImages, '/');
        }
    }

    // Also attempt deleting any file that ends with the filename under gallery dirs.
    foreach (image_storage_roots_admin_supply() as $imagesRoot) {
        $wildGallery = glob($imagesRoot . '/products/gallery/' . $sku . '/*' . $filename);
        if (is_array($wildGallery)) {
            foreach ($wildGallery as $wf) {
                $candidates[] = $wf;
            }
        }
    }

    foreach (image_storage_roots_admin_supply() as $imagesRoot) {
        $wildLegacy = glob($imagesRoot . '/products/by_code/' . $sku . '/*' . $filename);
        if (is_array($wildLegacy)) {
            foreach ($wildLegacy as $wf) {
                $candidates[] = $wf;
            }
        }
    }

    foreach (array_unique($candidates) as $target) {
        if (is_file($target)) {
            @unlink($target);
        }
    }
}

function remove_directory_recursive_admin_supply(string $dirPath): bool {
    if ($dirPath === '' || !is_dir($dirPath)) {
        return false;
    }

    $items = @scandir($dirPath);
    if (!is_array($items)) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dirPath . '/' . $item;
        if (is_dir($path)) {
            remove_directory_recursive_admin_supply($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }

    // Try multiple times to remove directory (handle race conditions)
    for ($attempt = 0; $attempt < 3; $attempt++) {
        if (@rmdir($dirPath)) {
            return true;
        }
        usleep(100000); // 100ms between attempts
    }

    return false;
}

function purge_gallery_image_references_admin_supply($pdo, string $sku, string $imagePath): void {
    $sku = normalize_sku_admin_supply($sku);
    $raw = trim($imagePath);
    if (!is_valid_sku_for_deletion_admin_supply($sku) || $raw === '') {
        return;
    }

    $relative = normalize_image_relative_path_admin_supply($raw);
    $filename = basename($relative);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $pathsToRemove = array_values(array_unique(array_filter([
        $relative,
        'images/products/gallery/' . $sku . '/' . $filename,
        'images/products/by_code/' . $sku . '/' . $filename,
    ])));

    foreach (['products', 'marketplace_ce_products'] as $table) {
        if (!db_table_exists($table)) {
            continue;
        }

        try {
            $skuColumn = sku_column_for_table_admin_supply($table);
            if ($skuColumn === null) {
                continue;
            }

            $stmt = $pdo->query("SELECT id, {$skuColumn} AS sku, image_url, variants_json FROM {$table}");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $rowSku = normalize_sku_admin_supply($row['sku'] ?? '');
                if (!is_valid_sku_for_deletion_admin_supply($rowSku) || $rowSku !== $sku) {
                    continue;
                }

                $changed = false;
                $nextImageUrl = trim((string)($row['image_url'] ?? ''));
                $nextImageNormalized = normalize_image_relative_path_admin_supply($nextImageUrl);
                if ($nextImageUrl !== '' && (
                    in_array($nextImageUrl, $pathsToRemove, true)
                    || in_array($nextImageNormalized, $pathsToRemove, true)
                    || basename($nextImageNormalized) === $filename
                )) {
                    $nextImageUrl = 'images/products/default-product.svg';
                    $changed = true;
                }

                $nextVariants = [];
                if (!empty($row['variants_json'])) {
                    $decoded = json_decode((string)$row['variants_json'], true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $value) {
                            $value = trim((string)$value);
                            $valueNormalized = normalize_image_relative_path_admin_supply($value);
                            if ($value === '' || in_array($value, $pathsToRemove, true) || in_array($valueNormalized, $pathsToRemove, true) || basename($valueNormalized) === $filename) {
                                $changed = true;
                                continue;
                            }
                            if (!in_array($value, $nextVariants, true)) {
                                $nextVariants[] = $value;
                            }
                        }
                    }
                }

                if ($changed) {
                    $nextJson = json_encode($nextVariants, JSON_UNESCAPED_UNICODE);
                    $update = $pdo->prepare("UPDATE {$table} SET image_url = ?, variants_json = ? WHERE id = ?");
                    $update->execute([$nextImageUrl, $nextJson, (int)($row['id'] ?? 0)]);
                }
            }
        } catch (Exception $ignored) {
        }
    }
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

    $maxFileSize = 10 * 1024 * 1024; // 10MB
    if (($file['size'] ?? 0) > $maxFileSize) {
        throw new Exception('La imagen excede el tamaño máximo permitido de 10MB');
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $original = (string)($file['name'] ?? 'image');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new Exception('Formato de imagen no permitido');
    }

    // Guardar en disco: images/products/gallery/{sku}/
    $imagesRoot = images_root_admin_supply();
    $galleryDir = $imagesRoot . '/products/gallery/' . $sku;
    $incomingHash = gallery_image_hash_admin_supply($tmp);
    
    // Use helper to create all necessary directories
    try {
        ensure_directory_exists($imagesRoot, 0777);
        ensure_directory_exists($imagesRoot . '/products', 0777);
        ensure_directory_exists($imagesRoot . '/products/gallery', 0777);
        ensure_directory_exists($galleryDir, 0777);
    } catch (Exception $e) {
        throw new Exception("Error al crear estructura de directorios: " . $e->getMessage());
    }

    if ($incomingHash !== '') {
        $existingImage = gallery_existing_image_by_hash_admin_supply($galleryDir, $incomingHash);
        if ($existingImage !== null) {
            return 'images/products/gallery/' . $sku . '/' . $existingImage;
        }
    }

    // Final validation
    if (!is_dir($galleryDir)) {
        throw new Exception("No se pudo crear directorio de galería después de intentos: $galleryDir");
    }
    if (!is_writable($galleryDir)) {
        // Try one more time to fix permissions
        if (!@chmod($galleryDir, 0777)) {
            throw new Exception("Directorio de galería no tiene permisos de escritura: $galleryDir");
        }
        // Verify it's writable now
        if (!is_writable($galleryDir)) {
            throw new Exception("No se puede establecer permisos de escritura en: $galleryDir");
        }
    }

    // Nombre de archivo: timestamp + random para evitar colisiones
    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    $destPath = $galleryDir . '/' . $filename;

    // Intentar redimensionar con GD si está disponible
    if (function_exists('imagecreatefromstring')) {
        $imageString = file_get_contents($tmp);
        if ($imageString === false) {
            throw new Exception("No se pudo leer archivo de imagen temporal");
        }
        
        $img = @imagecreatefromstring($imageString);
        if ($img) {
            $maxW = 1400; $maxH = 1400;
            $w = imagesx($img); $h = imagesy($img);
            if ($w > $maxW || $h > $maxH) {
                $ratio = min($maxW / $w, $maxH / $h);
                $nw = (int)($w * $ratio); $nh = (int)($h * $ratio);
                $newImg = imagecreatetruecolor($nw, $nh);
                if (in_array($ext, ['png', 'gif', 'webp'])) {
                    imagealphablending($newImg, false);
                    imagesavealpha($newImg, true);
                    $transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
                    imagefilledrectangle($newImg, 0, 0, $nw, $nh, $transparent);
                }
                imagecopyresampled($newImg, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                // Aplicar sharpen para mejorar nitidez tras resize
                if (function_exists('imageconvolution')) {
                    $sharpen = [[-1,-1,-1],[-1,16,-1],[-1,-1,-1]];
                    @imageconvolution($newImg, $sharpen, 8, 0);
                }
                imagedestroy($img);
                $img = $newImg;
            }
            
            $saved = false;
            if ($ext === 'png') {
                $saved = imagepng($img, $destPath, 3); // 0=sin compresión, 9=máxima; 3=alta calidad
            } elseif (in_array($ext, ['webp']) && function_exists('imagewebp')) {
                $saved = imagewebp($img, $destPath, 92);
            } else {
                $saved = imagejpeg($img, $destPath, 92);
                if ($saved) {
                    // normalize extension to jpg
                    $filename = pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
                    $newDestPath = $galleryDir . '/' . $filename;
                    if (is_file($destPath) && $destPath !== $newDestPath) {
                        rename($destPath, $newDestPath);
                    }
                    $destPath = $newDestPath;
                }
            }
            imagedestroy($img);
            
            if (!$saved) {
                throw new Exception("No se pudo guardar imagen procesada");
            }

            if ($incomingHash !== '') {
                @file_put_contents($destPath . '.sha1', $incomingHash, LOCK_EX);
            }
        } else {
            // GD no pudo procesar, mover archivo directo
            if (!move_uploaded_file($tmp, $destPath)) {
                throw new Exception("No se pudo mover archivo de imagen: " . error_get_last()['message'] ?? 'error desconocido');
            }

            if ($incomingHash !== '') {
                @file_put_contents($destPath . '.sha1', $incomingHash, LOCK_EX);
            }
        }
    } else {
        if (!move_uploaded_file($tmp, $destPath)) {
            throw new Exception("No se pudo mover archivo de imagen (GD no disponible): " . error_get_last()['message'] ?? 'error desconocido');
        }

        if ($incomingHash !== '') {
            @file_put_contents($destPath . '.sha1', $incomingHash, LOCK_EX);
        }
    }
    
    if (!is_file($destPath)) {
        throw new Exception("Archivo de imagen no se guardó correctamente");
    }

    foreach (image_storage_roots_admin_supply() as $mirrorRoot) {
        if ($mirrorRoot === $imagesRoot) {
            continue;
        }

        $mirrorDir = $mirrorRoot . '/products/gallery/' . $sku;
        try {
            ensure_directory_exists($mirrorRoot, 0777);
            ensure_directory_exists($mirrorRoot . '/products', 0777);
            ensure_directory_exists($mirrorRoot . '/products/gallery', 0777);
            ensure_directory_exists($mirrorDir, 0777);
        } catch (Exception $e) {
            continue;
        }

        $mirrorPath = $mirrorDir . '/' . $filename;
        if ($mirrorPath !== $destPath) {
            @copy($destPath, $mirrorPath);
            if ($incomingHash !== '') {
                @copy($destPath . '.sha1', $mirrorPath . '.sha1');
            }
        }
    }

    return 'images/products/gallery/' . $sku . '/' . $filename;
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

    $orderedImages = normalize_product_gallery_images_admin_supply($orderedImages);
    if (empty($orderedImages)) return [];

    // Ensure all images are canonicalized and persisted so deleted/legacy
    // files are not re-introduced by partial normalization.
    return normalize_and_persist_gallery_images_admin_supply($pdo, $sku, $orderedImages);
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
        $barcodeValue = trim((string)($payload['barcode'] ?? ''));
        $values[] = $barcodeValue === '' ? null : $barcodeValue;
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
            $values[] = $payload['variants_json'] ?? '[]';
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
    // Handle barcode carefully: avoid inserting empty string which may violate unique constraint.
    if (db_column_exists('products', 'barcode')) {
        if (array_key_exists('barcode', $payload)) {
            $barcodeVal = trim((string)($payload['barcode'] ?? ''));
            if ($barcodeVal === '') {
                $sets[] = 'barcode = NULL';
            } else {
                $sets[] = 'barcode = ?';
                $values[] = $barcodeVal;
            }
        }
    }
    if (db_column_exists('products', 'image_url')) { $sets[] = 'image_url = ?'; $values[] = $payload['image_url']; }
        if (db_column_exists('products', 'variants_json')) { $sets[] = 'variants_json = ?'; $values[] = $payload['variants_json'] ?? '[]'; }
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
    // Seeder eliminado: la BD se gestiona manualmente
    return;
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

        // Skip base64 data URIs — too large for the reference dropdown and break onclick attrs
        if (strpos($path, 'data:') === 0) {
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

    $scanDir(images_root_admin_supply() . '/products', 'images/products');
    $scanDir(images_root_admin_supply(), 'images');

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

    // Seeder eliminado: no se agregan imagenes del catalogo base

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
        $baseDir = images_root_admin_supply() . '/products/by_code';
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

    // Optimization: check first-class disk cache by SKU folder to avoid heavy DB queries
    if (isset($cache[$sku])) {
        return $cache[$sku];
    }

    // Fallback to database/gallery lookup only if not in first-class cache
    $galleryImages = list_product_gallery_images_admin_supply($sku);
    if (!empty($galleryImages) && is_array($galleryImages)) {
        return (string)$galleryImages[0];
    }

    return null;
}

function admin_supply_local_image_exists(string $path): bool {
    $path = trim($path);
    if ($path === '' || strpos($path, 'data:image/') === 0 || preg_match('/^https?:\/\//i', $path) === 1) {
        return $path !== '';
    }

    // Point to public/ directory instead of public/api/ to correctly verify if local image exists
    return is_file(dirname(__DIR__) . '/' . ltrim($path, '/'));
}

function apply_catalog_image_fallback_admin_supply(array $item): array {
    $current = trim((string)($item['image_url'] ?? ''));
    $needsFallback = ($current === '' || strcasecmp($current, 'images/products/default-product.svg') === 0 || !admin_supply_local_image_exists($current));
    if (!$needsFallback) {
        return $item;
    }

    $resolved = resolve_admin_supply_image_by_sku($item['sku'] ?? '');
    if ($resolved !== null && $resolved !== '') {
        $item['image_url'] = $resolved;
    }

    return $item;
}

function homepage_updates_active_column_admin_supply(): ?string {
    return first_existing_column_admin_supply('homepage_updates', ['is_active', 'active']);
}

function list_stock_products_compatible($pdo, int $limit = 50, int $offset = 0): array {
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

    // Optimized SQL query with LIMIT and OFFSET (Explicitly cast for PostgreSQL)
    $sql = "SELECT id, {$skuSelect}, {$nameSelect}, {$descriptionSelect}, {$categorySelect}, {$stockSelect}, {$reorderSelect}, {$priceSelect}, {$imageSelect}, {$isActiveSelect} 
            FROM products 
            ORDER BY id DESC 
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    try {
        $stmt = $pdo->query($sql);
        $items = $stmt ? $stmt->fetchAll() : [];
        
        // Resolve image fallback for the current page only
        $items = array_map('apply_catalog_image_fallback_admin_supply', $items);
        
        return $items;
    } catch (Exception $e) {
        return [];
    }
}

function count_stock_products_compatible($pdo): int {
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
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
    $write_actions = ['product-save', 'product-delete', 'marketplace-save', 'marketplace-delete', 'stock-update', 'toggle-visibility', 'product-batch-save', 'upload-marketplace-images'];
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
                    'message' => 'El código debe tener 5 o 6 números',
                    'sku' => $sku
                ];
                break;
            }


            $id = (int)($_GET['id'] ?? 0);
            $allowSeedSku = in_array((string)($_GET['allow_seed'] ?? '0'), ['1', 'true', 'TRUE', 'yes', 'on'], true);
            $usage = sku_usage_admin_supply($pdo, $sku, 0, $id);
            $sameRecord = record_matches_normalized_sku_admin_supply($pdo, 'products', $id, $sku);
            $seedConflict = $usage['in_seed'] && !$sameRecord && !$allowSeedSku;
            // Debug helper: return diagnostic info when ?debug=1 is present
            if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
                $response = [
                    'success' => false,
                    'message' => 'diagnostic',
                    'diagnostic' => [
                        'usage' => $usage,
                        'sameRecord' => $sameRecord,
                        'seedConflict' => $seedConflict,
                        'id' => $id,
                        'sku' => $sku
                    ]
                ];
                break;
            }
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
            // If SKU collides only with seed and not present in DB, include a hint to allow marking as deleted
            if ($exists && !$usage['in_products'] && !$usage['in_marketplace'] && $usage['in_seed']) {
                $response['seed_only'] = true;
            }
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
                    'message' => 'El código debe tener 5 o 6 números',
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
            if (!is_valid_numeric_sku_for_creation_admin_supply($sku)) {
                $response = ['success' => false, 'message' => 'El código del producto debe tener exactamente 5 o 6 números (sin letras)'];
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
                $imageUrl = store_product_image_for_sku_admin_supply($_FILES['image'], $sku);
            } elseif (isset($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {
                $uploadedFiles = normalize_uploaded_files($_FILES['images']);
                foreach ($uploadedFiles as $uploadedFile) {
                    if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $imageUrl = store_product_image_for_sku_admin_supply($uploadedFile, $sku);
                        break;
                    }
                }
            }

            $finalGallery = [];
            if (!empty($galleryImages)) {
                $finalGallery = $galleryImages;
            } elseif ($imageUrl !== '' && strcasecmp($imageUrl, 'images/products/default-product.svg') !== 0) {
                $finalGallery = [$imageUrl];
            }
            if (!empty($finalGallery)) {
                $persistedGallery = persist_product_gallery_images_admin_supply($pdo, $sku, $finalGallery);
                if (!empty($persistedGallery)) {
                    $imageUrl = $persistedGallery[0] ?? $imageUrl;
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

                // Ensure gallery directory exists for newly created products in legacy fallback flow.
            try {
                $galleryDir = images_root_admin_supply() . '/products/gallery/' . $sku;
                ensure_directory_exists($galleryDir, 0777);
            } catch (Exception $ignored) {
                // Non-fatal: product was created and gallery can be created on first upload.
            }

                // Background: ensure marketplace stock/records sync for this SKU
                try {
                    if (function_exists('auto_sync_stock_after_change')) {
                        @auto_sync_stock_after_change($pdo, $sku);
                    }
                } catch (Throwable $e) {
                    error_log('auto_sync_stock_after_change (create-fallback) failed: ' . $e->getMessage());
                }

            log_action(
                $_SESSION['user_id'],
                'ADMIN_CREATE_PRODUCT',
                'Producto creado por admin: ' . $sku,
                null
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
            // Background: ensure marketplace stock/records sync for this SKU
            try {
                if (function_exists('auto_sync_stock_after_change')) {
                    @auto_sync_stock_after_change($pdo, $sku);
                }
            } catch (Throwable $e) {
                error_log('auto_sync_stock_after_change (create) failed: ' . $e->getMessage());
            }
            break;

        case 'product-save':
            error_log("SAVE REQ SKU: " . ($input['sku'] ?? 'MISSING'));
            error_log("SAVE REQ IMG_URL: " . ($input['image_url'] ?? 'MISSING'));
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
                // Get all gallery images from disk BEFORE save to include them in variants_json
                $galleryImages = list_product_gallery_files_admin_supply($sku);
            
                if (!is_valid_numeric_sku_for_creation_admin_supply($sku)) {
                $response = ['success' => false, 'message' => 'El código del producto debe tener exactamente 5 o 6 números (sin letras)'];
                break;
            }
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'Este código está marcado como eliminado y no se puede reutilizar'];
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
                $check = $pdo->prepare('SELECT id, variants_json FROM products WHERE id = ? LIMIT 1');
                $check->execute([$id]);
                $row = $check->fetch();
                if (!$row) {
                    $response = ['success' => false, 'message' => 'Producto no encontrado'];
                    break;
                }

                $dbVariants = json_decode((string)($row['variants_json'] ?? '[]'), true);
                if (!is_array($dbVariants)) $dbVariants = [];

                // SMART SYNC: Keep DB order for existing files, add new files from disk at the end
                $finalGallery = [];
                // 1. Keep files that are both on disk and already in DB (preserves custom order)
                foreach ($dbVariants as $img) {
                    if (in_array($img, $galleryImages)) {
                        $finalGallery[] = $img;
                    }
                }
                // 2. Add files that are on disk but NOT in DB yet (newly uploaded files)
                foreach ($galleryImages as $img) {
                    if (!in_array($img, $finalGallery)) {
                        $finalGallery[] = $img;
                    }
                }

                // Ensure the imageUrl sent from frontend is the FIRST item if it's in the gallery
                if (!empty($imageUrl) && in_array($imageUrl, $finalGallery)) {
                    $finalGallery = array_values(array_unique(array_merge([$imageUrl], $finalGallery)));
                }

                if (empty($finalGallery) && !empty($imageUrl) && strcasecmp($imageUrl, 'images/products/default-product.svg') !== 0) {
                     $finalGallery = [$imageUrl];
                }

                $variantsJson = json_encode($finalGallery, JSON_UNESCAPED_UNICODE);

                    // Prefer the image_url from the request if it's valid, otherwise fallback to gallery first item
                    $finalImageUrl = $imageUrl;
                    if (!empty($finalGallery) && !in_array($imageUrl, $finalGallery)) {
                        // If current imageUrl is not in gallery, but gallery is not empty, use gallery's first item
                        // Note: ideally we should use the order from DB, but this prevents overwriting with a random file.
                        if (strpos($imageUrl, 'images/products/') !== 0) {
                             $finalImageUrl = $finalGallery[0];
                        }
                    }
                    if (empty($finalImageUrl) || $finalImageUrl === 'images/products/default-product.svg') {
                        if (!empty($finalGallery)) $finalImageUrl = $finalGallery[0];
                        else $finalImageUrl = 'images/products/default-product.svg';
                    }
                
                update_product_compatible($pdo, $id, [
                    'sku' => $sku,
                    'name' => $name,
                    'category' => $category,
                    'description' => $description,
                    'barcode' => $barcode,
                        'price' => $price,
                        'variants_json' => $variantsJson,
                    'stock_quantity' => max(0, $stockQty),
                    'reorder_level' => max(0, $reorder),
                    'image_url' => $finalImageUrl,
                    'is_active' => $isVisible
                ]);

                if (!empty($finalGallery)) {
                    persist_product_gallery_images_admin_supply($pdo, $sku, $finalGallery);
                } else {
                    foreach (['products', 'marketplace_ce_products'] as $table) {
                        if (!db_table_exists($table)) {
                            continue;
                        }

                        try {
                            $stmt = $pdo->prepare("SELECT id, sku FROM {$table} WHERE sku = ? OR sku LIKE ?");
                            $stmt->execute([$sku, "%{$sku}%"]);
                            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            $matched_ids = [];
                            foreach ($candidates as $cnd) {
                                if (normalize_sku_admin_supply($cnd['sku']) === $sku) {
                                    $matched_ids[] = $cnd['id'];
                                }
                            }
                            if (!empty($matched_ids)) {
                                $placeholders = implode(',', array_fill(0, count($matched_ids), '?'));
                                $stmt = $pdo->prepare("UPDATE {$table} SET image_url = ?, variants_json = ? WHERE id IN ($placeholders)");
                                $stmt->execute(array_merge(['images/products/default-product.svg', '[]'], $matched_ids));
                            }
                        } catch (Exception $ignored) {
                        }
                    }

                    $galleryDir = images_root_admin_supply() . '/products/gallery/' . $sku;
                    if (is_dir($galleryDir)) {
                        remove_directory_recursive_admin_supply($galleryDir);
                    }
                }

                $response = [
                    'success' => true,
                    'message' => 'Producto actualizado correctamente',
                    'product' => [
                        'id' => $id,
                        'sku' => $sku,
                        'name' => $name
                    ]
                ];
                // Background: sync updated stock to marketplace for this SKU
                try {
                    if (function_exists('auto_sync_stock_after_change')) {
                        @auto_sync_stock_after_change($pdo, $sku);
                    }
                } catch (Throwable $e) {
                    error_log('auto_sync_stock_after_change (update) failed: ' . $e->getMessage());
                }
                break;
            }

                // Use gallery images from disk, or fallback to the selected image_url.
                $finalGallery = !empty($galleryImages)
                    ? $galleryImages
                    : (!empty($imageUrl) && strcasecmp($imageUrl, 'images/products/default-product.svg') !== 0 ? [$imageUrl] : []);
                $variantsJson = json_encode($finalGallery, JSON_UNESCAPED_UNICODE);
            
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
                    'is_active' => $isVisible,
                    'variants_json' => $variantsJson
                ]);

                // Ensure product gallery directory exists even if no images yet
                try {
                    $galleryDir = images_root_admin_supply() . '/products/gallery/' . $sku;
                    ensure_directory_exists($galleryDir, 0777);
                } catch (Exception $ignored) {
                    // Non-fatal: product already created, UI will report image issues if any
                }

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

            try {
                    // Fetch product to get SKU and image info before deletion
                    $stmt = $pdo->prepare("SELECT id, sku, image_url, variants_json FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                    if (!$product) {
                        $response = ['success' => false, 'message' => 'Producto no encontrado'];
                        break;
                    }

                    $sku = normalize_sku_admin_supply($product['sku'] ?? '');
                
                    // Step 1: Delete image files from disk
                    $imagesToDelete = [];
                    if (!empty($product['image_url']) && strpos($product['image_url'], 'default-product.svg') === false) {
                        $imagesToDelete[] = $product['image_url'];
                    }
                    if (!empty($product['variants_json'])) {
                        $variants = json_decode($product['variants_json'], true) ?: [];
                        foreach ($variants as $img) {
                            $img = trim((string)$img);
                            if ($img !== '' && strpos($img, 'default-product.svg') === false) {
                                $imagesToDelete[] = $img;
                            }
                        }
                    }
                
                    // Delete image files from disk - iterate and ensure each is deleted
                    $deletedImages = 0;
                    foreach (array_unique($imagesToDelete) as $imgPath) {
                        delete_product_gallery_file_admin_supply($sku, $imgPath);
                        $deletedImages++;
                    }
                
                    // Step 2: If SKU has marketplace entries, clean them up too BEFORE deleting product
                    $mpDeleted = 0;
                    if (!empty($sku) && is_valid_numeric_sku_admin_supply($sku)) {
                        try {
                            $stmt = $pdo->prepare("SELECT id, sku, image_url, variants_json FROM marketplace_ce_products WHERE sku = ? OR sku LIKE ?");
                            $stmt->execute([$sku, "%{$sku}%"]);
                            $mpProducts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                            $matched_mp_ids = [];
                            foreach ($mpProducts as $mp) {
                                if (normalize_sku_admin_supply($mp['sku']) === $sku) {
                                    $matched_mp_ids[] = $mp['id'];
                                    // Delete marketplace images from disk
                                    if (!empty($mp['image_url']) && strpos($mp['image_url'], 'default-product.svg') === false) {
                                        delete_product_gallery_file_admin_supply($sku, $mp['image_url']);
                                    }
                                    if (!empty($mp['variants_json'])) {
                                        $vars = json_decode($mp['variants_json'], true) ?: [];
                                        foreach ($vars as $v) {
                                            $v = trim((string)$v);
                                            if ($v !== '' && strpos($v, 'default-product.svg') === false) {
                                                delete_product_gallery_file_admin_supply($sku, $v);
                                            }
                                        }
                                    }
                                }
                            }
                            // Delete marketplace entries from database
                            if (!empty($matched_mp_ids)) {
                                $placeholders = implode(',', array_fill(0, count($matched_mp_ids), '?'));
                                $stmt = $pdo->prepare("DELETE FROM marketplace_ce_products WHERE id IN ($placeholders)");
                                $stmt->execute($matched_mp_ids);
                                $mpDeleted = $stmt->rowCount();
                            }
                        } catch (Exception $e) {
                            // Log but continue - we still want to delete the main product
                        }
                    }
                
                    // Step 3: Force-delete dependent rows so product delete is not blocked by FK history
                    $dependentRowsDeleted = force_delete_product_dependencies_admin_supply($pdo, $id);

                    // Step 4: Delete product from database
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    $productDeleted = $stmt->rowCount();
                
                    if ($productDeleted === 0) {
                        $response = ['success' => false, 'message' => 'No se pudo eliminar el producto de la BD'];
                        break;
                    }
                
                    // Step 5: Clean gallery directories if empty
                    $deletedDirs = 0;
                    if (!empty($sku) && is_valid_numeric_sku_admin_supply($sku)) {
                        foreach (image_storage_roots_admin_supply() as $imagesRoot) {
                            $galleryDirs = [
                                $imagesRoot . '/products/gallery/' . $sku,
                                $imagesRoot . '/products/by_code/' . $sku,
                            ];
                            foreach ($galleryDirs as $galleryDir) {
                                if (is_dir($galleryDir) && remove_directory_recursive_admin_supply($galleryDir)) {
                                    $deletedDirs++;
                                }
                            }
                        }
                    }
                
                    $response = [
                        'success' => true, 
                        'message' => "Producto eliminado completamente (SKU: $sku, dependencias: $dependentRowsDeleted, imágenes: $deletedImages, marketplace CE: $mpDeleted, directorios: $deletedDirs)",
                        'sku' => $sku,
                        'dependencies_deleted' => $dependentRowsDeleted,
                        'images_deleted' => $deletedImages,
                        'marketplace_deleted' => $mpDeleted,
                        'directories_deleted' => $deletedDirs
                    ];
                    // Background: cleanup orphaned marketplace records and ensure global sync
                    try {
                        if (function_exists('cleanup_orphaned_records')) {
                            @cleanup_orphaned_records($pdo);
                        }
                        if (function_exists('auto_sync_stock_after_change')) {
                            @auto_sync_stock_after_change($pdo, null);
                        }
                    } catch (Throwable $e) {
                        error_log('post-delete sync/cleanup failed: ' . $e->getMessage());
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() === '23503') {
                        $response = ['success' => false, 'message' => 'No se puede eliminar porque este producto tiene pedidos o historial asociado.'];
                    } else {
                        $response = ['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()];
                    }
                }
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

        case 'product-image-upload':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $uploaded = [];
            $fileInput = $_FILES['images'] ?? $_FILES['images[]'] ?? $_FILES['image'] ?? null;
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

            // Optional debug output: return raw DB fields for diagnosis when ?debug=1
            if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
                try {
                    $debugRows = [];
                    if (db_table_exists('products')) {
                        $stmt = $pdo->prepare("SELECT variants_json, image_url FROM products WHERE sku = ?");
                        $stmt->execute([$sku]);
                        $r = $stmt->fetch();
                        if ($r) $debugRows['products'] = $r;
                    }
                    if (db_table_exists('marketplace_ce_products')) {
                        $stmt = $pdo->prepare("SELECT variants_json, image_url FROM marketplace_ce_products WHERE sku = ?");
                        $stmt->execute([$sku]);
                        $r = $stmt->fetch();
                        if ($r) $debugRows['marketplace_ce_products'] = $r;
                    }
                    if (!empty($debugRows)) {
                        $response['debug'] = $debugRows;
                    }
                } catch (Exception $ignored) {
                }
            }

            // Add caching headers for faster gallery list retrieval
            $etag = '"gallery-' . hash('xxh64', json_encode($images)) . '"';
            header('ETag: ' . $etag);
            header('Cache-Control: private, max-age=300'); // 5-minute cache
            header('Vary: Accept');
            
            // Check If-None-Match header to return 304 Not Modified if unchanged
            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
                http_response_code(304);
                exit;
            }
            break;

        case 'mark-sku-deleted':
            // Función deprecada: el seeder ya no existe, responder OK por compatibilidad
            $response = ['success' => true, 'message' => 'Sin efecto (seeder eliminado)'];
            break;

        case 'product-gallery-upload':
        case 'marketplace-image-upload':
        case 'upload-marketplace-images':
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
            $uploadErrors = [];
            foreach ($files as $file) {
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $uploadErrors[] = "Error de carga: " . ($file['error'] ?? 'desconocido');
                    continue;
                }
                try {
                    $uploaded[] = store_product_image_for_sku_admin_supply($file, $sku);
                } catch (Exception $e) {
                    $uploadErrors[] = "Error al procesar imagen: " . $e->getMessage();
                }
            }

            if (empty($uploaded)) {
                $message = 'No se pudieron subir las imágenes';
                if (!empty($uploadErrors)) {
                    $message .= ' - ' . implode('; ', array_slice($uploadErrors, 0, 2));
                }
                $response = ['success' => false, 'message' => $message];
                break;
            }

            $images = list_product_gallery_images_admin_supply($sku);
            // Agregar solo las nuevas (evitar duplicados)
            foreach ($uploaded as $newImg) {
                if (!in_array($newImg, $images, true)) {
                    $images[] = $newImg;
                }
            }

            $images = normalize_and_persist_gallery_images_admin_supply($pdo, $sku, $images);
            $cover = $images[0] ?? null;

            $response = [
                'success' => true,
                'message' => 'Galería actualizada correctamente',
                'sku' => $sku,
                'uploaded' => $uploaded,
                'images' => $images,
                'cover' => $cover
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

            $images = list_product_gallery_images_admin_supply($sku);
            $imageIndex = array_search($image, $images);
            if ($imageIndex !== false) {
                $cover = $images[$imageIndex];
                // Move cover to index 0
                unset($images[$imageIndex]);
                array_unshift($images, $cover);
                $images = normalize_and_persist_gallery_images_admin_supply($pdo, $sku, $images);
                $cover = $images[0] ?? $cover;
            } else {
                $cover = $images[0] ?? null;
            }

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
                $response = ['success' => false, 'message' => 'SKU o imagen inválida'];
                break;
            }

            $images = list_product_gallery_images_admin_supply($sku);
            $imageIndex = array_search($image, $images);

            $debugEnabled = !empty($input['debug']) || (!empty($_POST['debug']) && $_POST['debug']);
            $deleteDebug = [];

            if ($imageIndex !== false) {
                unset($images[$imageIndex]);
                $images = array_values($images);
                if ($debugEnabled) {
                    // perform debug-aware deletion attempts
                    $raw = trim($image);
                    $parsed = parse_url($raw);
                    $relative = '';
                    if ($parsed !== false && isset($parsed['path'])) {
                        $relative = ltrim($parsed['path'], '/');
                    } else {
                        $relative = ltrim(preg_replace('/\?.*$/', '', $raw), '/');
                    }
                    $filename = basename($relative);
                    $candidates = [
                        images_root_admin_supply() . '/products/gallery/' . $sku . '/' . $filename,
                        images_root_admin_supply() . '/products/by_code/' . $sku . '/' . $filename,
                        images_root_admin_supply() . '/products/gallery/' . $sku . '/' . $filename,
                        images_root_admin_supply() . '/products/by_code/' . $sku . '/' . $filename,
                    ];
                    $wildGallery = glob(images_root_admin_supply() . '/products/gallery/' . $sku . '/*' . $filename);
                    if (is_array($wildGallery)) {
                        foreach ($wildGallery as $wf) $candidates[] = $wf;
                    }
                    foreach (array_unique($candidates) as $target) {
                        $existsBefore = is_file($target);
                        $deleted = false;
                        if ($existsBefore) {
                            $deleted = @unlink($target);
                        }
                        $existsAfter = is_file($target);
                        $deleteDebug[] = ['target' => $target, 'before' => $existsBefore, 'deleted' => (bool)$deleted, 'after' => $existsAfter];
                    }
                } else {
                    delete_product_gallery_file_admin_supply($sku, $image);
                }
                purge_gallery_image_references_admin_supply($pdo, $sku, $image);

                if (!empty($images)) {
                    $images = normalize_and_persist_gallery_images_admin_supply($pdo, $sku, $images);
                    $json = json_encode($images, JSON_UNESCAPED_UNICODE);
                    $cover = $images[0] ?? 'images/products/default-product.svg';
                } else {
                    $json = '[]';
                    $cover = 'images/products/default-product.svg';
                    foreach (['products', 'marketplace_ce_products'] as $table) {
                        if (!db_table_exists($table)) {
                            continue;
                        }

                        try {
                            $stmt = $pdo->prepare("SELECT id, sku FROM {$table} WHERE sku = ? OR sku LIKE ?");
                            $stmt->execute([$sku, "%{$sku}%"]);
                            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            $matched_ids = [];
                            foreach ($candidates as $cnd) {
                                if (normalize_sku_admin_supply($cnd['sku']) === $sku) {
                                    $matched_ids[] = $cnd['id'];
                                }
                            }
                            if (!empty($matched_ids)) {
                                $placeholders = implode(',', array_fill(0, count($matched_ids), '?'));
                                $stmt = $pdo->prepare("UPDATE {$table} SET image_url = ?, variants_json = ? WHERE id IN ($placeholders)");
                                $stmt->execute(array_merge([$cover, $json], $matched_ids));
                            }
                        } catch (Exception $ignored) {
                        }
                    }
                }

            } else {
                // image not found in canonical list: still attempt to delete file and purge references
                if ($debugEnabled) {
                    $raw = trim($image);
                    $parsed = parse_url($raw);
                    $relative = '';
                    if ($parsed !== false && isset($parsed['path'])) {
                        $relative = ltrim($parsed['path'], '/');
                    } else {
                        $relative = ltrim(preg_replace('/\?.*$/', '', $raw), '/');
                    }
                    $filename = basename($relative);
                    $candidates = [
                        images_root_admin_supply() . '/products/gallery/' . $sku . '/' . $filename,
                        images_root_admin_supply() . '/products/by_code/' . $sku . '/' . $filename,
                    ];
                    $wildGallery = glob(images_root_admin_supply() . '/products/gallery/' . $sku . '/*' . $filename);
                    if (is_array($wildGallery)) {
                        foreach ($wildGallery as $wf) $candidates[] = $wf;
                    }
                    foreach (array_unique($candidates) as $target) {
                        $existsBefore = is_file($target);
                        $deleted = false;
                        if ($existsBefore) {
                            $deleted = @unlink($target);
                        }
                        $existsAfter = is_file($target);
                        $deleteDebug[] = ['target' => $target, 'before' => $existsBefore, 'deleted' => (bool)$deleted, 'after' => $existsAfter];
                    }
                } else {
                    delete_product_gallery_file_admin_supply($sku, $image);
                }
                purge_gallery_image_references_admin_supply($pdo, $sku, $image);
            }

            $images_final = list_product_gallery_images_admin_supply($sku);
            
            $response = [
                'success' => true,
                'message' => 'Imagen eliminada correctamente',
                'sku' => $sku,
                'images' => $images_final,
                'cover' => $images_final[0] ?? null
            ];
            if (!empty($deleteDebug)) {
                $response['delete_debug'] = $deleteDebug;
            }
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

            error_log("REORDER REQ SKU: " . $sku);
            error_log("REORDER REQ IMAGES: " . json_encode($images));
            $ordered = reorder_product_gallery_images_admin_supply($pdo, $sku, $images);
            error_log("REORDER FINAL: " . json_encode($ordered));
            $response = [
                'success' => true,
                'message' => 'Orden de imágenes actualizado',
                'sku' => $sku,
                'images' => $ordered,
                'cover' => $ordered[0] ?? null
            ];
            break;

        case 'product-gallery-bootstrap':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $sku = normalize_sku_admin_supply($input['sku'] ?? '');
            $image = trim((string)($input['image'] ?? ''));
            if (!is_valid_numeric_sku_admin_supply($sku) || $image === '' || strpos($image, 'default-product.svg') !== false) {
                $response = ['success' => false, 'message' => 'SKU o imagen inválidos'];
                break;
            }

            $images = persist_product_gallery_images_admin_supply($pdo, $sku, [$image]);
            if (empty($images)) {
                $response = ['success' => false, 'message' => 'No fue posible inicializar la galería'];
                break;
            }

            $response = [
                'success' => true,
                'message' => 'Galería inicializada correctamente',
                'sku' => $sku,
                'images' => $images,
                'cover' => $images[0] ?? null
            ];
            break;

        case 'sync-images-to-db-all':
            // Admin-only endpoint: this file already requires admin via require_admin()
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            // Require CSRF for safety
            require_csrf_token();

            $root = images_root_admin_supply() . '/products/by_code';
            if (!is_dir($root)) {
                $response = ['success' => false, 'message' => 'No hay directorio de imágenes legacy'];
                break;
            }

            $dirs = glob($root . '/*', GLOB_ONLYDIR);
            $migrated = 0;
            $errors = [];

            foreach ($dirs as $dir) {
                $sku = basename($dir);
                $files = list_product_gallery_files_admin_supply($sku);
                if (empty($files)) continue;

                try {
                    $images = persist_product_gallery_images_admin_supply($pdo, $sku, $files);
                    if (!empty($images)) $migrated++;
                } catch (Exception $e) {
                    $errors[] = ['sku' => $sku, 'error' => $e->getMessage()];
                }
            }

            $response = ['success' => true, 'message' => 'Migración completa', 'migrated_dirs' => $migrated, 'errors' => $errors];
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
            $context = $_GET['context'] ?? '';
            $where = [];
            if ($onlyActive) $where[] = "is_active = true";
            if ($context === 'stock') {
                $where[] = "(context = 'stock' OR context = 'both')";
            } elseif ($context === 'marketplace') {
                $where[] = "(context = 'marketplace' OR context = 'both')";
            }
            $whereStr = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

            $stmt = $pdo->query("SELECT id, {$nameSelect}, {$orderSelect}, {$activeSelect}, " . (db_column_exists('product_categories', 'context') ? "context" : "'stock' AS context") . " FROM product_categories" . $whereStr . " ORDER BY " . (db_column_exists('product_categories', 'sort_order') ? 'sort_order ASC, ' : '') . "name ASC");
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
            $context = sanitize($input['context'] ?? ($_POST['context'] ?? 'stock'));

            if ($name === '') {
                $response = ['success' => false, 'message' => 'El nombre de la categoría es obligatorio'];
                break;
            }

            $nameNormalized = normalize_category_admin_supply($name);
            $allCategoriesStmt = $pdo->query("SELECT id, name FROM product_categories");
            $allCategories = $allCategoriesStmt ? $allCategoriesStmt->fetchAll() : [];
            
            foreach ($allCategories as $cat) {
                if ($cat['id'] == $id) {
                    continue;
                }
                $catNormalized = normalize_category_admin_supply($cat['name']);
                if ($catNormalized === $nameNormalized) {
                    $response = ['success' => false, 'message' => 'Ya existe una categoría con ese nombre (sin diferencia de acentos)'];
                    break 2;
                }
            }

            if ($id > 0) {
                $updated = false;

                // Full update path (newest schema)
                try {
                    $stmt = $pdo->prepare("UPDATE product_categories SET name = ?, sort_order = ?, is_active = ?, context = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$name, $sortOrder, $isActive, $context, $id]);
                    $updated = true;
                } catch (Exception $ignored) {
                    try {
                        $stmt = $pdo->prepare("UPDATE product_categories SET name = ?, sort_order = ?, is_active = ?, context = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$name, $sortOrder, $isActive ? 1 : 0, $context, $id]);
                        $updated = true;
                    } catch (Exception $ignored2) {
                    }
                }

                // Legacy fallback: without updated_at
                if (!$updated) {
                    try {
                        $stmt = $pdo->prepare("UPDATE product_categories SET name = ?, sort_order = ?, is_active = ?, context = ? WHERE id = ?");
                        $stmt->execute([$name, $sortOrder, $isActive, $context, $id]);
                        $updated = true;
                    } catch (Exception $ignored) {
                        try {
                            $stmt = $pdo->prepare("UPDATE product_categories SET name = ?, sort_order = ?, is_active = ?, context = ? WHERE id = ?");
                            $stmt->execute([$name, $sortOrder, $isActive ? 1 : 0, $context, $id]);
                            $updated = true;
                        } catch (Exception $ignored2) {
                        }
                    }
                }

                // Minimal fallback: name + sort_order only
                if (!$updated) {
                    try {
                        $stmt = $pdo->prepare("UPDATE product_categories SET name = ?, sort_order = ?, context = ? WHERE id = ?");
                        $stmt->execute([$name, $sortOrder, $context, $id]);
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
                    $createdId = insert_category_and_get_id_admin_supply($pdo, $name, $sortOrder, $isActive, $context);
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

            try {
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
            } catch (Exception $e) {
                // If it fails, maybe it's in use or a constraint fails.
                // Fallback: Just mark it as inactive or return a friendly error.
                if ($id > 0) {
                    try {
                        $pdo->prepare("UPDATE product_categories SET is_active = false WHERE id = ?")->execute([$id]);
                        $response = ['success' => true, 'message' => 'Categoría oculta (no se puede eliminar porque está en uso)'];
                    } catch (Exception $e2) {
                        $response = ['success' => false, 'message' => 'Error al eliminar la categoría: ' . $e->getMessage()];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Error al eliminar la categoría: ' . $e->getMessage()];
                }
            }
            break;

        case 'product-batch-save':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            $products = $input['products'] ?? [];
            if (!is_array($products) || count($products) === 0) {
                $response = ['success' => false, 'message' => 'No hay productos para procesar'];
                break;
            }
            $processed = 0;
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO products (sku, name, description, category, unit_price, stock_quantity, reorder_level, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                    ON CONFLICT (sku) DO UPDATE SET 
                        name = EXCLUDED.name,
                        description = EXCLUDED.description,
                        category = EXCLUDED.category,
                        unit_price = EXCLUDED.unit_price,
                        stock_quantity = EXCLUDED.stock_quantity,
                        reorder_level = EXCLUDED.reorder_level
                ");
                // Note: SQLite uses ON CONFLICT (sku) if it's unique.
                // But products.sku isn't guaranteed to be a unique index in this schema (id is).
                // Let's do an UPSERT by first checking, or doing SELECT/UPDATE.
                foreach ($products as $p) {
                    $sku = normalize_sku_admin_supply($p['sku'] ?? '');
                    if ($sku === '') continue;
                    $name = trim($p['name'] ?? 'Producto CSV');
                    $category = trim($p['category'] ?? 'General');
                    $desc = trim($p['description'] ?? '');
                    $price = (float)($p['unit_price'] ?? 0);
                    $stock = (int)($p['stock_quantity'] ?? 0);
                    $reorder = (int)($p['reorder_level'] ?? 10);
                    
                    $check = $pdo->prepare("SELECT id, sku FROM products WHERE sku = ? OR sku LIKE ?");
                    $check->execute([$sku, "%{$sku}%"]);
                    $candidates = $check->fetchAll(PDO::FETCH_ASSOC);
                    
                    $matched_id = null;
                    foreach ($candidates as $cnd) {
                        if (normalize_sku_admin_supply($cnd['sku']) === $sku) {
                            $matched_id = $cnd['id'];
                            break;
                        }
                    }
                    
                    if ($matched_id) {
                        $upd = $pdo->prepare("UPDATE products SET name=?, category=?, description=?, unit_price=?, stock_quantity=?, reorder_level=? WHERE id = ?");
                        $upd->execute([$name, $category, $desc, $price, $stock, $reorder, $matched_id]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO products (sku, name, category, description, unit_price, stock_quantity, reorder_level, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, true)");
                        $ins->execute([$sku, $name, $category, $desc, $price, $stock, $reorder]);
                    }
                    $processed++;
                }
                $pdo->commit();
                $response = ['success' => true, 'processed' => $processed];
                // Background: sync all products to marketplace after batch import
                try {
                    if (function_exists('auto_sync_stock_after_change')) {
                        @auto_sync_stock_after_change($pdo, null);
                    }
                } catch (Throwable $e) {
                    error_log('auto_sync_stock_after_change (batch) failed: ' . $e->getMessage());
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
            }
            break;

        case 'marketplace-batch-save':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            $products = $input['products'] ?? [];
            if (!is_array($products) || count($products) === 0) {
                $response = ['success' => false, 'message' => 'No hay artículos CE para procesar'];
                break;
            }
            $processed = 0;
            $pdo->beginTransaction();
            try {
                foreach ($products as $p) {
                    $sku = normalize_sku_admin_supply($p['sku'] ?? '');
                    if ($sku === '') continue;
                    $name = trim($p['name'] ?? 'Artículo CE CSV');
                    $category = trim($p['category'] ?? 'Marketplace CE');
                    $desc = trim($p['description'] ?? '');
                    $condition = trim($p['condition_label'] ?? 'Seminuevo');
                    $price = (float)($p['unit_price'] ?? 0);
                    $stock = (int)($p['stock_quantity'] ?? 1);
                    
                    $check = $pdo->prepare("SELECT id, sku FROM marketplace_ce_products WHERE sku = ? OR sku LIKE ?");
                    $check->execute([$sku, "%{$sku}%"]);
                    $candidates = $check->fetchAll(PDO::FETCH_ASSOC);
                    
                    $matched_id = null;
                    foreach ($candidates as $cnd) {
                        if (normalize_sku_admin_supply($cnd['sku']) === $sku) {
                            $matched_id = $cnd['id'];
                            break;
                        }
                    }
                    
                    if ($matched_id) {
                        $upd = $pdo->prepare("UPDATE marketplace_ce_products SET name=?, category=?, description=?, condition_label=?, unit_price=?, stock_quantity=? WHERE id = ?");
                        $upd->execute([$name, $category, $desc, $condition, $price, $stock, $matched_id]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO marketplace_ce_products (sku, name, category, description, condition_label, unit_price, stock_quantity, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, true)");
                        $ins->execute([$sku, $name, $category, $desc, $condition, $price, $stock]);
                    }
                    $processed++;
                }
                $pdo->commit();
                $response = ['success' => true, 'processed' => $processed];
            } catch (Exception $e) {
                $pdo->rollBack();
                $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
            }
            break;

        case 'upload-marketplace-images':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            $sku = normalize_sku_admin_supply($_POST['sku'] ?? '');
            if ($sku === '' || empty($_FILES['images']['name'][0])) {
                $response = ['success' => false, 'message' => 'SKU y al menos una imagen son requeridos'];
                break;
            }

            $uploadedFiles = [];
            foreach ($_FILES['images']['name'] as $key => $name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['images']['tmp_name'][$key];
                    $mimeType = mime_content_type($tmpName);
                    if (!$mimeType) $mimeType = 'image/jpeg';
                    $uploadedFiles[] = convert_image_to_base64_admin_supply($tmpName, $mimeType);
                }
            }

            if (count($uploadedFiles) > 0) {
                try {
                    $pdo->exec("ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS variants_json TEXT");
                } catch(Exception $e) {}

                $stmt = $pdo->prepare("SELECT id, sku, variants_json, image_url FROM marketplace_ce_products WHERE sku = ? OR sku LIKE ?");
                $stmt->execute([$sku, "%{$sku}%"]);
                $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $row = null;
                foreach ($candidates as $cnd) {
                    if (normalize_sku_admin_supply($cnd['sku']) === $sku) {
                        $row = $cnd;
                        break;
                    }
                }
                
                if ($row) {
                    $existing = [];
                    if (!empty($row['variants_json'])) {
                        $existing = json_decode($row['variants_json'], true) ?: [];
                    } elseif (!empty($row['image_url']) && strpos($row['image_url'], 'default-product.svg') === false) {
                        $existing[] = $row['image_url'];
                    }
                    $allImages = array_merge($existing, $uploadedFiles);
                    $json = json_encode($allImages);
                    $mainImage = $allImages[0];
                    $update = $pdo->prepare("UPDATE marketplace_ce_products SET image_url = ?, variants_json = ? WHERE id = ?");
                    $update->execute([$mainImage, $json, $row['id']]);
                }
                $response = ['success' => true, 'message' => count($uploadedFiles) . ' imágenes subidas', 'files' => $uploadedFiles];
            } else {
                $response = ['success' => false, 'message' => 'No se pudo subir ninguna imagen'];
            }
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

            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(10, min(200, (int)($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;

            $onlyActive = isset($_GET['active']) && $_GET['active'] === '1';
            $whereActive = '';
            if ($onlyActive && $mkActiveCol !== null) {
                $whereActive = ' WHERE (CASE WHEN ' . $mkActiveCol . " IS NULL THEN 1 WHEN LOWER(CAST(" . $mkActiveCol . " AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
            }

            $countStmt = $pdo->query('SELECT COUNT(*) FROM marketplace_ce_products' . $whereActive);
            $total = $countStmt ? (int)$countStmt->fetchColumn() : 0;

            $stmt = $pdo->query(
                'SELECT id, ' . $selectSku . ', ' . $selectName . ', ' . $selectCategory . ', ' . $selectDescription . ', ' . $selectCondition . ', ' . $selectPrice . ', ' . $selectStock . ', ' . $selectImage . ', ' . $selectActive . ', ' . $selectCreatedAt . ', ' . $selectUpdatedAt .
                ' FROM marketplace_ce_products' . $whereActive . ' ORDER BY ' . $orderExpr . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset
            );
            $items = $stmt ? $stmt->fetchAll() : [];

            $response = [
                'success' => true,
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_items' => $total,
                    'total_pages' => max(1, (int)ceil($total / $perPage))
                ]
            ];
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

            // Get all gallery images from disk BEFORE save to include them in variants_json
            $galleryImages = list_product_gallery_files_admin_supply($sku);

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

            if ($sku === '' || $name === '') {
                $response = ['success' => false, 'message' => 'SKU y nombre son obligatorios'];
                break;
            }
            if (!is_valid_numeric_sku_admin_supply($sku)) {
                $response = ['success' => false, 'message' => 'El código SKU CE debe tener 5 o 6 números'];
                break;
            }

            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'Este código está marcado como eliminado y no se puede reutilizar'];
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

            if ($description === '') {
                $description = 'Sin descripción';
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
                $imageUrl = store_product_image_for_sku_admin_supply($_FILES['image'], $sku);
            }

            if ($id > 0) {
                $sets = [$mkSkuCol . ' = ?', $mkNameCol . ' = ?', $mkDescriptionCol . ' = ?'];
                $values = [$sku, $name, $description];

                if ($mkConditionCol !== null) { $sets[] = $mkConditionCol . ' = ?'; $values[] = $conditionLabel; }
                if ($mkPriceCol !== null) { $sets[] = $mkPriceCol . ' = ?'; $values[] = $unitPrice; }
                if ($mkStockCol !== null) { $sets[] = $mkStockCol . ' = ?'; $values[] = max(0, $stockQuantity); }
                
                // Preserve existing gallery images if any, or use newly uploaded ones
                $existingGallery = list_product_gallery_images_admin_supply($sku);
                $finalGallery = !empty($existingGallery) ? $existingGallery : $galleryImages;
                if (!empty($finalGallery) && db_column_exists('marketplace_ce_products', 'variants_json')) {
                    $sets[] = 'variants_json = ?';
                    $values[] = json_encode($finalGallery, JSON_UNESCAPED_UNICODE);
                }
                
                if ($mkUpdatedByCol !== null) { $sets[] = $mkUpdatedByCol . ' = ?'; $values[] = (int)($_SESSION['user_id'] ?? 0); }
                if ($mkUpdatedAtCol !== null) { $sets[] = $mkUpdatedAtCol . ' = CURRENT_TIMESTAMP'; }
                if ($mkCategoryCol !== null) { $sets[] = $mkCategoryCol . ' = ?'; $values[] = $category; }
                if ($mkImageCol !== null) { $sets[] = $mkImageCol . ' = ?'; $values[] = $imageUrl; }

                $values[] = $id;
                $stmt = $pdo->prepare('UPDATE marketplace_ce_products SET ' . implode(', ', $sets) . ' WHERE id = ?');
                $stmt->execute($values);

                // Keep gallery route available for CE SKU even when no upload happened in this request.
                try {
                    $galleryDir = images_root_admin_supply() . '/products/gallery/' . $sku;
                    ensure_directory_exists($galleryDir, 0777);
                } catch (Exception $ignored) {
                    // Non-fatal: CE item is already updated.
                }
                
                // Update visibility separately to avoid type conflicts
                set_marketplace_visibility_compatible($pdo, $id, $isActive);
                
                $response = ['success' => true, 'message' => 'Artículo CE actualizado'];
            } else {
                $columns = [$mkSkuCol, $mkNameCol, $mkDescriptionCol];
                $placeholders = ['?', '?', '?'];
                $values = [$sku, $name, $description];

                if ($mkConditionCol !== null) { $columns[] = $mkConditionCol; $placeholders[] = '?'; $values[] = $conditionLabel; }
                if ($mkPriceCol !== null) { $columns[] = $mkPriceCol; $placeholders[] = '?'; $values[] = $unitPrice; }
                if ($mkStockCol !== null) { $columns[] = $mkStockCol; $placeholders[] = '?'; $values[] = max(0, $stockQuantity); }
                
                // Add gallery images to variants_json for new items too
                $finalGallery = !empty($galleryImages) ? $galleryImages : [$imageUrl];
                if (db_column_exists('marketplace_ce_products', 'variants_json')) {
                    $columns[] = 'variants_json';
                    $placeholders[] = '?';
                    $values[] = json_encode($finalGallery, JSON_UNESCAPED_UNICODE);
                }
                
                if ($mkImageCol !== null) { $columns[] = $mkImageCol; $placeholders[] = '?'; $values[] = $imageUrl; }
                if ($mkCreatedByCol !== null) { $columns[] = $mkCreatedByCol; $placeholders[] = '?'; $values[] = (int)($_SESSION['user_id'] ?? 0); }
                if ($mkUpdatedByCol !== null) { $columns[] = $mkUpdatedByCol; $placeholders[] = '?'; $values[] = (int)($_SESSION['user_id'] ?? 0); }
                if ($mkCategoryCol !== null) { $columns[] = $mkCategoryCol; $placeholders[] = '?'; $values[] = $category; }
                // Set visibility to true by default for new items
                if ($mkActiveCol !== null) { $columns[] = $mkActiveCol; $placeholders[] = '?'; $values[] = $isActive ? 1 : 0; }

                $stmt = $pdo->prepare('INSERT INTO marketplace_ce_products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')');
                $stmt->execute($values);

                // Ensure gallery route exists for newly created CE items.
                try {
                    $galleryDir = images_root_admin_supply() . '/products/gallery/' . $sku;
                    ensure_directory_exists($galleryDir, 0777);
                } catch (Exception $ignored) {
                    // Non-fatal: CE item is already created.
                }
                
                // If insertion succeeded and we have is_active column issue, ensure it's set
                try {
                    $lastId = $pdo->lastInsertId('marketplace_ce_products_id_seq');
                    if ($lastId && $mkActiveCol === null) {
                        set_marketplace_visibility_compatible($pdo, (int)$lastId, $isActive);
                    }
                } catch (Exception $ignored) {}
                
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

            try {
                // Fetch marketplace product to get SKU and image info before deletion
                $stmt = $pdo->prepare("SELECT sku, image_url, variants_json FROM marketplace_ce_products WHERE id = ?");
                $stmt->execute([$id]);
                $mpProduct = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$mpProduct) {
                    $response = ['success' => false, 'message' => 'Artículo no encontrado'];
                    break;
                }

                $sku = normalize_sku_admin_supply($mpProduct['sku'] ?? '');
                
                // Collect all image paths to delete from disk
                $imagesToDelete = [];
                if (!empty($mpProduct['image_url']) && strpos($mpProduct['image_url'], 'default-product.svg') === false) {
                    $imagesToDelete[] = $mpProduct['image_url'];
                }
                if (!empty($mpProduct['variants_json'])) {
                    $variants = json_decode($mpProduct['variants_json'], true) ?: [];
                    foreach ($variants as $img) {
                        $img = trim((string)$img);
                        if ($img !== '' && strpos($img, 'default-product.svg') === false) {
                            $imagesToDelete[] = $img;
                        }
                    }
                }
                
                // Delete image files from disk
                foreach (array_unique($imagesToDelete) as $imgPath) {
                    delete_product_gallery_file_admin_supply($sku, $imgPath);
                }
                
                // Delete marketplace product row
                $stmt = $pdo->prepare("DELETE FROM marketplace_ce_products WHERE id = ?");
                $stmt->execute([$id]);
                
                // Clean gallery directory - use robust deletion
                $deletedDir = 0;
                if (!empty($sku) && is_valid_numeric_sku_admin_supply($sku)) {
                    // Check if this SKU still exists in products table
                    $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
                    $stmt->execute([$sku]);
                    $still_in_products = $stmt->fetchColumn() !== false;
                    
                    // Only delete directory if SKU is NOT in products table anymore
                    if (!$still_in_products) {
                        foreach (image_storage_roots_admin_supply() as $imagesRoot) {
                            $galleryDir = $imagesRoot . '/products/gallery/' . $sku;
                            if (is_dir($galleryDir) && remove_directory_recursive_admin_supply($galleryDir)) {
                                $deletedDir++;
                            }
                        }
                    }
                }
                
                $response = ['success' => true, 'message' => 'Artículo CE eliminado definitivamente', 'sku' => $sku, 'directory_deleted' => $deletedDir];
            } catch (PDOException $e) {
                if ($e->getCode() === '23503') {
                    $response = ['success' => false, 'message' => 'No se puede eliminar porque este artículo tiene historial asociado.'];
                } else {
                    throw $e;
                }
            }
            break;

        case 'updates-list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $activeCol = homepage_updates_active_column_admin_supply();
            $sortCol = first_existing_column_admin_supply('homepage_updates', ['sort_order', 'display_order', 'order_index', 'position']);
            $imageCol = first_existing_column_admin_supply('homepage_updates', ['image_url', 'image', 'photo_url']);
            $createdAtCol = first_existing_column_admin_supply('homepage_updates', ['created_at']);
            $updatedAtCol = first_existing_column_admin_supply('homepage_updates', ['updated_at']);

            $selectImage = $imageCol !== null ? ('COALESCE(' . $imageCol . ", '') AS image_url") : "'' AS image_url";
            $selectSort = $sortCol !== null ? ('COALESCE(' . $sortCol . ', 0) AS sort_order') : '0 AS sort_order';
            $selectActive = $activeCol !== null
                ? ("(CASE WHEN " . $activeCol . " IS NULL THEN 1 WHEN LOWER(CAST(" . $activeCol . " AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) AS is_active")
                : '1 AS is_active';
            $selectCreatedAt = $createdAtCol !== null ? ($createdAtCol . ' AS created_at') : 'NULL AS created_at';
            $selectUpdatedAt = $updatedAtCol !== null ? ($updatedAtCol . ' AS updated_at') : 'NULL AS updated_at';
            $orderExpr = $sortCol !== null ? ($sortCol . ' ASC, id DESC') : 'id DESC';

            $onlyActive = isset($_GET['active']) && $_GET['active'] === '1';
            $whereActive = '';
            if ($onlyActive && $activeCol !== null) {
                $whereActive = ' WHERE (CASE WHEN ' . $activeCol . " IS NULL THEN 1 WHEN LOWER(CAST(" . $activeCol . " AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
            }
            $limit = $onlyActive ? 40 : 120;
            $stmt = $pdo->query('SELECT id, update_type, title, body, ' . $selectImage . ', ' . $selectSort . ', ' . $selectActive . ', ' . $selectCreatedAt . ', ' . $selectUpdatedAt . ' FROM homepage_updates' . $whereActive . ' ORDER BY ' . $orderExpr . ' LIMIT ' . (int)$limit);

            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        case 'updates-save':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($_POST['id'] ?? ($input['id'] ?? 0));
            $type = sanitize($_POST['update_type'] ?? ($input['update_type'] ?? 'noticia'));
            $title = trim((string)($_POST['title'] ?? ($input['title'] ?? ($_REQUEST['title'] ?? ''))));
            $body = trim((string)($_POST['body'] ?? ($input['body'] ?? ($_REQUEST['body'] ?? ''))));
            $sortOrder = (int)($_POST['sort_order'] ?? ($input['sort_order'] ?? 0));
            $isActive = isset($_POST['is_active'])
                ? normalize_bool_admin_supply($_POST['is_active'], true)
                : (isset($input['is_active']) ? normalize_bool_admin_supply($input['is_active'], true) : true);

            $activeCol = homepage_updates_active_column_admin_supply();
            $sortCol = first_existing_column_admin_supply('homepage_updates', ['sort_order', 'display_order', 'order_index', 'position']);
            $imageCol = first_existing_column_admin_supply('homepage_updates', ['image_url', 'image', 'photo_url']);
            $createdByCol = first_existing_column_admin_supply('homepage_updates', ['created_by']);
            $updatedByCol = first_existing_column_admin_supply('homepage_updates', ['updated_by']);
            $updatedAtCol = first_existing_column_admin_supply('homepage_updates', ['updated_at']);

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
            $imageWarning = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $imageUrl = store_product_image($_FILES['image']);
                } catch (Exception $e) {
                    // Non-fatal: record saved without image, warn admin
                    $imageWarning = 'Imagen no pudo guardarse: ' . $e->getMessage();
                    error_log('homepage_update image upload error: ' . $e->getMessage());
                }
            }

            if ($id > 0) {
                $sets = ['update_type = ?', 'title = ?', 'body = ?'];
                $values = [$type, $title, $body];

                if ($imageUrl !== null && $imageCol !== null) {
                    $sets[] = $imageCol . ' = ?';
                    $values[] = $imageUrl;
                }
                if ($sortCol !== null) {
                    $sets[] = $sortCol . ' = ?';
                    $values[] = $sortOrder;
                }
                if ($activeCol !== null) {
                    $sets[] = $activeCol . ' = ?';
                    $values[] = $isActive ? 1 : 0;
                }
                if ($updatedByCol !== null) {
                    $sets[] = $updatedByCol . ' = ?';
                    $values[] = (int)($_SESSION['user_id'] ?? 0);
                }
                if ($updatedAtCol !== null) {
                    $sets[] = $updatedAtCol . ' = CURRENT_TIMESTAMP';
                }

                $values[] = $id;
                $stmt = $pdo->prepare('UPDATE homepage_updates SET ' . implode(', ', $sets) . ' WHERE id = ?');
                $stmt->execute($values);
                $msg = $imageWarning ? ('Publicacion actualizada (sin imagen: ' . $imageWarning . ')') : 'Publicacion actualizada';
                $response = ['success' => true, 'message' => $msg];
            } else {
                $columns = ['update_type', 'title', 'body'];
                $placeholders = ['?', '?', '?'];
                $values = [$type, $title, $body];

                if ($imageCol !== null) {
                    $columns[] = $imageCol;
                    $placeholders[] = '?';
                    $values[] = $imageUrl;
                }
                if ($sortCol !== null) {
                    $columns[] = $sortCol;
                    $placeholders[] = '?';
                    $values[] = $sortOrder;
                }
                if ($activeCol !== null) {
                    $columns[] = $activeCol;
                    $placeholders[] = '?';
                    $values[] = $isActive ? 1 : 0;
                }
                if ($createdByCol !== null) {
                    $columns[] = $createdByCol;
                    $placeholders[] = '?';
                    $values[] = (int)($_SESSION['user_id'] ?? 0);
                }
                if ($updatedByCol !== null) {
                    $columns[] = $updatedByCol;
                    $placeholders[] = '?';
                    $values[] = (int)($_SESSION['user_id'] ?? 0);
                }

                $stmt = $pdo->prepare('INSERT INTO homepage_updates (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')');
                $stmt->execute($values);
                $msg = $imageWarning ? ('Publicacion creada (sin imagen: ' . $imageWarning . ')') : 'Publicacion creada';
                $response = ['success' => true, 'message' => $msg];
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
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = max(10, min(200, (int)($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $per_page;
            
            $items = list_stock_products_compatible($pdo, $per_page, $offset);
            $total = count_stock_products_compatible($pdo);
            
            $response = [
                'success' => true, 
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total_items' => $total,
                    'total_pages' => ceil($total / $per_page)
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
            $visit_datetime = normalize_datetime_value($input['visit_datetime'] ?? '');
            $notes = sanitize($input['notes'] ?? '');
            if ($supplier_name === '' || !$visit_datetime) {
                $response = ['success' => false, 'message' => 'Proveedor y fecha válidos son obligatorios'];
                break;
            }
            $stmt = $pdo->prepare("INSERT INTO supplier_calendar (supplier_name, visit_datetime, notes, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$supplier_name, $visit_datetime, $notes, $_SESSION['user_id'] ?? null]);
            $response = ['success' => true, 'message' => 'Visita registrada'];
            break;

        case 'calendar-delete':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'ID de visita inválido'];
                break;
            }
            $stmt = $pdo->prepare("DELETE FROM supplier_calendar WHERE id = ?");
            $stmt->execute([$id]);
            $response = ['success' => true, 'message' => 'Visita eliminada'];
            break;

        case 'auto-reorder-draft':
            require_admin();
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $skuColumn = sku_column_for_table_admin_supply('products') ?: 'sku';
            $nameColumn = name_column_for_table_admin_supply('products') ?: 'name';
            $priceColumn = db_column_exists('products', 'unit_price') ? 'unit_price' : (db_column_exists('products', 'sell_price') ? 'sell_price' : '0');
            
            $sql = "
                SELECT 
                    p.id AS product_id,
                    p.{$skuColumn} AS sku,
                    p.{$nameColumn} AS name,
                    COALESCE(p.stock_quantity, 0) AS stock_quantity,
                    COALESCE(p.reorder_level, 10) AS reorder_level,
                    COALESCE(p.{$priceColumn}, 0) AS unit_price,
                    ps.id AS supplier_product_id,
                    ps.supplier_name,
                    ps.supplier_sku,
                    ps.unit_cost
                FROM products p
                LEFT JOIN product_suppliers ps ON ps.product_id = p.id
                WHERE p.stock_quantity <= COALESCE(p.reorder_level, 10)
                AND (CASE WHEN p.is_active IS NULL THEN 1 WHEN LOWER(CAST(p.is_active AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1
                ORDER BY ps.supplier_name NULLS LAST, p.{$nameColumn} ASC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            // Group by supplier
            $grouped = [];
            foreach ($items as $item) {
                $supplier = trim((string)($item['supplier_name'] ?? ''));
                if ($supplier === '') {
                    $supplier = 'Proveedor General Truper';
                }
                
                if (!isset($grouped[$supplier])) {
                    $grouped[$supplier] = [
                        'supplier_name' => $supplier,
                        'items' => []
                    ];
                }
                
                // Suggested order quantity to reach reorder_level * 2
                $stock = (int)$item['stock_quantity'];
                $reorder = (int)$item['reorder_level'];
                $suggestedQty = max(1, ($reorder * 2) - $stock);
                
                // Estimated cost (use supplier cost if available, else unit price * 0.7 as wholesale estimate)
                $cost = (float)($item['unit_cost'] ?? 0);
                if ($cost <= 0) {
                    $cost = (float)$item['unit_price'] * 0.7;
                }
                
                $grouped[$supplier]['items'][] = [
                    'product_id' => (int)$item['product_id'],
                    'sku' => $item['sku'],
                    'product_name' => $item['name'],
                    'stock_quantity' => $stock,
                    'reorder_level' => $reorder,
                    'supplier_product_id' => $item['supplier_product_id'] ? (int)$item['supplier_product_id'] : null,
                    'supplier_sku' => $item['supplier_sku'] ?? '',
                    'suggested_quantity' => $suggestedQty,
                    'estimated_cost' => round($cost, 2)
                ];
            }
            
            $response = [
                'success' => true,
                'drafts' => array_values($grouped)
            ];
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

        case 'sync-images-to-db':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $baseDir = images_root_admin_supply() . '/products/by_code';
            if (!is_dir($baseDir)) {
                $response = ['success' => false, 'message' => 'Directorio de imágenes no encontrado'];
                break;
            }

            $synced = 0;
            $errors = [];
            $dirs = scandir($baseDir);

            if (!is_array($dirs)) {
                $response = ['success' => false, 'message' => 'No se pudo escanear el directorio'];
                break;
            }

            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }

                $fullDir = $baseDir . '/' . $dir;
                if (!is_dir($fullDir)) {
                    continue;
                }

                // Extract SKU (5 digits)
                $sku = preg_replace('/\D+/', '', $dir);
                if (strlen($sku) !== 5) {
                    continue;
                }

                // Find all images
                $matches = glob($fullDir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
                if (empty($matches) || !is_array($matches)) {
                    continue;
                }

                // Sort by priority
                usort($matches, function ($a, $b) {
                    $nameA = strtoupper(pathinfo($a, PATHINFO_FILENAME));
                    $nameB = strtoupper(pathinfo($b, PATHINFO_FILENAME));
                    
                    $scoreA = 90;
                    $scoreB = 90;
                    
                    if (preg_match('/\+FC1$/', $nameA)) $scoreA = 0;
                    elseif (preg_match('/\+E1$/', $nameA)) $scoreA = 1;
                    elseif (preg_match('/\+D1$/', $nameA)) $scoreA = 2;
                    elseif (preg_match('/\+O\d+$/', $nameA)) $scoreA = 3;
                    elseif (strpos($nameA, '+') === false) $scoreA = 50;
                    
                    if (preg_match('/\+FC1$/', $nameB)) $scoreB = 0;
                    elseif (preg_match('/\+E1$/', $nameB)) $scoreB = 1;
                    elseif (preg_match('/\+D1$/', $nameB)) $scoreB = 2;
                    elseif (preg_match('/\+O\d+$/', $nameB)) $scoreB = 3;
                    elseif (strpos($nameB, '+') === false) $scoreB = 50;
                    
                    if ($scoreA === $scoreB) {
                        return strcmp($nameA, $nameB);
                    }
                    return $scoreA <=> $scoreB;
                });

                // Convert to web paths
                $imagePaths = array_map(function ($path) use ($sku) {
                    return 'images/products/by_code/' . $sku . '/' . basename($path);
                }, $matches);

                // Update products table
                if (db_table_exists('products')) {
                    try {
                        $stmt = $pdo->prepare("SELECT id, sku FROM products WHERE sku = ? OR sku LIKE ?");
                        $stmt->execute([$sku, "%{$sku}%"]);
                        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $row = null;
                        foreach ($candidates as $cnd) {
                            if (normalize_sku_admin_supply($cnd['sku']) === $sku) {
                                $row = $cnd;
                                break;
                            }
                        }
                        
                        if ($row) {
                            $coverImage = $imagePaths[0] ?? 'images/products/default-product.svg';
                            $variantsJson = json_encode($imagePaths, JSON_UNESCAPED_UNICODE);
                            
                            $updateStmt = $pdo->prepare("UPDATE products SET image_url = ?, variants_json = ? WHERE id = ?");
                            $updateStmt->execute([$coverImage, $variantsJson, $row['id']]);
                            $synced++;
                        }
                    } catch (Exception $e) {
                        $errors[] = "products SKU {$sku}: " . $e->getMessage();
                    }
                }

                // Update marketplace_ce_products table
                if (db_table_exists('marketplace_ce_products')) {
                    try {
                        $stmt = $pdo->prepare("SELECT id, sku FROM marketplace_ce_products WHERE sku = ? OR sku LIKE ?");
                        $stmt->execute([$sku, "%{$sku}%"]);
                        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $row = null;
                        foreach ($candidates as $cnd) {
                            if (normalize_sku_admin_supply($cnd['sku']) === $sku) {
                                $row = $cnd;
                                break;
                            }
                        }
                        
                        if ($row) {
                            $coverImage = $imagePaths[0] ?? 'images/products/default-product.svg';
                            $variantsJson = json_encode($imagePaths, JSON_UNESCAPED_UNICODE);
                            
                            $updateStmt = $pdo->prepare("UPDATE marketplace_ce_products SET image_url = ?, variants_json = ? WHERE id = ?");
                            $updateStmt->execute([$coverImage, $variantsJson, $row['id']]);
                            $synced++;
                        }
                    } catch (Exception $e) {
                        $errors[] = "marketplace_ce_products SKU {$sku}: " . $e->getMessage();
                    }
                }
            }

            $response = [
                'success' => true,
                'message' => "Sincronización completada: {$synced} productos actualizados",
                'synced' => $synced,
                'errors' => $errors
            ];
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

// Clear persistent cache on successful POST operations (writes)
if ($method === 'POST' && isset($response) && is_array($response) && ($response['success'] ?? false) === true) {
    if (function_exists('cache_clear')) {
        cache_clear();
    }
}

$buffer = ob_get_clean();
if (!empty($buffer)) {
    error_log('admin_supply API buffered output: ' . trim($buffer));
}

// JSON minificado para reducir tamaño de respuesta
echo json_encode($response, JSON_UNESCAPED_UNICODE);
