<?php
/**
 * API de Traza de Auditoría / Log de Cambios de Pedido
 * Truper Platform
 */
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$folio = sanitize($_GET['folio'] ?? '');
if (empty($folio)) {
    echo json_encode(['success' => false, 'message' => 'Folio requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT status, notes, changed_by, created_at FROM order_tracking_history WHERE order_folio = ? ORDER BY id DESC");
    $stmt->execute([$folio]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'logs' => $logs]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al obtener traza de auditoría']);
}
?>
