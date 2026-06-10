<?php
require_once 'config/config.php';

try {
    $stmt = $pdo->prepare("SELECT routine_name FROM information_schema.routines WHERE routine_name = 'reconcile_cash_drawer'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        echo "La función reconcile_cash_drawer existe en la base de datos\n";
        
        // Probar ejecutar la función
        $stmt = $pdo->prepare("SELECT * FROM reconcile_cash_drawer(0) LIMIT 1");
        $stmt->execute();
        $test = $stmt->fetch();
        echo "La función se ejecuta correctamente\n";
        print_r($test);
    } else {
        echo "La función reconcile_cash_drawer NO existe en la base de datos\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
