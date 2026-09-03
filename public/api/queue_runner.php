<?php
/**
 * API Endpoint para ejecutar tareas de la cola (Cloud Cron / Admin Trigger)
 * Truper Platform
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Queue/QueueWorker.php';

header('Content-Type: application/json');

$cronSecret = getenv('CRON_SECRET') ?: 'truper_cron_secure_key_2026';
$providedSecret = $_GET['secret'] ?? ($_SERVER['HTTP_X_CRON_SECRET'] ?? '');

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isSecretValid = !empty($providedSecret) && hash_equals($cronSecret, $providedSecret);

if (!$isAdmin && !$isSecretValid) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$worker = new QueueWorker($pdo, false, 25);
ob_start();
$processed = $worker->run();
$output = ob_get_clean();

echo json_encode([
    'success' => true,
    'processed_jobs' => $processed,
    'log' => $output,
    'timestamp' => date('c')
]);
