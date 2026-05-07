<?php
// Usage: php scripts/delete_image_cli.php <sku> <image_relative_path>
chdir(__DIR__ . '/../');
require_once __DIR__ . '/../config/database.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    fwrite(STDERR, "No DB available\n");
    exit(2);
}

$sku = $argv[1] ?? '';
$image = $argv[2] ?? '';
if (trim($sku) === '' || trim($image) === '') {
    echo "Usage: php scripts/delete_image_cli.php <sku> <image_relative_path>\n";
    exit(1);
}

function normalize_sku_cli($s){ return substr(preg_replace('/\D+/', '', (string)$s),0,6); }
function is_valid_sku_cli($s){ return (bool)preg_match('/^\d{5,6}$/',$s); }

$skuN = normalize_sku_cli($sku);
if (!is_valid_sku_cli($skuN)) { echo "Invalid SKU\n"; exit(1); }

$rel = ltrim($image, '/');
$filename = basename($rel);

// Delete files (canonical + legacy)
$paths = [
    // current repo public path (what Apache serves)
    __DIR__ . '/../public/' . "images/products/gallery/{$skuN}/{$filename}",
    __DIR__ . '/../public/' . "images/products/by_code/{$skuN}/{$filename}",
    // legacy path used by some scripts (keep for compatibility)
    __DIR__ . '/../' . "images/products/gallery/{$skuN}/{$filename}",
    __DIR__ . '/../' . "images/products/by_code/{$skuN}/{$filename}",
];
foreach ($paths as $p) {
    if (is_file($p)) { @unlink($p); echo "Deleted file: {$p}\n"; } else { echo "Not found: {$p}\n"; }
}

// Remove references from products and marketplace_ce_products
$tables = ['products','marketplace_ce_products'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT id, sku, image_url, variants_json FROM {$table}");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $row) {
            $rowSku = normalize_sku_cli($row['sku'] ?? '');
            if ($rowSku !== $skuN) continue;
            $changed = false;
            $img = trim((string)$row['image_url']);
            $cand1 = $rel;
            $cand2 = 'images/products/gallery/'.$skuN.'/'.$filename;
            $cand3 = 'images/products/by_code/'.$skuN.'/'.$filename;
            $cand4 = 'public/images/products/gallery/'.$skuN.'/'.$filename;
            $cand5 = 'public/images/products/by_code/'.$skuN.'/'.$filename;
            if ($img === $cand1 || $img === $cand2 || $img === $cand3 || $img === $cand4 || $img === $cand5) {
                $img = 'images/products/default-product.svg'; $changed = true;
            }
            $variants = [];
            if (!empty($row['variants_json'])) {
                $dec = json_decode($row['variants_json'], true) ?: [];
                foreach ($dec as $v) {
                    if ($v === $rel || $v === $cand2 || $v === $cand3 || $v === $cand4 || $v === $cand5) { $changed = true; continue; }
                    $variants[] = $v;
                }
            }
            if ($changed) {
                $json = json_encode($variants, JSON_UNESCAPED_UNICODE);
                $upd = $pdo->prepare("UPDATE {$table} SET image_url = ?, variants_json = ? WHERE id = ?");
                $upd->execute([$img, $json, (int)$row['id']]);
                echo "Updated DB row {$table}.id=".(int)$row['id']."\n";
            }
        }
    } catch (Exception $e) { echo "DB error: {$e->getMessage()}\n"; }
}

echo "Done\n";
