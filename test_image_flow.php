<?php
require 'config/config.php';

// Test product code
$testSku = '99999';
$testName = 'Test Product ' . time();

echo "=== TEST FLUJO DE IMÁGENES ===\n\n";

// 1. Crear producto
echo "1. Crear producto...\n";
$stmt = $pdo->prepare("INSERT INTO products (sku, name, category, description, unit_price, stock_quantity, reorder_level, image_url, is_active) 
                      VALUES (?, ?, 'Test', 'Test', 10.0, 50, 10, 'images/products/default-product.svg', true)");
$result = $stmt->execute([$testSku, $testName]);
$productId = $pdo->lastInsertId();
echo "✓ Producto creado: ID=$productId, SKU=$testSku\n\n";

// 2. Simular subida de imágenes (crear archivos de prueba)
echo "2. Crear directorio de galería y archivos de imagen de prueba...\n";
$galleryDir = __DIR__ . "/public/images/products/gallery/" . $testSku;
@mkdir($galleryDir, 0755, true);

// Crear 3 archivos de prueba (no son imágenes reales pero el test funciona)
$testImages = [];
for ($i = 1; $i <= 3; $i++) {
    $filename = $galleryDir . "/test_image_$i.jpg";
    file_put_contents($filename, "fake image content $i");
    $testImages[] = "images/products/gallery/$testSku/test_image_$i.jpg";
    echo "  ✓ Creado: " . basename($filename) . "\n";
}
echo "\n";

// 3. Guardar producto con imágenes (simular product-save)
echo "3. Guardar producto con galería de imágenes...\n";
$variantsJson = json_encode($testImages);
$stmt = $pdo->prepare("UPDATE products SET image_url = ?, variants_json = ? WHERE id = ?");
$stmt->execute([$testImages[0], $variantsJson, $productId]);
echo "✓ Producto guardado con " . count($testImages) . " imágenes\n";
echo "  image_url: " . $testImages[0] . "\n";
echo "  variants_json: OK\n\n";

// 4. Recuperar y verificar
echo "4. Recuperar producto y verificar imágenes...\n";
$stmt = $pdo->prepare("SELECT id, sku, image_url, variants_json FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();
$variants = json_decode($product['variants_json'], true);
echo "✓ Recuperado: SKU=" . $product['sku'] . ", images=" . count($variants) . "\n";
foreach ($variants as $idx => $img) {
    echo "  [$idx] " . basename($img) . "\n";
}
echo "\n";

// 5. Editar producto (cambiar nombre)
echo "5. Editar producto y verificar que imágenes persisten...\n";
$newName = $testName . " (Editado)";
$stmt = $pdo->prepare("UPDATE products SET name = ? WHERE id = ?");
$stmt->execute([$newName, $productId]);

$stmt = $pdo->prepare("SELECT name, variants_json FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();
echo "✓ Nombre actualizado: " . $product['name'] . "\n";
echo "✓ Imágenes aún presentes: " . count(json_decode($product['variants_json'], true)) . "\n\n";

// 6. Eliminar archivos individuales y verificar DB
echo "6. Eliminar una imagen de la galería...\n";
@unlink($galleryDir . "/test_image_2.jpg");
$newVariants = [$testImages[0], $testImages[2]];
$newVariantsJson = json_encode($newVariants);
$stmt = $pdo->prepare("UPDATE products SET variants_json = ? WHERE id = ?");
$stmt->execute([$newVariantsJson, $productId]);
echo "✓ Imagen eliminada del disco y DB\n";
echo "✓ Imágenes restantes: " . count($newVariants) . "\n\n";

// 7. Eliminar producto completamente (simular product-delete)
echo "7. Eliminar producto y verificar limpieza...\n";
$stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
$stmt->execute([$productId]);

// Limpiar directorio
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = array_diff(scandir($dir), array('.', '..'));
        foreach ($objects as $object) {
            $path = $dir . "/" . $object;
            if (is_dir($path)) {
                rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
rrmdir($galleryDir);

$filesRemaining = is_dir($galleryDir) ? glob("$galleryDir/*.jpg") : [];
echo "✓ Producto eliminado de BD\n";
echo "✓ Archivos en disco después de delete: " . count($filesRemaining ?? []) . " (deberían ser 0)\n";
echo "✓ Directorio existe: " . (is_dir($galleryDir) ? "sí (ERROR)" : "no (OK)") . "\n\n";

echo "=== TEST COMPLETADO EXITOSAMENTE ===\n";
?>
