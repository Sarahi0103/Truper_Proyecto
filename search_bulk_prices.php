<?php
$file = 'public/admin_supply.php';
$contents = file_get_contents($file);
$lines = explode("\n", $contents);
foreach ($lines as $i => $line) {
    if (stripos($line, 'Excluir productos') !== false || stripos($line, 'bulk-price') !== false || stripos($line, 'price-adjust') !== false || stripos($line, 'Ajuste de Precios Masivo') !== false) {
        echo ($i + 1) . ": " . trim($line) . "\n";
    }
}
