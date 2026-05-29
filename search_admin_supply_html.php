<?php
$file = 'public/admin_supply.php';
$contents = file_get_contents($file);
$lines = explode("\n", $contents);
foreach ($lines as $i => $line) {
    if (stripos($line, 'refreshCategoriesUi') !== false || stripos($line, 'categoriesList') !== false) {
        echo ($i + 1) . ": " . trim($line) . "\n";
    }
}
