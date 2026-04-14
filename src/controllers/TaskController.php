<?php
/**
 * Controlador de Tareas para Empleados
 */

class TaskController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function createTask($title, $description, $assigned_to, $assigned_by, $due_date, $priority = 'medium', $estimated_hours = null) {
        try {
            $this->ensureTaskSchemaCompatibility();

            $title = trim((string)$title);
            $description = trim((string)$description);
            $assigned_to = (int)$assigned_to;
            $assigned_by = (int)$assigned_by;
            $due_date = trim((string)$due_date);

            if ($title === '' || $description === '' || $assigned_to <= 0 || $assigned_by <= 0 || $due_date === '') {
                return ['success' => false, 'message' => 'Completa los campos obligatorios de la tarea'];
            }

            $allowedPriorities = ['low', 'medium', 'high', 'urgent'];
            $priority = in_array((string)$priority, $allowedPriorities, true) ? (string)$priority : 'medium';
            $task_number = 'TSK-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));

            $columns = [];
            $values = [];
            $params = [];

            if (db_column_exists('tasks', 'task_number')) {
                $columns[] = 'task_number';
                $values[] = '?';
                $params[] = $task_number;
            }

            $columns[] = 'title';
            $values[] = '?';
            $params[] = htmlspecialchars($title);

            $columns[] = 'description';
            $values[] = '?';
            $params[] = htmlspecialchars($description);

            $columns[] = 'assigned_to';
            $values[] = '?';
            $params[] = $assigned_to;

            $columns[] = 'assigned_by';
            $values[] = '?';
            $params[] = $assigned_by;

            if (db_column_exists('tasks', 'due_date')) {
                $columns[] = 'due_date';
                $values[] = '?';
                $params[] = $due_date;
            }

            if (db_column_exists('tasks', 'priority')) {
                $columns[] = 'priority';
                $values[] = '?';
                $params[] = $priority === 'urgent' ? 'high' : $priority;
            }

            if (db_column_exists('tasks', 'estimated_hours')) {
                $columns[] = 'estimated_hours';
                $values[] = '?';
                $params[] = ($estimated_hours !== null && $estimated_hours !== '') ? (float)$estimated_hours : null;
            }

            if (db_column_exists('tasks', 'status')) {
                $columns[] = 'status';
                $values[] = '?';
                $params[] = 'pending';
            }

            $sql = "INSERT INTO tasks (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'message' => 'Tarea creada exitosamente',
                'task_id' => $this->pdo->lastInsertId(),
                'task_number' => $task_number
            ];
        } catch (PDOException $e) {
            error_log("Error creando tarea: " . $e->getMessage());
            return ['success' => false, 'message' => 'No fue posible crear la tarea'];
        }
    }

    private function ensureTaskSchemaCompatibility() {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
            id SERIAL PRIMARY KEY,
            task_number VARCHAR(50) UNIQUE,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            assigned_to INTEGER NOT NULL,
            assigned_by INTEGER NOT NULL,
            priority VARCHAR(20) DEFAULT 'medium',
            status VARCHAR(20) DEFAULT 'pending',
            due_date DATE,
            completion_date TIMESTAMP,
            estimated_hours DECIMAL(5,2),
            actual_hours DECIMAL(5,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS task_number VARCHAR(50)");
        $this->pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS completion_date TIMESTAMP");
        $this->pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS estimated_hours DECIMAL(5,2)");
        $this->pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS actual_hours DECIMAL(5,2)");

        try {
            $this->pdo->exec("ALTER TABLE tasks ADD CONSTRAINT tasks_task_number_key UNIQUE (task_number)");
        } catch (Exception $ignored) {
            // Constraint may already exist.
        }
    }
    
    public function updateTaskStatus($task_id, $status) {
        try {
            $update_data = ['status' => $status];
            
            if ($status === 'completed') {
                $update_data['completion_date'] = date('Y-m-d H:i:s');
            }
            
            $set_clause = implode(', ', array_map(function($key) { return "$key = ?"; }, array_keys($update_data)));
            $values = array_values($update_data);
            $values[] = $task_id;
            
            $stmt = $this->pdo->prepare("UPDATE tasks SET $set_clause WHERE id = ?");
            $stmt->execute($values);
            
            return [
                'success' => true,
                'message' => 'Tarea actualizada exitosamente'
            ];
        } catch (PDOException $e) {
            error_log("Error actualizando tarea: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al actualizar la tarea'];
        }
    }
    
    public function getEmployeeTasks($employee_id, $status = null) {
        try {
            $query = "
                SELECT * FROM tasks 
                WHERE assigned_to = ?
            ";
            
            $params = [$employee_id];
            
            if ($status) {
                $query .= " AND status = ?";
                $params[] = $status;
            }
            
            $query .= " ORDER BY priority DESC, due_date ASC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function getAllTasks($status = null) {
        try {
            $query = "
                SELECT t.*, u.first_name, u.last_name 
                FROM tasks t
                JOIN users u ON t.assigned_to = u.id
            ";
            
            if ($status) {
                $query .= " WHERE t.status = ?";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([$status]);
            } else {
                $stmt = $this->pdo->prepare($query);
                $stmt->execute();
            }
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function logTaskHours($task_id, $hours) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE tasks 
                SET actual_hours = COALESCE(actual_hours, 0) + ? 
                WHERE id = ?
            ");
            
            $stmt->execute([$hours, $task_id]);
            
            return [
                'success' => true,
                'message' => 'Horas registradas exitosamente'
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al registrar horas'];
        }
    }
}
?>
