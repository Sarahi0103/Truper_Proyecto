<?php
$file = __DIR__ . '/../public/admin_supply.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

echo "=== Lines 6130 to 6200 ===\n";
for ($i = 6129; $i < 6200; $i++) {
    if (isset($lines[$i])) {
        echo ($i + 1) . ": " . $lines[$i] . "\n";
    }
}
