<?php
$file = __DIR__ . '/../public/admin_supply.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

echo "=== Lines 1715 to 1760 ===\n";
for ($i = 1714; $i < 1760; $i++) {
    if (isset($lines[$i])) {
        echo ($i + 1) . ": " . $lines[$i] . "\n";
    }
}
