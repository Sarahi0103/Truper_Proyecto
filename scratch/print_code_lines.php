<?php
$content = file_get_contents('public/api/admin_supply.php');
$lines = explode("\n", $content);

// We want to print lines around 4200 to 4250, and 3440 to 3480
echo "=== Lines 4190 - 4250 ===\n";
for ($i = 4190; $i <= 4250; $i++) {
    if (isset($lines[$i - 1])) {
        echo $i . ": " . $lines[$i - 1] . "\n";
    }
}

echo "\n=== Lines 3440 - 3480 ===\n";
for ($i = 3440; $i <= 3480; $i++) {
    if (isset($lines[$i - 1])) {
        echo $i . ": " . $lines[$i - 1] . "\n";
    }
}
?>
