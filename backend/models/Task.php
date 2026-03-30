<?php
/**
 * Modelo de Tareas - Truper
 */

require_once __DIR__ . '/../config/database.php';

class Task {
    private $conn;
    private $table = 'tasks';

    public function __construct() {
        $this->conn = $GLOBALS['db'];
    }

    /**
     * Crear tarea
     */
    public function create($assigned_to, $title, $description, $priority, $due_date, $assigned_by) {
        $query = "INSERT INTO {$this->table} (assigned_to, assigned_by, title, description, priority, due_date, status, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iissis", $assigned_to, $assigned_by, $title, $description, $priority, $due_date);
        
        if ($stmt->execute()) {
            return ['success' => true, 'task_id' => $stmt->insert_id];
        }
        return ['success' => false];
    }

    /**
     * Obtener tareas del empleado
     */
    public function getEmployeeTasks($employee_id) {
        $query = "SELECT t.*, u.name as assigned_by_name, a.name as assigned_to_name 
                  FROM {$this->table} t 
                  JOIN users u ON t.assigned_by = u.id 
                  JOIN users a ON t.assigned_to = a.id 
                  WHERE t.assigned_to = ? AND t.status != 'completed' 
                  ORDER BY t.due_date ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtener todas las tareas
     */
    public function getAll($filter = null) {
        $query = "SELECT t.*, u.name as assigned_by_name, a.name as assigned_to_name 
                  FROM {$this->table} t 
                  JOIN users u ON t.assigned_by = u.id 
                  JOIN users a ON t.assigned_to = a.id";
        
        if ($filter === 'pending') {
            $query .= " WHERE t.status = 'pending'";
        } elseif ($filter === 'in_progress') {
            $query .= " WHERE t.status = 'in_progress'";
        }
        
        $query .= " ORDER BY t.due_date ASC";
        
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Actualizar estado de tarea
     */
    public function updateStatus($task_id, $status) {
        $query = "UPDATE {$this->table} SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $status, $task_id);
        
        return $stmt->execute();
    }

    /**
     * Eliminar tarea
     */
    public function delete($task_id) {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $task_id);
        
        return $stmt->execute();
    }
}
?>


