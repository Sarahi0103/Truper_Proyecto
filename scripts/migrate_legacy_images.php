<?php
/**
 * CLI script to migrate legacy product images into canonical
 * public/images/products/gallery/{sku}/ and persist DB references
 *
 * Usage:
 *   php scripts/migrate_legacy_images.php
 */

chdir(__DIR__ . '/../');

require_once __DIR__ . '/../config/database.php'; // returns $pdo

if (!isset($pdo) || !$pdo instanceof PDO) {
    fwrite(STDERR, "No se pudo obtener PDO desde config/database.php\n");
    exit(2);
}

$root = realpath(__DIR__ . '/../');
$imagesRoot = $root . '/public/images/products';

function normalize_sku_cli(string $value): string {
    $sku = preg_replace('/\D+/', '', $value);
    return substr($sku, 0, 6);
}

function is_valid_numeric_sku_cli(string $sku): bool {
    return (bool)preg_match('/^\d{5,6}$/', $sku);
}

function canonical_relative_cli(string $sku, string $imagePath): string {
    $sku = normalize_sku_cli($sku);
    $raw = trim((string)$imagePath);
    $relative = ltrim($raw, '/');
    $filename = basename($relative);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return $raw;
    }
    return 'images/products/gallery/' . $sku . '/' . $filename;
}

function ensure_canonical_cli(string $root, string $sku, string $raw): ?string {
    $sku = normalize_sku_cli($sku);
    if (!is_valid_numeric_sku_cli($sku) || trim($raw) === '') return null;

    $canonicalRelative = canonical_relative_cli($sku, $raw);
    $source = $root . '/' . ltrim($raw, '/');
    if (!is_file($source)) {
        // try if raw is already relative to public/
        $alt = $root . '/public/' . ltrim($raw, '/');
        if (is_file($alt)) $source = $alt; else return null;
    }

    $canonicalPath = $root . '/' . ltrim($canonicalRelative, '/');
    $canonicalDir = dirname($canonicalPath);
    if (!is_dir($canonicalDir)) {
        @mkdir($canonicalDir, 0775, true);
    }

    if (!is_file($canonicalPath)) {
        if (!@copy($source, $canonicalPath)) {
            return null;
        }
    }

    return (is_file($canonicalPath) ? $canonicalRelative : null);
}

// collect candidate SKUs from disk directories
$skus = [];
$byCodeDir = $imagesRoot . '/by_code';
$galleryDir = $imagesRoot . '/gallery';

foreach ([$byCodeDir, $galleryDir] as $dir) {
    if (!is_dir($dir)) continue;
    $it = new DirectoryIterator($dir);
    foreach ($it as $entry) {
        if ($entry->isDot()) continue;
        if ($entry->isDir()) {
            $sku = normalize_sku_cli($entry->getFilename());
            if (is_valid_numeric_sku_cli($sku)) $skus[$sku] = true;
        }
    }
}

// also collect SKUs from DB rows (products and marketplace_ce_products)
try {
    foreach (['products', 'marketplace_ce_products'] as $table) {
        try {
            $stmt = $pdo->query("SELECT sku FROM {$table}");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach ($rows as $r) {
                $sku = normalize_sku_cli((string)$r);
                if (is_valid_numeric_sku_cli($sku)) $skus[$sku] = true;
            }
        } catch (Exception $e) {
            // table may not exist, skip
        }
    }
} catch (Exception $e) {}

$skus = array_keys($skus);
sort($skus);

if (empty($skus)) {
    echo "No se encontraron SKUs para procesar.\n";
    exit(0);
}

$report = [
    'skus_processed' => 0,
    'images_copied' => 0,
    'db_rows_updated' => 0,
    'missing_files' => []
];

foreach ($skus as $sku) {
    echo "Procesando SKU: {$sku}\n";
    $report['skus_processed']++;

    $foundImages = [];

    // disk: by_code/{sku} and gallery/{sku}
    foreach ([$byCodeDir . '/' . $sku, $galleryDir . '/' . $sku] as $dir) {
        if (!is_dir($dir)) continue;
        $it = new DirectoryIterator($dir);
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) continue;
            $relative = 'images/products/' . (strpos($dir, '/by_code/') !== false ? 'by_code' : 'gallery') . '/' . $sku . '/' . $file->getFilename();
            $canon = ensure_canonical_cli($root, $sku, $relative);
            if ($canon !== null) {
                $foundImages[] = $canon;
                $report['images_copied']++;
            } else {
                $report['missing_files'][] = $relative;
            }
        }
    }

    // DB references: scan products & marketplace_ce_products image_url + variants_json
    foreach (['products', 'marketplace_ce_products'] as $table) {
        try {
            $stmt = $pdo->prepare("SELECT image_url, variants_json FROM {$table} WHERE sku = ? OR sku LIKE ?");
            $stmt->execute([$sku, "%{$sku}%"]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if (!empty($row['image_url'])) {
                    $v = trim((string)$row['image_url']);
                    $canon = ensure_canonical_cli($root, $sku, $v);
                    if ($canon !== null && !in_array($canon, $foundImages, true)) $foundImages[] = $canon;
                }
                if (!empty($row['variants_json'])) {
                    $decoded = json_decode($row['variants_json'], true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $item) {
                            $v = trim((string)$item);
                            $canon = ensure_canonical_cli($root, $sku, $v);
                            if ($canon !== null && !in_array($canon, $foundImages, true)) $foundImages[] = $canon;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // ignore
        }
    }

    $foundImages = array_values(array_unique($foundImages));
    if (empty($foundImages)) {
        echo "  Ninguna imagen encontrada para SKU {$sku}\n";
        continue;
    }

    // Persist: cover = first
    $cover = $foundImages[0];
    $json = json_encode($foundImages, JSON_UNESCAPED_UNICODE);

    foreach (['products', 'marketplace_ce_products'] as $table) {
        try {
            // attempt update; if table/cols missing, skip
            $stmt = $pdo->prepare("UPDATE {$table} SET image_url = ?, variants_json = ? WHERE sku = ? OR sku LIKE ?");
            $stmt->execute([$cover, $json, $sku, "%{$sku}%"]);
            $report['db_rows_updated'] += $stmt->rowCount();
        } catch (Exception $e) {
            // skip
        }
    }

    echo "  Imagenes canonizadas: " . count($foundImages) . " (cover: {$cover})\n";
}

echo "\nResumen:\n";
echo "  SKUs procesados: " . $report['skus_processed'] . "\n";
echo "  Imágenes copiadas/registradas: " . $report['images_copied'] . "\n";
echo "  Filas DB actualizadas: " . $report['db_rows_updated'] . "\n";
if (!empty($report['missing_files'])) {
    echo "  Archivos no encontrados: " . count($report['missing_files']) . " (lista abajo)\n";
    foreach ($report['missing_files'] as $m) echo "    - {$m}\n";
}

file_put_contents(__DIR__ . '/../migration_report_images.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Reporte guardado en migration_report_images.json\n";

exit(0);

?>
