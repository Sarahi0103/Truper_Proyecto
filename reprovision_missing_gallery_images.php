<?php
/**
 * Reprovision missing gallery images referenced in DB.
 * - Busca `image_url` y `variants_json` en `products` y `marketplace_ce_products`.
 * - Para referencias que apunten a imágenes que no existen, crea un placeholder
 *   PNG en `public/images/products/gallery/{sku}/` y `images/products/gallery/{sku}/`.
 * - Actualiza las columnas con la nueva ruta canónica.
 */

require_once __DIR__ . '/config/config.php';

echo "=== REPROVISIONANDO IMÁGENES FALTANTES ===\n\n";

$roots = [
    __DIR__ . '/public/images',
    __DIR__ . '/images'
];

function file_exists_in_roots(array $roots, string $relative): bool {
    $relative = ltrim($relative, '/');
    foreach ($roots as $r) {
        if (is_file($r . '/' . $relative)) return true;
    }
    return false;
}

function write_placeholder_to_roots(array $roots, string $relative, string $base64data): bool {
    $relative = ltrim($relative, '/');
    $ok = true;
    foreach ($roots as $r) {
        $full = $r . '/' . $relative;
        $dir = dirname($full);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $bin = base64_decode($base64data);
        if ($bin === false) {
            $ok = false; continue;
        }
        if (@file_put_contents($full, $bin) === false) {
            $ok = false;
        }
    }
    return $ok;
}

// Tiny transparent PNG (1x1)
$tinyPng = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==';

$tables = ['products', 'marketplace_ce_products'];
$updated = 0;
$skipped = 0;
$errors = 0;

foreach ($tables as $table) {
    try {
        if (!$pdo) break;
        // guard clause if table missing
        $colCheck = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = '" . strtolower($table) . "'");
        if (!$colCheck) continue;
    } catch (Exception $e) {
        continue;
    }

    try {
        $stmt = $pdo->query("SELECT id, sku, image_url, variants_json FROM {$table}");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        continue;
    }

    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        $sku = trim((string)($row['sku'] ?? ''));
        if ($sku === '') { $skipped++; continue; }

        $needsUpdate = false;
        $newImageUrl = trim((string)($row['image_url'] ?? ''));

        // normalize image_url if it points into images/products/gallery/{sku}/...
        if ($newImageUrl !== '' && preg_match('#images/products/gallery/(' . preg_quote($sku, '#') . ')/(.*)$#', $newImageUrl, $m)) {
            if (!file_exists_in_roots($roots, $newImageUrl)) {
                // create new placeholder file
                $filename = time() . '_' . bin2hex(random_bytes(4)) . '.png';
                $rel = 'images/products/gallery/' . $sku . '/' . $filename;
                $wrote = write_placeholder_to_roots($roots, $rel, $tinyPng);
                if ($wrote) {
                    $newImageUrl = $rel;
                    $needsUpdate = true;
                    echo "SKU {$sku} (row {$id}) - image_url faltante, reprovisionada: {$rel}\n";
                } else {
                    echo "SKU {$sku} (row {$id}) - error al escribir placeholder\n";
                    $errors++; continue;
                }
            }
        }

        // variants_json
        $variants = [];
        $origVariants = [];
        if (!empty($row['variants_json'])) {
            $decoded = json_decode((string)$row['variants_json'], true);
            if (is_array($decoded)) {
                $origVariants = $decoded;
                foreach ($decoded as $v) {
                    $v = trim((string)$v);
                    if ($v === '') continue;
                    if (preg_match('#images/products/gallery/(' . preg_quote($sku, '#') . ')/(.*)$#', $v, $mm)) {
                        if (!file_exists_in_roots($roots, $v)) {
                            $filename = time() . '_' . bin2hex(random_bytes(4)) . '.png';
                            $rel = 'images/products/gallery/' . $sku . '/' . $filename;
                            $wrote = write_placeholder_to_roots($roots, $rel, $tinyPng);
                            if ($wrote) {
                                $variants[] = $rel;
                                $needsUpdate = true;
                                echo "SKU {$sku} (row {$id}) - variant faltante reprovisionada: {$rel}\n";
                                continue;
                            } else {
                                echo "SKU {$sku} (row {$id}) - error al escribir placeholder variant\n";
                                $errors++; continue 2;
                            }
                        }
                    }
                    // if exists or not a gallery reference, keep original
                    $variants[] = $v;
                }
            }
        }

        if ($needsUpdate) {
            try {
                $updVariantsJson = json_encode($variants ?: $origVariants, JSON_UNESCAPED_UNICODE);
                $upd = $pdo->prepare("UPDATE {$table} SET image_url = ?, variants_json = ? WHERE id = ?");
                $upd->execute([$newImageUrl, $updVariantsJson, $id]);
                $updated++;
            } catch (Exception $e) {
                echo "SKU {$sku} (row {$id}) - error DB: " . $e->getMessage() . "\n";
                $errors++; continue;
            }
        } else {
            $skipped++;
        }
    }
}

echo "\n=== RESULTADO ===\n";
echo "✅ Actualizados: {$updated}\n";
echo "⏭️  Saltados: {$skipped}\n";
echo "❌ Errores: {$errors}\n";

if ($updated > 0) {
    echo "\n✅ Reprovisionamiento completado. Ejecuta php sync_gallery_to_db.php si necesitas reordenar/normalizar entradas.\n";
}

?>
