<?php
/**
 * API Administrador: Gestión de Respaldos de Base de Datos y Certificados SAT CSD
 * Truper / Ferretería FOX Platform
 */

header('Content-Type: application/json; charset=utf-8');
define('BACKUP_ALLOWED', true);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/utils/AppLogger.php';
require_once __DIR__ . '/../../scripts/backup_database_sat.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userRole = $_SESSION['role'] ?? '';
$isLogged = isset($_SESSION['user_id']);
$isAdmin = ($userRole === 'admin');

if (!$isLogged || !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso restringido. Se requieren permisos de Administrador Principal.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    global $pdo;
    $backupDir = __DIR__ . '/../../backups';

    switch ($action) {
        case 'run':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                break;
            }
            require_csrf_token();
            $result = runDatabaseSatBackup($pdo);
            echo json_encode($result);
            break;

        case 'list':
            $files = glob("{$backupDir}/backup_truper_sat_*.json");
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            $backups = [];
            foreach ($files as $file) {
                $basename = basename($file);
                $backups[] = [
                    'filename' => $basename,
                    'filesize_kb' => round(filesize($file) / 1024, 2),
                    'created_at' => date('c', filemtime($file)),
                    'date_formatted' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }

            echo json_encode([
                'success' => true,
                'count' => count($backups),
                'backups' => $backups
            ]);
            break;

        case 'download':
            $filename = basename($_GET['file'] ?? '');
            $filePath = "{$backupDir}/{$filename}";

            if (empty($filename) || !file_exists($filePath) || strpos($filename, 'backup_truper_sat_') !== 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Archivo de respaldo no encontrado.']);
                exit;
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción de respaldo no válida.']);
            break;
    }
} catch (Exception $e) {
    AppLogger::error("Error en API admin_backup: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al procesar respaldo: ' . $e->getMessage()]);
}
