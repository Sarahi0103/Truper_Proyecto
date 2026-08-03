<?php
/**
 * Script de Degradación Automática por Inactividad B2B (180 Días)
 * Truper Platform - Fase 1
 * Ejecucción programada (Cron Job)
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

try {
    // Select B2B users (contratista, escuela, mayoreo) whose last purchase was more than 180 days ago or never purchased after approval
    $sql = "
        SELECT id, email, first_name, customer_segment, last_purchase_at, b2b_approved_at 
        FROM users 
        WHERE customer_segment IN ('contratista', 'escuela', 'mayoreo')
          AND (
            (last_purchase_at IS NOT NULL AND last_purchase_at < NOW() - INTERVAL '180 days')
            OR
            (last_purchase_at IS NULL AND b2b_approved_at IS NOT NULL AND b2b_approved_at < NOW() - INTERVAL '180 days')
          )
    ";

    $stmt = $pdo->query($sql);
    $degradedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $degradedCount = 0;
    foreach ($degradedUsers as $u) {
        $upd = $pdo->prepare("UPDATE users SET customer_segment = 'menudeo' WHERE id = ?");
        $upd->execute([$u['id']]);

        // Audit log entry
        try {
            $log = $pdo->prepare("INSERT INTO order_tracking_history (order_folio, status, notes, changed_by) VALUES ('SYSTEM-DEGRADE', 'degraded', ?, 'Cron B2B Degradation')");
            $log->execute(["Usuario #{$u['id']} ({$u['email']}) degradado de {$u['customer_segment']} a menudeo por 180 días de inactividad"]);
        } catch (Exception $e) {}

        $degradedCount++;
    }

    echo json_encode([
        'success' => true,
        'message' => "Proceso de degradación ejecutado correctamente. {$degradedCount} cliente(s) B2B actualizados a menudeo por inactividad de 180 días.",
        'degraded_count' => $degradedCount,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al ejecutar degradación B2B: ' . $e->getMessage()
    ]);
}
