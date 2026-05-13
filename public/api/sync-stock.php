<?php
/**
 * API para sincronizar stock entre Abastecimiento y Catálogo Principal
 * Asegura que el stock sea el mismo en todos lados
 */

require_once '../../config/config.php';
require_admin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'check';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'check':
            /**
             * Verifica discrepancias de stock entre products y marketplace_ce_products
             */
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $stmt = $pdo->query("
                SELECT 
                    p.id,
                    p.sku,
                    p.name,
                    p.stock_quantity as product_stock,
                    COALESCE(m.stock_quantity, 0) as marketplace_stock,
                    CASE WHEN p.stock_quantity != COALESCE(m.stock_quantity, 0) THEN 1 ELSE 0 END as discrepancy
                FROM products p
                LEFT JOIN marketplace_ce_products m ON p.sku = m.sku
                WHERE p.stock_quantity != COALESCE(m.stock_quantity, 0)
                    OR (m.stock_quantity IS NOT NULL AND m.stock_quantity != p.stock_quantity)
                ORDER BY p.id DESC
                LIMIT 500
            ");

            $discrepancies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'total_discrepancies' => count($discrepancies),
                'discrepancies' => $discrepancies,
                'message' => count($discrepancies) === 0 ? 'Todos los stocks coinciden ✅' : 'Se encontraron discrepancias'
            ]);
            break;

        case 'sync':
            /**
             * Sincroniza stock desde products a marketplace_ce_products
             * Si un SKU existe en marketplace_ce_products, actualiza su stock
             */
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $pdo->beginTransaction();

            try {
                // Obtener todos los SKUs de products
                $stmt = $pdo->query("
                    SELECT id, sku, stock_quantity 
                    FROM products 
                    WHERE sku IS NOT NULL AND sku != ''
                ");
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $synced = 0;
                foreach ($products as $product) {
                    // Actualizar stock en marketplace_ce_products si existe
                    $updateStmt = $pdo->prepare("
                        UPDATE marketplace_ce_products 
                        SET stock_quantity = ? 
                        WHERE sku = ? OR sku LIKE ?
                    ");
                    $updateStmt->execute([
                        $product['stock_quantity'],
                        $product['sku'],
                        "%{$product['sku']}%"
                    ]);
                    $synced += $updateStmt->rowCount();
                }

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => "Sincronización completada: {$synced} registros actualizados",
                    'synced_count' => $synced
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'delete-product':
            /**
             * Elimina un producto definitivamente incluyendo todas sus referencias
             */
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $productId = (int)($_POST['id'] ?? 0);
            if ($productId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
                exit;
            }

            $pdo->beginTransaction();

            try {
                // 1. Obtener producto para eliminar imágenes
                $stmt = $pdo->prepare("
                    SELECT id, sku, image_url, variants_json 
                    FROM products 
                    WHERE id = ?
                ");
                $stmt->execute([$productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
                    exit;
                }

                // 2. Eliminar referencias en marketplace_ce_products
                $stmt = $pdo->prepare("
                    DELETE FROM marketplace_ce_products 
                    WHERE sku = ? OR sku LIKE ?
                ");
                $stmt->execute([
                    $product['sku'],
                    "%{$product['sku']}%"
                ]);
                $marketplaceDeleted = $stmt->rowCount();

                // 3. Eliminar producto principal
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$productId]);

                // 4. Eliminar imágenes del disco
                $imagesToDelete = [];
                if (!empty($product['image_url'])) {
                    $imagesToDelete[] = $product['image_url'];
                }
                if (!empty($product['variants_json'])) {
                    $variants = json_decode($product['variants_json'], true) ?: [];
                    foreach ($variants as $img) {
                        $img = trim((string)$img);
                        if ($img !== '') {
                            $imagesToDelete[] = $img;
                        }
                    }
                }

                $deletedImages = 0;
                foreach (array_unique($imagesToDelete) as $imgPath) {
                    if (file_exists($imgPath)) {
                        @unlink($imgPath);
                        $deletedImages++;
                    }
                }

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => "Producto eliminado definitivamente",
                    'product_id' => $productId,
                    'sku' => $product['sku'],
                    'marketplace_records_deleted' => $marketplaceDeleted,
                    'images_deleted' => $deletedImages
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;

        case 'delete-image':
            /**
             * Elimina una imagen definitivamente de un producto
             */
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $productId = (int)($_POST['product_id'] ?? 0);
            $imageUrl = trim($_POST['image_url'] ?? '');

            if ($productId <= 0 || $imageUrl === '') {
                echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
                exit;
            }

            $pdo->beginTransaction();

            try {
                // 1. Remover imagen del producto principal
                $stmt = $pdo->prepare("
                    SELECT variants_json FROM products WHERE id = ?
                ");
                $stmt->execute([$productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $variants = json_decode($product['variants_json'] ?? '[]', true) ?: [];
                    $variants = array_filter($variants, function($v) use ($imageUrl) {
                        return trim((string)$v) !== $imageUrl;
                    });

                    $updateStmt = $pdo->prepare("
                        UPDATE products 
                        SET variants_json = ?,
                            image_url = CASE WHEN image_url = ? THEN NULL ELSE image_url END
                        WHERE id = ?
                    ");
                    $updateStmt->execute([
                        json_encode(array_values($variants), JSON_UNESCAPED_UNICODE),
                        $imageUrl,
                        $productId
                    ]);
                }

                // 2. Remover imagen del marketplace si existe
                $stmt = $pdo->prepare("
                    SELECT id, variants_json FROM marketplace_ce_products WHERE sku IN (
                        SELECT sku FROM products WHERE id = ?
                    )
                ");
                $stmt->execute([$productId]);
                $marketplaceProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($marketplaceProducts as $mp) {
                    $mpVariants = json_decode($mp['variants_json'] ?? '[]', true) ?: [];
                    $mpVariants = array_filter($mpVariants, function($v) use ($imageUrl) {
                        return trim((string)$v) !== $imageUrl;
                    });

                    $mpUpdate = $pdo->prepare("
                        UPDATE marketplace_ce_products 
                        SET variants_json = ?,
                            image_url = CASE WHEN image_url = ? THEN NULL ELSE image_url END
                        WHERE id = ?
                    ");
                    $mpUpdate->execute([
                        json_encode(array_values($mpVariants), JSON_UNESCAPED_UNICODE),
                        $imageUrl,
                        $mp['id']
                    ]);
                }

                // 3. Eliminar archivo del disco
                $deleted = false;
                if (file_exists($imageUrl)) {
                    $deleted = @unlink($imageUrl);
                }

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Imagen eliminada definitivamente',
                    'image_url' => $imageUrl,
                    'file_deleted' => $deleted,
                    'marketplace_records_updated' => count($marketplaceProducts)
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
