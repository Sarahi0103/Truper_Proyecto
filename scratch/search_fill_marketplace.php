<?php
$file = __DIR__ . '/../public/admin_supply.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

foreach ($lines as $i => $line) {
    if (preg_match('/fillMarketplace/i', $line)) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
