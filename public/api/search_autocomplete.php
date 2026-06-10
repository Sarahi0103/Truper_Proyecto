<?php
/**
 * API de Búsqueda Autocompletada
 * Proporciona sugerencias de búsqueda en tiempo real
 */

require_once '../../config/config.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$limit = min((int)($_GET['limit'] ?? 10), 50); // Máximo 50 resultados

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'suggestions' => []]);
    exit;
}

try {
    $suggestions = [];
    
    // Buscar en productos propios
    $stmt = $pdo->prepare("
        SELECT 
            id,
            sku,
            name,
            category,
            price,
            'catalog' as source
        FROM productos 
        WHERE visible = true 
        AND (name ILIKE ? OR sku ILIKE ? OR category ILIKE ?)
        ORDER BY 
            CASE 
                WHEN name ILIKE ? THEN 1
                WHEN sku ILIKE ? THEN 2
                ELSE 3
            END,
            name ASC
        LIMIT ?
    ");
    
    $searchPattern = "%$query%";
    $exactPattern = "$query%";
    
    $stmt->execute([
        $searchPattern,
        $searchPattern,
        $searchPattern,
        $exactPattern,
        $exactPattern,
        $limit
    ]);
    
    $catalogResults = $stmt->fetchAll();
    
    // Buscar en marketplace si existe la tabla
    try {
        $stmtMarket = $pdo->prepare("
            SELECT 
                id,
                sku,
                name,
                category,
                price,
                'marketplace' as source
            FROM marketplace_ce 
            WHERE visible = true 
            AND (name ILIKE ? OR sku ILIKE ? OR category ILIKE ?)
            ORDER BY 
                CASE 
                    WHEN name ILIKE ? THEN 1
                    WHEN sku ILIKE ? THEN 2
                    ELSE 3
                END,
                name ASC
            LIMIT ?
        ");
        
        $stmtMarket->execute([
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $exactPattern,
            $exactPattern,
            $limit
        ]);
        
        $marketplaceResults = $stmtMarket->fetchAll();
    } catch (Exception $e) {
        $marketplaceResults = [];
    }
    
    // Combinar resultados
    $allResults = array_merge($catalogResults, $marketplaceResults);
    
    // Eliminar duplicados por SKU
    $uniqueResults = [];
    $seenSkus = [];
    
    foreach ($allResults as $result) {
        $skuKey = $result['sku'] . '_' . $result['source'];
        if (!in_array($skuKey, $seenSkus)) {
            $seenSkus[] = $skuKey;
            $uniqueResults[] = [
                'id' => $result['id'],
                'sku' => $result['sku'],
                'name' => $result['name'],
                'category' => $result['category'],
                'price' => (float)$result['price'],
                'source' => $result['source']
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
        'message' => 'Error en la búsqueda',
        'suggestions' => []
    ]);
}
