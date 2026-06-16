<?php
$lines = file(__DIR__ . '/../public/admin_supply.php');
foreach ($lines as $i => $line) {
    if (strpos($line, 'Panel') !== false || strpos($line, 'Módulo') !== false || strpos($line, 'admin-hero') !== false) {
        echo ($i + 1) . ': ' . trim($line) . PHP_EOL;
    }
}
