<?php
// Ejecuta sincronización desde CLI (sin require_admin)
$pdo = include __DIR__ . '/../config/database.php';
if (!$pdo) { echo json_encode(['success'=>false,'message'=>'No DB']); exit(1); }
try {
    $stmt = $pdo->query("SELECT id, sku, stock_quantity FROM products WHERE sku IS NOT NULL AND sku != ''");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $synced = 0;
    foreach ($products as $product) {
        $updateStmt = $pdo->prepare("UPDATE marketplace_ce_products SET stock_quantity = ? WHERE sku = ? OR sku LIKE ?");
        $updateStmt->execute([$product['stock_quantity'], $product['sku'], "%{$product['sku']}%"]);
        $synced += $updateStmt->rowCount();
    }
    echo json_encode(['success'=>true,'message'=>"Sincronización completada: {$synced} registros actualizados","synced_count"=>$synced]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    exit(1);
}
