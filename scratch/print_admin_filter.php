<?php
$file = __DIR__ . '/../public/admin_supply.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

echo "=== Lines 6310 to 6390 ===\n";
for ($i = 6309; $i < 6390; $i++) {
    if (isset($lines[$i])) {
        echo ($i + 1) . ": " . $lines[$i] . "\n";
    }
}
