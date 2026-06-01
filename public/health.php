<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: text/plain; charset=utf-8');

function normalize_sku_admin_supply($value): string {
    $raw = trim((string)$value);
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') return '';
    // Ensure at least 5 digits by left-padding with zeros, and limit to 6 digits.
    if (strlen($digits) < 5) {
        $digits = str_pad($digits, 5, '0', STR_PAD_LEFT);
    }
    if (strlen($digits) > 6) {
        $digits = substr($digits, 0, 6);
    }
    return $digits;
}

$products = [
    ['sku' => '23031', 'name' => 'Parrilla Eléctrica de 1 Quemador Volteck', 'category' => 'Material eléctrico', 'description' => 'Parrilla eléctrica de 1 quemador, cuadrada, Volteck Basic.', 'unit_price' => '135.00', 'stock_quantity' => '50', 'reorder_level' => '10'],
    ['sku' => '23032', 'name' => 'Lámpara LED de 10W Luz Blanca Volteck', 'category' => 'Material eléctrico', 'description' => 'Lámpara LED de 10 watts con luz blanca fría', 'unit_price' => '45.50', 'stock_quantity' => '120', 'reorder_level' => '20'],
];

echo "Simulating product-batch-save:\n";

$processed = 0;
$pdo->beginTransaction();
try {
    foreach ($products as $p) {
        $sku = normalize_sku_admin_supply($p['sku'] ?? '');
        if ($sku === '') {
            echo "SKU is empty for " . $p['name'] . "\n";
            continue;
        }
        $name = trim($p['name'] ?? 'Producto CSV');
        $category = trim($p['category'] ?? 'General');
        $desc = trim($p['description'] ?? '');
        $price = (float)($p['unit_price'] ?? 0);
        $stock = (int)($p['stock_quantity'] ?? 0);
        $reorder = (int)($p['reorder_level'] ?? 10);
        
        echo "Processing SKU: $sku, Name: $name\n";

        $check = $pdo->prepare("SELECT id FROM products WHERE sku = ? OR sku LIKE ?");
        $check->execute([$sku, "%{$sku}%"]);
        $exists = $check->fetchColumn();
        if ($exists) {
            echo "Product exists (ID: $exists). Updating...\n";
            $upd = $pdo->prepare("UPDATE products SET name=?, category=?, description=?, unit_price=?, stock_quantity=?, reorder_level=? WHERE sku = ? OR sku LIKE ?");
            $upd->execute([$name, $category, $desc, $price, $stock, $reorder, $sku, "%{$sku}%"]);
            echo "Updated.\n";
        } else {
            echo "Product does not exist. Inserting...\n";
            $ins = $pdo->prepare("INSERT INTO products (sku, name, category, description, unit_price, stock_quantity, reorder_level, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $ins->execute([$sku, $name, $category, $desc, $price, $stock, $reorder]);
            echo "Inserted.\n";
        }
        $processed++;
    }
    $pdo->commit();
    echo "Transaction committed. Processed: $processed\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Transaction rolled back. Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
