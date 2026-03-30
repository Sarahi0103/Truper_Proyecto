<?php
/**
 * Modelo de Usuario
 */

class User {
    private $pdo;
    private $table = 'users';

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function update($id, $data) {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }

        $values[] = $id;

        $sql = "UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($values);
    }

    public function addPoints($user_id, $points) {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table} 
            SET loyalty_points = loyalty_points + ? 
            WHERE id = ?
        ");
        
        return $stmt->execute([$points, $user_id]);
    }

    public function getAllEmployees() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table} 
            WHERE role IN ('employee', 'admin') 
            ORDER BY first_name, last_name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>
