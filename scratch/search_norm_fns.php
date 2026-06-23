<?php
$file = __DIR__ . '/../public/admin_supply.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

echo "Searching for normalizeMarketplaceCode...\n";
foreach ($lines as $i => $line) {
    if (preg_match('/normalizeMarketplaceCode/i', $line)) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}

echo "\nSearching for normalizeNumericSku...\n";
foreach ($lines as $i => $line) {
    if (preg_match('/normalizeNumericSku/i', $line)) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
