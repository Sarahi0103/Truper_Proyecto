<?php
/**
 * Script para convertir imágenes base64 guardadas en BD a archivos reales
 * Ejecutar desde terminal: php clean_base64_images.php
 */

require_once 'config/config.php';

echo "=== Limpiador de imágenes base64 ===\n\n";

// Ensure images directory exists
$imagesDir = __DIR__ . '/images/products';
if (!is_dir($imagesDir)) {
    mkdir($imagesDir, 0755, true);
    echo "✓ Directorio creado: $imagesDir\n";
}

try {
    // Process products table
    if (db_table_exists('products')) {
        echo "\nProcesando tabla 'products'...\n";
        
        $stmt = $pdo->query("SELECT id, sku, image_url, variants_json FROM products WHERE image_url LIKE 'data:image%' OR variants_json LIKE '%data:image%'");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $converted = 0;
        foreach ($rows as $row) {
            $id = $row['id'];
            $sku = $row['sku'];
            $imageUrl = $row['image_url'];
            $variantsJson = $row['variants_json'];
            
            $newImageUrl = $imageUrl;
            $newVariantsJson = $variantsJson;
            
            // Convert image_url if it's base64
            if (!empty($imageUrl) && strpos($imageUrl, 'data:image') === 0) {
                $newImageUrl = convert_base64_image_to_file($imageUrl, $sku);
                if ($newImageUrl && $newImageUrl !== $imageUrl) {
                    echo "  ✓ image_url convertida para producto $sku\n";
                }
            }
            
            // Convert variants_json if it contains base64
            if (!empty($variantsJson) && strpos($variantsJson, 'data:image') !== false) {
                $variants = json_decode($variantsJson, true);
                if (is_array($variants)) {
                    $modified = false;
                    foreach ($variants as &$variant) {
                        if (is_string($variant) && strpos($variant, 'data:image') === 0) {
                            $newPath = convert_base64_image_to_file($variant, $sku);
                            if ($newPath) {
                                $variant = $newPath;
                                $modified = true;
                            }
                        }
                    }
                    if ($modified) {
                        $newVariantsJson = json_encode($variants);
                        echo "  ✓ variants_json convertida para producto $sku\n";
                    }
                }
            }
            
            // Update database if changes were made
            if ($newImageUrl !== $imageUrl || $newVariantsJson !== $variantsJson) {
                $update = $pdo->prepare("UPDATE products SET image_url = ?, variants_json = ? WHERE id = ?");
                $update->execute([$newImageUrl, $newVariantsJson, $id]);
                $converted++;
            }
        }
        
        echo "  → Procesados: " . count($rows) . " productos, convertidos: $converted\n";
    }
    
    // Process marketplace_ce_products table
    if (db_table_exists('marketplace_ce_products')) {
        echo "\nProcesando tabla 'marketplace_ce_products'...\n";
        
        $stmt = $pdo->query("SELECT id, sku, image_url, variants_json FROM marketplace_ce_products WHERE image_url LIKE 'data:image%' OR variants_json LIKE '%data:image%'");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $converted = 0;
        foreach ($rows as $row) {
            $id = $row['id'];
            $sku = $row['sku'];
            $imageUrl = $row['image_url'];
            $variantsJson = $row['variants_json'];
            
            $newImageUrl = $imageUrl;
            $newVariantsJson = $variantsJson;
            
            // Convert image_url if it's base64
            if (!empty($imageUrl) && strpos($imageUrl, 'data:image') === 0) {
                $newImageUrl = convert_base64_image_to_file($imageUrl, $sku);
                if ($newImageUrl && $newImageUrl !== $imageUrl) {
                    echo "  ✓ image_url convertida para artículo CE $sku\n";
                }
            }
            
            // Convert variants_json if it contains base64
            if (!empty($variantsJson) && strpos($variantsJson, 'data:image') !== false) {
                $variants = json_decode($variantsJson, true);
                if (is_array($variants)) {
                    $modified = false;
                    foreach ($variants as &$variant) {
                        if (is_string($variant) && strpos($variant, 'data:image') === 0) {
                            $newPath = convert_base64_image_to_file($variant, $sku);
                            if ($newPath) {
                                $variant = $newPath;
                                $modified = true;
                            }
                        }
                    }
                    if ($modified) {
                        $newVariantsJson = json_encode($variants);
                        echo "  ✓ variants_json convertida para artículo CE $sku\n";
                    }
                }
            }
            
            // Update database if changes were made
            if ($newImageUrl !== $imageUrl || $newVariantsJson !== $variantsJson) {
                $update = $pdo->prepare("UPDATE marketplace_ce_products SET image_url = ?, variants_json = ? WHERE id = ?");
                $update->execute([$newImageUrl, $newVariantsJson, $id]);
                $converted++;
            }
        }
        
        echo "  → Procesados: " . count($rows) . " artículos CE, convertidos: $converted\n";
    }
    
    echo "\n✓ Conversión completada\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

function convert_base64_image_to_file($dataUri, $sku) {
    // Parse data URI
    if (strpos($dataUri, 'data:image/') !== 0) {
        return null;
    }
    
    // Extract MIME type and base64 data
    preg_match('/^data:image\/(\w+);base64,(.+)$/', $dataUri, $matches);
    if (empty($matches[2])) {
        return null;
    }
    
    $ext = $matches[1];
    $base64Data = $matches[2];
    
    // Decode base64
    $imageData = base64_decode($base64Data, true);
    if ($imageData === false) {
        return null;
    }
    
    // Create directory for SKU if needed
    if (is_valid_numeric_sku_admin_supply($sku)) {
        $galleryDir = __DIR__ . '/images/products/by_code/' . $sku;
    } else {
        $galleryDir = __DIR__ . '/images/products';
    }
    
    if (!is_dir($galleryDir)) {
        mkdir($galleryDir, 0755, true);
    }
    
    // Generate filename
    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filepath = $galleryDir . '/' . $filename;
    
    // Save file
    if (file_put_contents($filepath, $imageData) === false) {
        return null;
    }
    
    // Return web path
    if (is_valid_numeric_sku_admin_supply($sku)) {
        return 'images/products/by_code/' . $sku . '/' . $filename;
    } else {
        return 'images/products/' . $filename;
    }
}

function is_valid_numeric_sku_admin_supply(string $sku): bool {
    return (bool)preg_match('/^\d{5}$/', $sku);
}
?>
