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

// CSRF para operaciones de escritura de tareas
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_csrf_token();
}

$rawInput = file_get_contents('php://input');
$decodedInput = json_decode($rawInput, true);
$input = is_array($decodedInput) ? $decodedInput : (is_array($_POST) ? $_POST : []);

$taskController = new TaskController($pdo);
$response = [];
$isAdmin = (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'employee');

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
                    $input['priority'] ?? 'medium',
                    $input['estimated_hours'] ?? null,
                    $input['estimated_ampm'] ?? 'AM'
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
            $actual_ampm = $input['actual_ampm'] ?? 'AM';

            if (!$isAdmin) {
                $check = $pdo->prepare("SELECT assigned_to FROM tasks WHERE id = ? LIMIT 1");
                $check->execute([$task_id]);
                $assignedTo = (int)$check->fetchColumn();
                if ($assignedTo !== (int)($_SESSION['user_id'] ?? 0)) {
                    $response = ['success' => false, 'message' => 'No puedes registrar horas en tareas de otro usuario'];
                    break;
                }
            }

            $response = $taskController->logTaskHours($task_id, $hours, $actual_ampm);
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

                $queries = [
                    "SELECT id, COALESCE(first_name, '') AS first_name, COALESCE(last_name, '') AS last_name, role FROM users WHERE role IN ('employee', 'admin') AND is_active = true ORDER BY first_name, last_name",
                    "SELECT id, COALESCE(first_name, '') AS first_name, COALESCE(last_name, '') AS last_name, role FROM users WHERE role IN ('employee', 'admin') AND active = 1 ORDER BY first_name, last_name",
                    "SELECT id, COALESCE(first_name, '') AS first_name, COALESCE(last_name, '') AS last_name, role FROM users WHERE role IN ('employee', 'admin') ORDER BY first_name, last_name"
                ];

                $users = [];
                foreach ($queries as $sql) {
                    try {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute();
                        $users = $stmt->fetchAll();
                        if (is_array($users)) {
                            break;
                        }
                    } catch (Exception $ignored) {
                        $users = [];
                    }
                }

                $response = ['success' => true, 'users' => $users];
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

        case 'kanban-update':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $task_id = (int)($input['task_id'] ?? 0);
            $column = sanitize($input['column'] ?? 'todo');
            $position = (int)($input['position'] ?? 0);

            if ($task_id <= 0) {
                $response = ['success' => false, 'message' => 'ID de tarea inválido'];
                break;
            }

            $validColumns = ['todo', 'in_progress', 'review', 'done'];
            if (!in_array($column, $validColumns)) {
                $response = ['success' => false, 'message' => 'Columna inválida'];
                break;
            }

            try {
                // Usar función de reordenamiento Kanban
                $stmt = $pdo->prepare("SELECT reorder_kanban_tasks(?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $column, $task_id, $position]);
                
                $response = ['success' => true, 'message' => 'Tarea movida en Kanban'];
            } catch (Exception $e) {
                // Fallback si la función no existe
                $stmt = $pdo->prepare("UPDATE tasks SET kanban_column = ?, position_order = ? WHERE id = ?");
                $stmt->execute([$column, $position, $task_id]);
                $response = ['success' => true, 'message' => 'Tarea movida en Kanban'];
            }
            break;

        case 'add-dependency':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            require_admin();

            $task_id = (int)($input['task_id'] ?? 0);
            $depends_on = (int)($input['depends_on'] ?? 0);
            $dependency_type = sanitize($input['dependency_type'] ?? 'finish_to_start');

            if ($task_id <= 0 || $depends_on <= 0) {
                $response = ['success' => false, 'message' => 'IDs de tareas inválidos'];
                break;
            }

            if ($task_id === $depends_on) {
                $response = ['success' => false, 'message' => 'Una tarea no puede depender de sí misma'];
                break;
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO task_dependencies (task_id, depends_on_task_id, dependency_type) VALUES (?, ?, ?) ON CONFLICT (task_id, depends_on_task_id) DO UPDATE SET dependency_type = ?");
                $stmt->execute([$task_id, $depends_on, $dependency_type, $dependency_type]);
                $response = ['success' => true, 'message' => 'Dependencia agregada'];
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Error al agregar dependencia: ' . $e->getMessage()];
            }
            break;

        case 'remove-dependency':
            if ($method !== 'POST' && $method !== 'DELETE') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            require_admin();

            $task_id = (int)($input['task_id'] ?? 0);
            $depends_on = (int)($input['depends_on'] ?? 0);

            if ($task_id <= 0 || $depends_on <= 0) {
                $response = ['success' => false, 'message' => 'IDs de tareas inválidos'];
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM task_dependencies WHERE task_id = ? AND depends_on_task_id = ?");
            $stmt->execute([$task_id, $depends_on]);
            $response = ['success' => true, 'message' => 'Dependencia eliminada'];
            break;

        case 'add-reminder':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $task_id = (int)($input['task_id'] ?? 0);
            $reminder_time = $input['reminder_time'] ?? null;
            $reminder_type = sanitize($input['reminder_type'] ?? 'email');

            if ($task_id <= 0 || !$reminder_time) {
                $response = ['success' => false, 'message' => 'Datos incompletos'];
                break;
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO task_reminders (task_id, reminder_time, reminder_type) VALUES (?, ?, ?)");
                $stmt->execute([$task_id, $reminder_time, $reminder_type]);
                
                // Actualizar tarea con tiempo de recordatorio
                $pdo->prepare("UPDATE tasks SET reminder_time = ? WHERE id = ?")->execute([$reminder_time, $task_id]);
                
                $response = ['success' => true, 'message' => 'Recordatorio agregado'];
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Error al agregar recordatorio: ' . $e->getMessage()];
            }
            break;

        case 'kanban-list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $userId = $isAdmin ? null : $_SESSION['user_id'];
            
            if ($isAdmin) {
                $stmt = $pdo->prepare("SELECT * FROM tasks ORDER BY kanban_column, position_order, due_date");
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare("SELECT * FROM tasks WHERE assigned_to = ? ORDER BY kanban_column, position_order, due_date");
                $stmt->execute([$userId]);
            }
            
            $tasks = $stmt->fetchAll();
            
            // Organizar por columnas Kanban
            $kanban = [
                'todo' => [],
                'in_progress' => [],
                'review' => [],
                'done' => []
            ];
            
            foreach ($tasks as $task) {
                $column = $task['kanban_column'] ?? 'todo';
                if (!isset($kanban[$column])) {
                    $column = 'todo';
                }
                $kanban[$column][] = $task;
            }
            
            $response = ['success' => true, 'kanban' => $kanban];
            break;

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }

} catch (Exception $e) {
    error_log("Tasks API Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
    if (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'employee') {
        $response['debug'] = [
            'action' => (string)$action,
            'detail' => (string)$e->getMessage()
        ];
    }
}

echo json_encode($response);
?>
