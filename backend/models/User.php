<?php
/**
 * Modelo de Usuario - Truper
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

class User {
    private $conn;
    private $table = 'users';

    public function __construct() {
        $this->conn = $GLOBALS['db'];
    }

    /**
     * Registrar usuario
     */
    public function register($email, $password, $name, $phone, $role = 'client') {
        $email = Security::sanitize($email);
        
        // Validar email
        if (!Security::validateEmail($email)) {
            return ['success' => false, 'message' => 'Email invÃ¡lido'];
        }

        // Verificar si existe
        $query = "SELECT id FROM {$this->table} WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'El email ya estÃ¡ registrado'];
        }

        // Hash de contraseÃ±a
        $hashed_password = Security::hashPassword($password);
        $name = Security::sanitize($name);
        $phone = Security::sanitize($phone);

        // Insertar usuario
        $query = "INSERT INTO {$this->table} (email, password, name, phone, role, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sssss", $email, $hashed_password, $name, $phone, $role);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Registro exitoso', 'user_id' => $stmt->insert_id];
        }
        
        return ['success' => false, 'message' => 'Error al registrar'];
    }

    /**
     * Login
     */
    public function login($email, $password) {
        $email = Security::sanitize($email);
        
        $query = "SELECT id, email, password, name, role FROM {$this->table} WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Email o contraseÃ±a incorrectos'];
        }

        $user = $result->fetch_assoc();

        if (!Security::verifyPassword($password, $user['password'])) {
            return ['success' => false, 'message' => 'Email o contraseÃ±a incorrectos'];
        }

        // Crear sesiÃ³n
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        return ['success' => true, 'message' => 'Login exitoso', 'user' => $user];
    }

    /**
     * Obtener usuario por ID
     */
    public function getById($id) {
        $query = "SELECT id, email, name, phone, role, birthday, points, created_at FROM {$this->table} WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Obtener hash de contraseÃ±a por ID
     */
    public function getPasswordHashById($id) {
        $query = "SELECT password FROM {$this->table} WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['password'] ?? null;
    }

    /**
     * Actualizar perfil
     */
    public function updateProfile($id, $name, $phone, $birthday) {
        $name = Security::sanitize($name);
        $phone = Security::sanitize($phone);
        $birthday = Security::sanitize($birthday);

        $query = "UPDATE {$this->table} SET name = ?, phone = ?, birthday = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sssi", $name, $phone, $birthday, $id);
        
        return $stmt->execute();
    }

    /**
     * Agregar puntos
     */
    public function addPoints($id, $points) {
        $query = "UPDATE {$this->table} SET points = points + ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $points, $id);
        
        return $stmt->execute();
    }

    /**
     * Obtener todos los usuarios
     */
    public function getAll($role = null) {
        if ($role) {
            $query = "SELECT id, email, name, phone, role, points, created_at FROM {$this->table} WHERE role = ? ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $role);
            $stmt->execute();
        } else {
            $query = "SELECT id, email, name, phone, role, points, created_at FROM {$this->table} ORDER BY created_at DESC";
            $result = $this->conn->query($query);
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>


