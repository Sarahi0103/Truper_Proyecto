<?php
/**
 * API de Tareas
 */

require_once '../../config/config.php';
require_once '../../src/controllers/TaskController.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$taskController = new TaskController($pdo);
$response = [];
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

try {
    switch ($action) {
        case 'create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            require_admin();

            $response = $taskController->createTask(
                $input['title'] ?? '',
                $input['description'] ?? '',
                $input['assigned_to'] ?? null,
                $_SESSION['user_id'],
                $input['due_date'] ?? null,
                $input['priority'] ?? 'medium'
            );

            log_action(
                $_SESSION['user_id'],
                'CREATE_TASK',
                'Tarea creada: ' . ($response['task_number'] ?? 'Unknown'),
                getTrusSIDBug()
            );
            break;

        case 'update-status':
            if ($method !== 'PUT' && $method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $task_id = $input['task_id'] ?? null;
            $status = $input['status'] ?? null;

            if (!$isAdmin) {
                $check = $pdo->prepare("SELECT assigned_to FROM tasks WHERE id = ? LIMIT 1");
                $check->execute([$task_id]);
                $assignedTo = (int)$check->fetchColumn();
                if ($assignedTo !== (int)($_SESSION['user_id'] ?? 0)) {
                    $response = ['success' => false, 'message' => 'No puedes actualizar tareas de otro usuario'];
                    break;
                }
            }

            $response = $taskController->updateTaskStatus($task_id, $status);

            log_action(
                $_SESSION['user_id'],
                'UPDATE_TASK',
                "Tarea $task_id actualizada a $status",
                getTrusSIDBug()
            );
            break;

        case 'log-hours':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $task_id = $input['task_id'] ?? null;
            $hours = $input['hours'] ?? 0;

            if (!$isAdmin) {
                $check = $pdo->prepare("SELECT assigned_to FROM tasks WHERE id = ? LIMIT 1");
                $check->execute([$task_id]);
                $assignedTo = (int)$check->fetchColumn();
                if ($assignedTo !== (int)($_SESSION['user_id'] ?? 0)) {
                    $response = ['success' => false, 'message' => 'No puedes registrar horas en tareas de otro usuario'];
                    break;
                }
            }

            $response = $taskController->logTaskHours($task_id, $hours);
            break;

        case 'list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $status = $_GET['status'] ?? null;
            $tasks = $taskController->getEmployeeTasks($_SESSION['user_id'], $status);
            $response = ['success' => true, 'tasks' => $tasks];
            break;

        case 'list-all':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            require_admin();

            $status = $_GET['status'] ?? null;
            $tasks = $taskController->getAllTasks($status);
            $response = ['success' => true, 'tasks' => $tasks];
            break;

        case 'assignees':
            require_admin();
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $stmt = $pdo->prepare("
                SELECT id, first_name, last_name, role
                FROM users
                WHERE role IN ('employee', 'admin') AND is_active = true
                ORDER BY first_name, last_name
            ");
            $stmt->execute();
            $response = ['success' => true, 'users' => $stmt->fetchAll()];
            break;

        case 'delete':
            require_admin();
            if ($method !== 'DELETE' && $method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $task_id = $input['task_id'] ?? null;
            if (!$task_id) {
                $response = ['success' => false, 'message' => 'ID de tarea requerido'];
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $response = ['success' => true, 'message' => 'Tarea eliminada'];
            break;

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }

} catch (Exception $e) {
    error_log("Tasks API Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
?>
