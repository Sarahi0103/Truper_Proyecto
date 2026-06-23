<?php
$file = __DIR__ . '/../public/admin_supply.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

echo "=== Lines 6200 to 6300 ===\n";
for ($i = 6199; $i < 6300; $i++) {
    if (isset($lines[$i])) {
        echo ($i + 1) . ": " . $lines[$i] . "\n";
    }
}
