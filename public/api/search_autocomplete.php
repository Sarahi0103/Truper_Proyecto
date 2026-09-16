<?php
/**
 * API de Búsqueda Autocompletada
 * Proporciona sugerencias de búsqueda en tiempo real con precios diferenciados por canal (Online / POS)
 */

require_once '../../config/config.php';

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
$limit = min(max(1, (int)($_GET['limit'] ?? 10)), 50); // Máximo 50 resultados
$channel = sanitize($_GET['channel'] ?? ($_GET['mode'] ?? 'online'));

if (strlen($query) < 1) {
    echo json_encode(['success' => true, 'suggestions' => [], 'count' => 0]);
    exit;
}

try {
    $suggestions = [];

    // Expresión de visibilidad y precio según canal para products
    if ($channel === 'pos') {
        $visCond = "(CASE WHEN show_in_pos IS NULL THEN (CASE WHEN is_active IS NULL THEN true ELSE is_active END) ELSE show_in_pos END) = true";
        $priceExpr = "COALESCE(NULLIF(price_pos, 0), unit_price, sell_price, net_price, 0)";
        $mktVisCond = "is_active = true";
        $mktPriceExpr = "COALESCE(unit_price, 0)";
    } elseif ($channel === 'online') {
        $visCond = "(CASE WHEN show_in_online IS NULL THEN (CASE WHEN is_active IS NULL THEN true ELSE is_active END) ELSE show_in_online END) = true";
        $priceExpr = "COALESCE(NULLIF(price_online, 0), unit_price, sell_price, net_price, 0)";
        $mktVisCond = "is_active = true AND COALESCE(show_in_online, true) = true";
        $mktPriceExpr = "COALESCE(NULLIF(price_online, 0), unit_price, 0)";
    } else {
        $visCond = "(CASE WHEN is_active IS NULL THEN true ELSE is_active END) = true";
        $priceExpr = "COALESCE(NULLIF(price_online, 0), NULLIF(price_pos, 0), unit_price, sell_price, net_price, 0)";
        $mktVisCond = "is_active = true";
        $mktPriceExpr = "COALESCE(NULLIF(price_online, 0), unit_price, 0)";
    }
    
    // Buscar en productos propios (catálogo)
    $stmt = $pdo->prepare("
        SELECT 
            id,
            sku,
            name,
            category,
            CAST({$priceExpr} AS NUMERIC) AS price,
            COALESCE(image_url, 'images/products/default-product.svg') AS image_url,
            'catalog' as source
        FROM products 
        WHERE {$visCond}
        AND (name ILIKE ? OR sku ILIKE ? OR category ILIKE ?)
        ORDER BY 
            CASE 
                WHEN sku ILIKE ? THEN 1
                WHEN name ILIKE ? THEN 2
                ELSE 3
            END,
            name ASC
        LIMIT ?
    ");
    
    $searchPattern = "%$query%";
    $exactPattern = "$query%";
    
    $stmt->bindValue(1, $searchPattern, PDO::PARAM_STR);
    $stmt->bindValue(2, $searchPattern, PDO::PARAM_STR);
    $stmt->bindValue(3, $searchPattern, PDO::PARAM_STR);
    $stmt->bindValue(4, $exactPattern, PDO::PARAM_STR);
    $stmt->bindValue(5, $exactPattern, PDO::PARAM_STR);
    $stmt->bindValue(6, (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $catalogResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar en marketplace si existe la tabla
    $marketplaceResults = [];
    if (db_table_exists('marketplace_ce_products')) {
        try {
            $stmtMarket = $pdo->prepare("
                SELECT 
                    id,
                    sku,
                    name,
                    category,
                    CAST({$mktPriceExpr} AS NUMERIC) AS price,
                    COALESCE(image_url, 'images/products/default-product.svg') AS image_url,
                    'marketplace' as source
                FROM marketplace_ce_products 
                WHERE {$mktVisCond}
                AND (name ILIKE ? OR sku ILIKE ? OR category ILIKE ?)
                ORDER BY 
                    CASE 
                        WHEN sku ILIKE ? THEN 1
                        WHEN name ILIKE ? THEN 2
                        ELSE 3
                    END,
                    name ASC
                LIMIT ?
            ");
            
            $stmtMarket->bindValue(1, $searchPattern, PDO::PARAM_STR);
            $stmtMarket->bindValue(2, $searchPattern, PDO::PARAM_STR);
            $stmtMarket->bindValue(3, $searchPattern, PDO::PARAM_STR);
            $stmtMarket->bindValue(4, $exactPattern, PDO::PARAM_STR);
            $stmtMarket->bindValue(5, $exactPattern, PDO::PARAM_STR);
            $stmtMarket->bindValue(6, (int)$limit, PDO::PARAM_INT);
            $stmtMarket->execute();
            
            $marketplaceResults = $stmtMarket->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $marketplaceResults = [];
        }
    }
    
    // Combinar resultados
    $allResults = array_merge($catalogResults, $marketplaceResults);
    
    // Eliminar duplicados por SKU
    $uniqueResults = [];
    $seenSkus = [];
    
    foreach ($allResults as $result) {
        $skuKey = $result['sku'] . '_' . $result['source'];
        if (!in_array($skuKey, $seenSkus, true)) {
            $seenSkus[] = $skuKey;
            $uniqueResults[] = [
                'id' => (int)$result['id'],
                'sku' => (string)$result['sku'],
                'name' => (string)$result['name'],
                'category' => (string)($result['category'] ?? 'General'),
                'price' => (float)$result['price'],
                'image_url' => (string)$result['image_url'],
                'source' => (string)$result['source']
            ];
        }
    }
    
    // Limitar resultados finales
    $suggestions = array_slice($uniqueResults, 0, $limit);
    
    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions,
        'count' => count($suggestions)
    ]);
    
} catch (Exception $e) {
    error_log("Search autocomplete error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error en la búsqueda: ' . $e->getMessage(),
        'suggestions' => []
    ]);
}
