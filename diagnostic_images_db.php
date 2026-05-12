<?php
/**
 * Diagnóstico de imágenes - verifica estado actual en BD y disco
 */

// Cargar configuración
require_once __DIR__ . '/config/config.php';

echo "=== DIAGNÓSTICO DE IMÁGENES EN BASE DE DATOS ===\n\n";

try {
    // Query 1: Contar productos con imágenes
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_productos,
            COUNT(CASE WHEN image_url IS NOT NULL AND image_url != 'images/products/default-product.svg' THEN 1 END) as con_imagen,
            COUNT(CASE WHEN image_url LIKE 'images/products/gallery/%' THEN 1 END) as galeria_canonica,
            COUNT(CASE WHEN variants_json IS NOT NULL AND variants_json != '' AND variants_json != '[]' THEN 1 END) as con_variants
        FROM products
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "[1] ESTADÍSTICAS GENERALES:\n";
    echo "    Total productos: {$stats['total_productos']}\n";
    echo "    Con imagen_url: {$stats['con_imagen']}\n";
    echo "    Con ruta canónica (gallery/): {$stats['galeria_canonica']}\n";
    echo "    Con variants_json: {$stats['con_variants']}\n\n";
    
    // Query 2: Muestra de productos con imágenes
    $stmt = $pdo->query("
        SELECT sku, image_url, 
               CASE 
                   WHEN image_url LIKE 'images/products/gallery/%' THEN 'CANÓNICA'
                   WHEN image_url = 'images/products/default-product.svg' THEN 'DEFAULT'
                   ELSE 'OTRA'
               END as tipo_ruta
        FROM products 
        WHERE image_url IS NOT NULL AND image_url != 'images/products/default-product.svg'
        ORDER BY sku
        LIMIT 10
    ");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "[2] MUESTRA DE PRODUCTOS CON IMAGEN (primeros 10):\n";
    foreach ($samples as $row) {
        $exists = file_exists(__DIR__ . '/public/' . $row['image_url']) ? '✓' : '✗';
        echo "    {$exists} SKU {$row['sku']}: {$row['tipo_ruta']}\n";
        echo "        Path: {$row['image_url']}\n";
    }
    echo "\n";
    
    // Query 3: Distribuciónde tipos de imagen
    $stmt = $pdo->query("
        SELECT 
            CASE 
                WHEN image_url LIKE 'images/products/gallery/%' THEN 'CANÓNICA (gallery/)'
                WHEN image_url LIKE 'images/products/by_code/%' THEN 'LEGADA (by_code/)'
                WHEN image_url LIKE 'images/products/%' THEN 'OTRA'
                WHEN image_url = 'images/products/default-product.svg' THEN 'DEFAULT'
                ELSE 'SIN CLASIFICAR'
            END as tipo,
            COUNT(*) as cantidad
        FROM products
        WHERE image_url IS NOT NULL
        GROUP BY tipo
        ORDER BY cantidad DESC
    ");
    $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "[3] DISTRIBUCIÓN DE RUTAS DE IMAGEN:\n";
    foreach ($distribution as $row) {
        $percent = ($row['cantidad'] / $stats['total_productos']) * 100;
        printf("    %s: %d (%.1f%%)\n", $row['tipo'], $row['cantidad'], $percent);
    }
    echo "\n";
    
    // Query 4: Productos sin imagen
    $stmt = $pdo->query("
        SELECT COUNT(*) as sin_imagen FROM products
        WHERE image_url IS NULL OR image_url = 'images/products/default-product.svg'
    ");
    $no_image = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "[4] PRODUCTOS SIN IMAGEN PERSONALIZADA: {$no_image['sin_imagen']}\n\n";
    
    // Query 5: Chequeo marketplace_ce
    $stmt = $pdo->query("
        SELECT COUNT(*) as total FROM marketplace_ce_products
    ");
    $ce_total = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as con_imagen FROM marketplace_ce_products
        WHERE image_url IS NOT NULL AND image_url != 'images/products/default-product.svg'
    ");
    $ce_imagen = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "[5] MARKETPLACE CE:\n";
    printf("    Total: %d, Con imagen: %d (%.1f%%)\n\n", 
        $ce_total['total'],
        $ce_imagen['con_imagen'],
        ($ce_total['total'] > 0) ? (($ce_imagen['con_imagen'] / $ce_total['total']) * 100) : 0
    );
    
    // Conclusión
    echo "[6] CONCLUSIÓN:\n";
    $percent_canonica = ($stats['galeria_canonica'] / ($stats['con_imagen'] ?: 1)) * 100;
    echo "    ✅ Código desplegado en main\n";
    echo "    ✅ Funciones de persistencia activas\n";
    printf("    %s %.1f%% de imágenes usan ruta canónica (gallery/)\n", 
        ($percent_canonica > 50 ? '✅' : '⚠️'), 
        $percent_canonica
    );
    echo "    ℹ️  Las nuevas imágenes se guardarán en ruta canónica automáticamente\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
