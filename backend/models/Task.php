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
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (assigned_to, assigned_by, title, description, priority, due_date, status, created_at) VALUES (:assigned_to, :assigned_by, :title, :description, :priority, :due_date, 'pending', NOW()) RETURNING id");
        $stmt->execute([
            ':assigned_to' => $assigned_to,
            ':assigned_by' => $assigned_by,
            ':title' => $title,
            ':description' => $description,
            ':priority' => $priority,
            ':due_date' => $due_date,
        ]);

        $taskId = $stmt->fetchColumn();
        if ($taskId) {
            return ['success' => true, 'task_id' => (int)$taskId];
        }
        return ['success' => false];
    }

    /**
     * Obtener tareas del empleado
     */
    public function getEmployeeTasks($employee_id) {
        $stmt = $this->conn->prepare("SELECT t.*, u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END AS assigned_by_name, a.first_name || CASE WHEN a.last_name IS NOT NULL AND a.last_name <> '' THEN ' ' || a.last_name ELSE '' END AS assigned_to_name FROM {$this->table} t JOIN users u ON t.assigned_by = u.id JOIN users a ON t.assigned_to = a.id WHERE t.assigned_to = :employee_id AND t.status <> 'completed' ORDER BY t.due_date ASC");
        $stmt->execute([':employee_id' => $employee_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener todas las tareas
     */
    public function getAll($filter = null) {
        $sql = "SELECT t.*, u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END AS assigned_by_name, a.first_name || CASE WHEN a.last_name IS NOT NULL AND a.last_name <> '' THEN ' ' || a.last_name ELSE '' END AS assigned_to_name FROM {$this->table} t JOIN users u ON t.assigned_by = u.id JOIN users a ON t.assigned_to = a.id";

        if ($filter === 'pending') {
            $sql .= " WHERE t.status = 'pending'";
        } elseif ($filter === 'in_progress') {
            $sql .= " WHERE t.status = 'in_progress'";
        }

        $sql .= " ORDER BY t.due_date ASC";

        $stmt = $this->conn->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Actualizar estado de tarea
     */
    public function updateStatus($task_id, $status) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET status = :status, updated_at = NOW() WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $task_id]);
    }

    /**
     * Eliminar tarea
     */
    public function delete($task_id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute([':id' => $task_id]);
    }
}
?>


