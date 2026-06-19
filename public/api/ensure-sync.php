<?php
/**
 * Función helper para sincronizar stocks automáticamente
 * Se llama después de cualquier operación que modifique stock o productos
 */

function auto_sync_stock_after_change($pdo, $sku = null) {
    try {
        if ($sku === null || $sku === '') {
            // Sincronizar TODO
            $stmt = $pdo->query("
                SELECT p.sku, p.stock_quantity 
                FROM products p
                WHERE p.stock_quantity != COALESCE((
                    SELECT stock_quantity FROM marketplace_ce_products m 
                    WHERE m.sku = p.sku LIMIT 1
                ), 0)
            ");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            // Sincronizar SKU específico
            $stmt = $pdo->prepare("
                SELECT stock_quantity FROM products WHERE sku = ? LIMIT 1
            ");
            $stmt->execute([$sku]);
            $productStock = $stmt->fetchColumn();
            
            if ($productStock !== false) {
                // Actualizar en marketplace
                $update = $pdo->prepare("
                    UPDATE marketplace_ce_products 
                    SET stock_quantity = ? 
                    WHERE sku = ? OR sku LIKE ?
                ");
                $update->execute([$productStock, $sku, "%{$sku}%"]);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error en auto_sync_stock_after_change: " . $e->getMessage());
        return false;
    }
}

// Función para limpiar registros huérfanos (productos eliminados)
function cleanup_orphaned_records($pdo) {
    // Desactivado para permitir productos independientes en Marketplace CE
    return true;
}
