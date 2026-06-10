<?php
/**
 * API de productos con carga diferida y caché
 * Implementa batches de 50 productos y caché APCu
 */

require_once '../../config/config.php';
require_login();

header('Content-Type: application/json');

$page = intval($_GET['page'] ?? 1);
$limit = intval($_GET['limit'] ?? 50);
$offset = ($page - 1) * $limit;
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

require_once '../../src/Services/ProductCacheService.php';
require_once '../../backend/models/Product.php';

$cacheService = new ProductCacheService();
$productModel = new Product();

// Generar clave de caché
$cacheKey = $cacheService->generateCacheKey([
    'category' => $category,
    'search' => $search,
    'page' => $page
]);

// Intentar obtener del caché
$cachedProducts = $cacheService->getProducts($cacheKey);
if ($cachedProducts !== null) {
    echo json_encode([
        'success' => true,
        'products' => $cachedProducts,
        'cached' => true,
        'page' => $page,
        'limit' => $limit
    ]);
    exit();
}

// Construir consulta
$sql = "SELECT id, sku, name, category, unit_price, stock_quantity, image_url 
        FROM products 
        WHERE is_active = true";

$params = [];

if (!empty($category)) {
    $sql .= " AND category = :category";
    $params[':category'] = $category;
}

if (!empty($search)) {
    $sql .= " AND (name ILIKE :search OR sku ILIKE :search)";
    $params[':search'] = "%{$search}%";
}

$sql .= " ORDER BY name ASC LIMIT :limit OFFSET :offset";
$params[':limit'] = $limit;
$params[':offset'] = $offset;

try {
    $stmt = $GLOBALS['db']->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Guardar en caché
    $cacheService->setProducts($cacheKey, $products);

    echo json_encode([
        'success' => true,
        'products' => $products,
        'cached' => false,
        'page' => $page,
        'limit' => $limit
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener productos: ' . $e->getMessage()
    ]);
}
