<?php
/**
 * API de Búsqueda Avanzada
 * Búsqueda con autocomplete, filtros, ordenamiento sensible a canales (Online / POS)
 */

require_once '../../config/config.php';
require_once '../../src/utils/AppLogger.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $channel = sanitize($_GET['channel'] ?? ($_GET['mode'] ?? 'online'));

    // Expresión de visibilidad según el canal
    if ($channel === 'pos') {
        $visibilityExpr = "(CASE WHEN show_in_pos IS NULL THEN (CASE WHEN is_active IS NULL THEN true ELSE is_active END) ELSE show_in_pos END) = true";
        $channelPriceExpr = "COALESCE(NULLIF(price_pos, 0), unit_price, sell_price, net_price, 0)";
    } elseif ($channel === 'online') {
        $visibilityExpr = "(CASE WHEN show_in_online IS NULL THEN (CASE WHEN is_active IS NULL THEN true ELSE is_active END) ELSE show_in_online END) = true";
        $channelPriceExpr = "COALESCE(NULLIF(price_online, 0), unit_price, sell_price, net_price, 0)";
    } else {
        $visibilityExpr = "(CASE WHEN is_active IS NULL THEN true ELSE is_active END) = true";
        $channelPriceExpr = "COALESCE(NULLIF(price_online, 0), NULLIF(price_pos, 0), unit_price, sell_price, net_price, 0)";
    }

    switch ($action) {
        case 'autocomplete':
            // Autocomplete de búsqueda
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $query = trim($_GET['q'] ?? '');
            $limit = min((int)($_GET['limit'] ?? 10), 30);
            
            if (strlen($query) < 1) {
                echo json_encode(['success' => true, 'results' => []]);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, name, sku, COALESCE(image_url, 'images/products/default-product.svg') AS image_url,
                       CAST({$channelPriceExpr} AS NUMERIC) AS price,
                       category
                FROM products
                WHERE {$visibilityExpr}
                AND (name ILIKE ? OR sku ILIKE ? OR description ILIKE ? OR category ILIKE ?)
                ORDER BY 
                    CASE 
                        WHEN sku ILIKE ? THEN 1
                        WHEN name ILIKE ? THEN 2
                        ELSE 3
                    END,
                    name ASC
                LIMIT ?
            ");
            $searchTerm = "%{$query}%";
            $exactTerm = "{$query}%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $exactTerm, $exactTerm, $limit]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as &$r) {
                $r['price'] = (float)($r['price'] ?? 0);
            }
            unset($r);
            
            echo json_encode(['success' => true, 'results' => $results]);
            break;
            
        case 'search':
            // Búsqueda completa con filtros
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $query = trim($_GET['q'] ?? '');
            $category = trim($_GET['category'] ?? '');
            $minPrice = (float)($_GET['min_price'] ?? 0);
            $maxPrice = !empty($_GET['max_price']) ? (float)$_GET['max_price'] : PHP_FLOAT_MAX;
            $sortBy = $_GET['sort_by'] ?? 'relevance';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;
            
            $whereConditions = [$visibilityExpr];
            $params = [];
            
            // Búsqueda por texto
            if (!empty($query)) {
                $whereConditions[] = "(name ILIKE ? OR sku ILIKE ? OR description ILIKE ? OR category ILIKE ?)";
                $searchTerm = "%{$query}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Filtro por categoría
            if (!empty($category)) {
                $whereConditions[] = "category ILIKE ?";
                $params[] = "%{$category}%";
            }
            
            // Filtro por precio
            if ($minPrice > 0 || $maxPrice < PHP_FLOAT_MAX) {
                $whereConditions[] = "({$channelPriceExpr}) BETWEEN ? AND ?";
                $params[] = $minPrice;
                $params[] = $maxPrice;
            }
            
            // Ordenamiento
            $orderBy = match($sortBy) {
                'price_asc' => "{$channelPriceExpr} ASC",
                'price_desc' => "{$channelPriceExpr} DESC",
                'name_asc' => 'name ASC',
                'name_desc' => 'name DESC',
                'newest' => 'id DESC',
                default => 'name ASC'
            };
            
            // Contar total
            $countSql = "SELECT COUNT(*) FROM products WHERE " . implode(' AND ', $whereConditions);
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
            
            // Obtener resultados
            $sql = "SELECT id, name, sku, description, category, COALESCE(image_url, 'images/products/default-product.svg') AS image_url,
                           CAST({$channelPriceExpr} AS NUMERIC) AS price,
                           unit_price, price_online, price_pos, stock_quantity, variants_json, is_active
                    FROM products 
                    WHERE " . implode(' AND ', $whereConditions) . " 
                    ORDER BY {$orderBy} 
                    LIMIT ? OFFSET ?";
            $queryParams = array_merge($params, [$perPage, $offset]);
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($queryParams);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($products as &$p) {
                $p['price'] = (float)($p['price'] ?? 0);
            }
            unset($p);
            
            echo json_encode([
                'success' => true,
                'products' => $products,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ]);
            break;
            
        case 'filters':
            // Obtener filtros disponibles
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            // Categorías disponibles
            $stmt = $pdo->prepare("
                SELECT category AS name, COUNT(id) AS product_count
                FROM products
                WHERE {$visibilityExpr} AND category IS NOT NULL AND category != ''
                GROUP BY category
                ORDER BY category ASC
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Rango de precios
            $stmtRange = $pdo->prepare("
                SELECT COALESCE(MIN({$channelPriceExpr}), 0) AS min_price, 
                       COALESCE(MAX({$channelPriceExpr}), 0) AS max_price
                FROM products
                WHERE {$visibilityExpr}
            ");
            $stmtRange->execute();
            $priceRange = $stmtRange->fetch(PDO::FETCH_ASSOC);
            if ($priceRange) {
                $priceRange['min_price'] = (float)$priceRange['min_price'];
                $priceRange['max_price'] = (float)$priceRange['max_price'];
            }
            
            echo json_encode([
                'success' => true,
                'categories' => $categories,
                'price_range' => $priceRange
            ]);
            break;
            
        case 'history':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    AppLogger::error("Search API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
