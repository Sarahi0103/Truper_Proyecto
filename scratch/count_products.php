<?php
$pdo = require __DIR__ . '/../config/database.php';
try {
    $stmt = $pdo->query('SELECT COUNT(*) FROM products');
    echo "Total Productos: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    echo "Total Usuarios: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pdo->query('SELECT email, role FROM users');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
