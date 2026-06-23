<?php
$file1 = __DIR__ . '/../public/admin_supply.php';
$file2 = __DIR__ . '/../public/api/admin_supply.php';

function search_in_file($filePath, $pattern) {
    $content = file_get_contents($filePath);
    if ($content === false) {
        echo "Could not read $filePath\n";
        return;
    }
    echo "=== Searching in " . basename($filePath) . " for '$pattern' ===\n";
    $lines = explode("\n", $content);
    $found = false;
    foreach ($lines as $i => $line) {
        if (stripos($line, $pattern) !== false) {
            echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
            $found = true;
        }
    }
    if (!$found) {
        echo "No matches found.\n";
    }
}

search_in_file($file1, 'sync_product_to_marketplace_ce');
search_in_file($file2, 'sync_product_to_marketplace_ce');
search_in_file($file2, 'action');
search_in_file($file2, 'marketplace');
