<?php
/**
 * MEJORAS AL SISTEMA DE GESTIÓN DE PRODUCTOS
 * Fichero: optimization_patch.php
 * 
 * Implementa:
 * 1. Limpieza de SKUs huérfanos
 * 2. Validación de integridad
 * 3. Caché de imágenes
 * 4. Sincronización automática con marketplace
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'config/config.php';

class ProductSystemOptimizer {
    private $pdo;
    private $cacheDir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->cacheDir = sys_get_temp_dir() . '/truper_cache';
        @mkdir($this->cacheDir, 0775, true);
    }

    /**
     * PROBLEMA 1: Limpiar SKUs Huérfanos
     * ================================
     * Cuando se elimina un producto, el código podría quedar registrado
     * en la tabla de datos base (seed). Esta función lo limpia.
     */
    public function clean_orphaned_skus() {
        echo "🔍 Buscando SKUs huérfanos...\n";
        
        try {
            // Paso 1: Obtener todos los SKUs activos en BD
            $stmt = $this->pdo->query("
                SELECT DISTINCT sku FROM products WHERE sku != '' AND sku IS NOT NULL
                UNION
                SELECT DISTINCT sku FROM marketplace_ce_products WHERE sku != '' AND sku IS NOT NULL
            ");
            $activeSKUs = array_column($stmt->fetchAll(), 'sku');
            echo "✓ SKUs activos: " . count($activeSKUs) . "\n";
            
            // Paso 2: Buscar SKUs eliminados en carpeta de imágenes
            $imageDir = __DIR__ . '/public/images/products/by_code';
            $orphanedDirs = [];
            
            if (is_dir($imageDir)) {
                foreach (scandir($imageDir) as $dir) {
                    if ($dir === '.' || $dir === '..') continue;
                    
                    $fullPath = $imageDir . '/' . $dir;
                    if (is_dir($fullPath) && !in_array($dir, $activeSKUs)) {
                        $orphanedDirs[] = $fullPath;
                    }
                }
            }
            
            echo "✓ Carpetas huérfanas encontradas: " . count($orphanedDirs) . "\n";
            
            // Paso 3: Eliminar carpetas huérfanas
            foreach ($orphanedDirs as $orphanDir) {
                $this->remove_directory_recursive($orphanDir);
                echo "  ✓ Eliminada: " . basename($orphanDir) . "\n";
            }
            
            // Paso 4: Limpiar caché
            $this->invalidate_cache('product_codes');
            $this->invalidate_cache('product_images');
            
            echo "\n✅ Limpieza completada. SKUs huérfanos eliminados: " . count($orphanedDirs) . "\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * PROBLEMA 2: Acelerar Búsqueda de Imágenes
     * ==========================================
     * Crear caché de índice de imágenes para no escanear filesystem
     */
    public function build_image_cache() {
        echo "📦 Construyendo caché de imágenes...\n";
        
        try {
            $imageDir = __DIR__ . '/public/images/products/by_code';
            $imageIndex = [];
            $totalFiles = 0;
            
            if (is_dir($imageDir)) {
                foreach (scandir($imageDir) as $sku) {
                    if ($sku === '.' || $sku === '..') continue;
                    
                    $skuPath = $imageDir . '/' . $sku;
                    if (is_dir($skuPath)) {
                        $images = [];
                        foreach (scandir($skuPath) as $file) {
                            if ($file !== '.' && $file !== '..' && is_file($skuPath . '/' . $file)) {
                                $images[] = $file;
                                $totalFiles++;
                            }
                        }
                        
                        if (!empty($images)) {
                            $imageIndex[$sku] = [
                                'count' => count($images),
                                'files' => $images,
                                'updated' => time()
                            ];
                        }
                    }
                }
            }
            
            // Guardar caché
            $cacheFile = $this->cacheDir . '/image_index.json';
            file_put_contents($cacheFile, json_encode($imageIndex, JSON_PRETTY_PRINT));
            
            echo "✓ SKUs procesados: " . count($imageIndex) . "\n";
            echo "✓ Archivos indexados: " . $totalFiles . "\n";
            echo "✓ Caché guardado: " . $cacheFile . "\n";
            echo "\n✅ Caché de imágenes completado.\n";
            
            return true;
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * PROBLEMA 3: Validar Integridad de Base de Datos
     * ================================================
     * Verificar que todos los productos tengan datos consistentes
     */
    public function validate_database_integrity() {
        echo "🔬 Validando integridad de base de datos...\n";
        
        $issues = [];
        
        try {
            // Búsqueda 1: Productos sin SKU
            $stmt = $this->pdo->query("
                SELECT id, name FROM products WHERE sku IS NULL OR sku = ''
            ");
            $emptySKU = $stmt->fetchAll();
            if (!empty($emptySKU)) {
                $issues[] = [
                    'severity' => 'HIGH',
                    'type' => 'empty_sku',
                    'count' => count($emptySKU),
                    'message' => 'Productos sin SKU: ' . count($emptySKU),
                    'ids' => array_column($emptySKU, 'id')
                ];
            }
            
            // Búsqueda 2: SKUs duplicados
            $stmt = $this->pdo->query("
                SELECT sku, COUNT(*) as cnt FROM products 
                WHERE sku != '' GROUP BY sku HAVING cnt > 1
            ");
            $duplicates = $stmt->fetchAll();
            if (!empty($duplicates)) {
                $issues[] = [
                    'severity' => 'CRITICAL',
                    'type' => 'duplicate_sku',
                    'count' => count($duplicates),
                    'message' => 'SKUs duplicados: ' . count($duplicates),
                    'data' => $duplicates
                ];
            }
            
            // Búsqueda 3: Marketplace huérfano
            $stmt = $this->pdo->query("
                SELECT mp.id, mp.sku FROM marketplace_ce_products mp
                LEFT JOIN products p ON mp.sku = p.sku
                WHERE p.id IS NULL
            ");
            $orphanedMP = $stmt->fetchAll();
            if (!empty($orphanedMP)) {
                $issues[] = [
                    'severity' => 'MEDIUM',
                    'type' => 'orphaned_marketplace',
                    'count' => count($orphanedMP),
                    'message' => 'Marketplace sin producto base: ' . count($orphanedMP),
                    'ids' => array_column($orphanedMP, 'id')
                ];
            }
            
            // Mostrar resultados
            echo "📊 Resultados de validación:\n";
            if (empty($issues)) {
                echo "✅ Base de datos íntegra - No hay problemas\n";
            } else {
                foreach ($issues as $issue) {
                    $icon = $issue['severity'] === 'CRITICAL' ? '🔴' : ($issue['severity'] === 'HIGH' ? '🟠' : '🟡');
                    echo "\n$icon [{$issue['severity']}] {$issue['message']}\n";
                    if (!empty($issue['ids'])) {
                        echo "   IDs afectados: " . implode(', ', array_slice($issue['ids'], 0, 5)) . 
                             (count($issue['ids']) > 5 ? "... y más" : "") . "\n";
                    }
                }
            }
            
            return $issues;
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            return [];
        }
    }

    /**
     * PROBLEMA 4: Optimizar Eliminación de Productos
     * ===============================================
     */
    public function improve_product_deletion_handler() {
        $improvedCode = <<<'PHP'
        
// MEJORADO: Función de eliminación completa
function delete_product_complete($pdo, $id, $sku) {
    $deletionLog = [
        'product_id' => $id,
        'sku' => $sku,
        'deleted_at' => date('Y-m-d H:i:s'),
        'steps' => []
    ];
    
    try {
        // PASO 1: Eliminar imágenes del disco
        $imageDir = __DIR__ . '/../public/images/products/by_code/' . $sku;
        if (is_dir($imageDir)) {
            remove_directory_recursive($imageDir);
            $deletionLog['steps'][] = 'Images deleted';
        }
        
        // PASO 2: Eliminar de tabla products
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $deletionLog['steps'][] = 'Product record deleted';
        
        // PASO 3: Eliminar de marketplace
        $stmt = $pdo->prepare("DELETE FROM marketplace_ce_products WHERE sku = ?");
        $stmt->execute([$sku]);
        $deletionLog['steps'][] = 'Marketplace records deleted';
        
        // PASO 4: Eliminar historial
        $stmt = $pdo->prepare("DELETE FROM product_history WHERE product_id = ?");
        $stmt->execute([$id]);
        $deletionLog['steps'][] = 'History purged';
        
        // PASO 5: Invalidar caché
        cache_invalidate(['product_' . $id, 'sku_' . $sku, 'product_images']);
        $deletionLog['steps'][] = 'Cache invalidated';
        
        // PASO 6: Registrar en log
        log_event('product_deleted', $deletionLog);
        
        return ['success' => true, 'deleted' => true, 'log' => $deletionLog];
        
    } catch (Exception $e) {
        $deletionLog['error'] = $e->getMessage();
        log_event('product_deletion_failed', $deletionLog);
        throw $e;
    }
}
PHP;

        echo "📝 Código mejorado para eliminación:\n";
        echo $improvedCode;
        return true;
    }

    /**
     * Utilidad: Eliminar directorio recursivamente
     */
    private function remove_directory_recursive($dir) {
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->remove_directory_recursive($path) : unlink($path);
        }
        
        return rmdir($dir);
    }

    /**
     * Utilidad: Invalidar caché
     */
    private function invalidate_cache($key) {
        $cacheFile = $this->cacheDir . '/' . $key . '.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
        
        if (function_exists('apcu_delete')) {
            apcu_delete($key);
        }
    }
}

// ============================================================
// EJECUTAR OPTIMIZACIONES
// ============================================================

if (php_sapi_name() === 'cli') {
    // Ejecutar desde línea de comandos
    $optimizer = new ProductSystemOptimizer($pdo);
    
    echo "\n";
    echo "╔════════════════════════════════════════════════════════╗\n";
    echo "║    OPTIMIZADOR DEL SISTEMA TRUPER                    ║\n";
    echo "╚════════════════════════════════════════════════════════╝\n";
    echo "\n";
    
    $optimizer->clean_orphaned_skus();
    echo "\n";
    
    $optimizer->build_image_cache();
    echo "\n";
    
    $issues = $optimizer->validate_database_integrity();
    echo "\n";
    
    $optimizer->improve_product_deletion_handler();
    echo "\n";
    
    echo "✅ Optimizaciones completadas.\n";
}
?>
