<?php
$file = __DIR__ . '/../public/admin_supply.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

echo "Searching for normalizeCode...\n";
$found = false;
foreach ($lines as $i => $line) {
    if (preg_match('/normalizeCode/i', $line)) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
        $found = true;
    }
}
if (!$found) {
    echo "normalizeCode is NOT found anywhere!\n";
}
