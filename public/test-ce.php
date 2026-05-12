<?php
// Test file to verify marketplace_ce.php can be served
echo "✅ TEST: Archivo marketplace_ce.php está siendo servido correctamente\n";
echo "Si ves este mensaje, el .htaccess está funcionando.\n";
echo "Hora: " . date('Y-m-d H:i:s') . "\n";
echo "\n=== Marketplace CE products en BD ===\n";
require_once '../config/config.php';
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM marketplace_ce_products WHERE is_active = 1 OR is_active IS NULL");
    $result = $stmt->fetch();
    echo "Total CE products activos: " . $result['total'] . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
