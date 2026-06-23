<?php
$file = __DIR__ . '/../public/admin_supply.php';
$content = file_get_contents($file);

echo "Searching in admin_supply.php...\n";
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (preg_match('/marketplace/i', $line)) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
echo "Search completed.\n";
