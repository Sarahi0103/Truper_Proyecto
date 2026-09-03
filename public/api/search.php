<?php
/**
 * API de Búsqueda Avanzada
 * Búsqueda con autocomplete, filtros, ordenamiento
 */

require_once '../../config/config.php';
require_once '../../src/utils/AppLogger.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'autocomplete':
            // Autocomplete de búsqueda
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $query = $_GET['q'] ?? '';
            $limit = (int)($_GET['limit'] ?? 10);
            
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'results' => []]);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, name, sku, image_url, price
                FROM products
                WHERE is_online_visible = true
                AND (name ILIKE ? OR sku ILIKE ? OR description ILIKE ?)
                ORDER BY name
                LIMIT ?
            ");
            $searchTerm = "%{$query}%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'results' => $results]);
            break;
            
        case 'search':
            // Búsqueda completa con filtros
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $query = $_GET['q'] ?? '';
            $category = $_GET['category'] ?? '';
            $minPrice = $_GET['min_price'] ?? 0;
            $maxPrice = $_GET['max_price'] ?? PHP_FLOAT_MAX;
            $sortBy = $_GET['sort_by'] ?? 'relevance';
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 20);
            $offset = ($page - 1) * $perPage;
            
            $whereConditions = ["is_online_visible = true"];
            $params = [];
            
            // Búsqueda por texto
            if (!empty($query)) {
                $whereConditions[] = "(name ILIKE ? OR sku ILIKE ? OR description ILIKE ?)";
                $searchTerm = "%{$query}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Filtro por categoría
            if (!empty($category)) {
                $whereConditions[] = "category_id = ?";
                $params[] = $category;
            }
            
            // Filtro por precio
            $whereConditions[] = "price BETWEEN ? AND ?";
            $params[] = $minPrice;
            $params[] = $maxPrice;
            
            // Ordenamiento
            $orderBy = match($sortBy) {
                'price_asc' => 'price ASC',
                'price_desc' => 'price DESC',
                'name_asc' => 'name ASC',
                'name_desc' => 'name DESC',
                'newest' => 'created_at DESC',
                default => 'name ASC'
            };
            
            // Contar total
            $countSql = "SELECT COUNT(*) FROM products WHERE " . implode(' AND ', $whereConditions);
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Obtener resultados
            $sql = "SELECT * FROM products WHERE " . implode(' AND ', $whereConditions) . " ORDER BY {$orderBy} LIMIT ? OFFSET ?";
            $params[] = $perPage;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
            
            // Categorías
            $stmt = $pdo->query("
                SELECT c.id, c.name, COUNT(p.id) as product_count
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id AND p.is_online_visible = true
                GROUP BY c.id, c.name
                ORDER BY c.name
            ");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Rango de precios
            $stmt = $pdo->query("
                SELECT MIN(price) as min_price, MAX(price) as max_price
                FROM products
                WHERE is_online_visible = true
            ");
            $priceRange = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'categories' => $categories,
                'price_range' => $priceRange
            ]);
            break;
            
        case 'history':
            // Guardar búsqueda en historial (requiere login)
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            require_login();
            
            $input = json_decode(file_get_contents('php://input'), true);
            $query = $input['query'] ?? '';
            
            if (empty($query)) {
                echo json_encode(['success' => false, 'message' => 'Query requerido']);
                exit;
            }
            
            // Guardar en localStorage del cliente (no en BD por privacidad)
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    AppLogger::error("Search API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
