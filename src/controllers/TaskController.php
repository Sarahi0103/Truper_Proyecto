<?php
/**
 * Controlador de Tareas para Empleados
 */

class TaskController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function createTask($title, $description, $assigned_to, $assigned_by, $due_date, $priority = 'medium') {
        try {
            $task_number = 'TSK-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO tasks (task_number, title, description, assigned_to, assigned_by, due_date, priority, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $stmt->execute([
                $task_number,
                htmlspecialchars($title),
                htmlspecialchars($description),
                $assigned_to,
                $assigned_by,
                $due_date,
                $priority
            ]);
            
            return [
                'success' => true,
                'message' => 'Tarea creada exitosamente',
                'task_id' => $this->pdo->lastInsertId(),
                'task_number' => $task_number
            ];
        } catch (PDOException $e) {
            error_log("Error creando tarea: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al crear la tarea'];
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
