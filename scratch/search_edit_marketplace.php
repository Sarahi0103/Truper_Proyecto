<?php
$file = __DIR__ . '/../public/admin_supply.php';
$content = file_get_contents($file);

echo "Searching for edit functions in admin_supply.php...\n";
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (preg_match('/editMarketplace/i', $line)) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
search_around_line($lines, 7256); // Line containing editMarketplaceSelectedItem

function search_around_line($lines, $lineNum) {
    echo "=== Around line $lineNum ===\n";
    $start = max(0, $lineNum - 30);
    $end = min(count($lines), $lineNum + 30);
    for ($i = $start; $i < $end; $i++) {
        echo ($i + 1) . ": " . $lines[$i] . "\n";
    }
}
