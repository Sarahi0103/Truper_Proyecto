<?php
$file = __DIR__ . '/../public/api/admin_supply.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

echo "Searching for marketplace-sku-check...\n";
foreach ($lines as $i => $line) {
    if (preg_match('/marketplace-sku-check/i', $line)) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
        search_around_line($lines, $i + 1);
    }
}

function search_around_line($lines, $lineNum) {
    echo "=== Around line $lineNum ===\n";
    $start = max(0, $lineNum - 1);
    $end = min(count($lines), $lineNum + 40);
    for ($i = $start; $i < $end; $i++) {
        echo ($i + 1) . ": " . $lines[$i] . "\n";
    }
}
