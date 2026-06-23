<?php
$file = __DIR__ . '/../public/admin_supply.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

echo "=== Lines 6250 to 6550 ===\n";
for ($i = 6249; $i < 6550; $i++) {
    if (isset($lines[$i])) {
        echo ($i + 1) . ": " . $lines[$i] . "\n";
    }
}
