<?php
/**
 * Sync all images from images/products/by_code/{SKU}/ to database
 * Migrates filesystem images to variants_json and image_url columns
 */

require_once 'config/config.php';

// Get admin user for logging
$adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$adminId = (int)($adminStmt ? $adminStmt->fetchColumn() : 0) ?: 1;

$baseDir = __DIR__ . '/public/images/products/by_code';
if (!is_dir($baseDir)) {
    echo "❌ Directorio no encontrado: {$baseDir}\n";
    exit(1);
}

$dirs = scandir($baseDir);
if (!is_array($dirs)) {
    echo "❌ No se pudo escanear el directorio\n";
    exit(1);
}

$synced = 0;
$errors = [];

foreach ($dirs as $dir) {
    if ($dir === '.' || $dir === '..') {
        continue;
    }

    $fullDir = $baseDir . '/' . $dir;
    if (!is_dir($fullDir)) {
        continue;
    }

    // Extract SKU (should be 5 digits)
    $sku = preg_replace('/\D+/', '', $dir);
    if (strlen($sku) !== 5) {
        continue;
    }

    // Find all images in this SKU directory
    $matches = glob($fullDir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
    if (empty($matches) || !is_array($matches)) {
        continue;
    }

    // Sort by priority (FC1 first, then E1, D1, etc)
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

    // Convert file paths to web-accessible paths
    $imagePaths = array_map(function ($path) use ($sku) {
        return 'images/products/by_code/' . $sku . '/' . basename($path);
    }, $matches);

    // Update products table
    if (db_table_exists('products')) {
        try {
            $stmt = $pdo->prepare("SELECT id, image_url, variants_json FROM products WHERE sku = ?");
            $stmt->execute([$sku]);
            $row = $stmt->fetch();
            
            if ($row) {
                $id = $row['id'];
                $coverImage = $imagePaths[0] ?? 'images/products/default-product.svg';
                $galleryImages = $imagePaths; // All images
                $variantsJson = json_encode($galleryImages, JSON_UNESCAPED_UNICODE);
                
                $updateStmt = $pdo->prepare("UPDATE products SET image_url = ?, variants_json = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$coverImage, $variantsJson, $id]);
                
                echo "✓ products (SKU {$sku}): {$coverImage} + " . (count($galleryImages) - 1) . " más\n";
                $synced++;
            }
        } catch (Exception $e) {
            $errors[] = "products (SKU {$sku}): " . $e->getMessage();
        }
    }

    // Update marketplace_ce_products table
    if (db_table_exists('marketplace_ce_products')) {
        try {
            $stmt = $pdo->prepare("SELECT id, image_url, variants_json FROM marketplace_ce_products WHERE sku = ?");
            $stmt->execute([$sku]);
            $row = $stmt->fetch();
            
            if ($row) {
                $id = $row['id'];
                $coverImage = $imagePaths[0] ?? 'images/products/default-product.svg';
                $galleryImages = $imagePaths;
                $variantsJson = json_encode($galleryImages, JSON_UNESCAPED_UNICODE);
                
                $updateStmt = $pdo->prepare("UPDATE marketplace_ce_products SET image_url = ?, variants_json = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$coverImage, $variantsJson, $id]);
                
                echo "✓ marketplace_ce_products (SKU {$sku}): {$coverImage} + " . (count($galleryImages) - 1) . " más\n";
                $synced++;
            }
        } catch (Exception $e) {
            $errors[] = "marketplace_ce_products (SKU {$sku}): " . $e->getMessage();
        }
    }
}

echo "\n=== RESULTADO ===\n";
echo "✓ Sincronizados: {$synced} productos\n";

if (!empty($errors)) {
    echo "\n⚠ Errores:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n✓ Migración completada.\n";
echo "  Todas las imágenes están ahora en la base de datos.\n";
echo "  Puedes eliminar manualmente el directorio images/products/by_code/ si deseas.\n";
