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

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }

} catch (Exception $e) {
    error_log("Tasks API Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
?>
