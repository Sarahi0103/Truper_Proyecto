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
            return ['success' => false, 'message' => 'Email inválido'];
        }

        // Verificar si existe
        $stmt = $this->conn->prepare("SELECT id FROM {$this->table} WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['success' => false, 'message' => 'El email ya está registrado'];
        }

        // Hash de contraseña
        $hashed_password = Security::hashPassword($password);
        $name = Security::sanitize($name);
        $phone = Security::sanitize($phone);

        // Insertar usuario
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (email, password, name, phone, role, created_at) VALUES (:email, :password, :name, :phone, :role, NOW()) RETURNING id");
        $stmt->execute([
            ':email' => $email,
            ':password' => $hashed_password,
            ':name' => $name,
            ':phone' => $phone,
            ':role' => $role
        ]);

        $userId = $stmt->fetchColumn();
        if ($userId) {
            return ['success' => true, 'message' => 'Registro exitoso', 'user_id' => (int)$userId];
        }
        
        return ['success' => false, 'message' => 'Error al registrar'];
    }

    /**
     * Login
     */
    public function login($email, $password) {
        $email = Security::sanitize($email);
        
        $stmt = $this->conn->prepare("SELECT id, email, password, name, role FROM {$this->table} WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'Email o contraseña incorrectos'];
        }

        if (!Security::verifyPassword($password, $user['password'])) {
            return ['success' => false, 'message' => 'Email o contraseña incorrectos'];
        }

        // Crear sesión
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
        $stmt = $this->conn->prepare("SELECT id, email, first_name || CASE WHEN last_name IS NOT NULL AND last_name <> '' THEN ' ' || last_name ELSE '' END AS name, phone, role, birthdate AS birthday, loyalty_points AS points, created_at FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener hash de contraseña por ID
     */
    public function getPasswordHashById($id) {
        $stmt = $this->conn->prepare("SELECT password FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['password'] ?? null;
    }

    /**
     * Actualizar perfil
     */
    public function updateProfile($id, $name, $phone, $birthday) {
        $name = Security::sanitize($name);
        $phone = Security::sanitize($phone);
        $birthday = Security::sanitize($birthday);

        $parts = preg_split('/\s+/', trim($name), 2);
        $firstName = $parts[0] !== '' ? $parts[0] : $name;
        $lastName = $parts[1] ?? '';

        $stmt = $this->conn->prepare("UPDATE {$this->table} SET first_name = :first_name, last_name = :last_name, phone = :phone, birthdate = :birthdate, updated_at = NOW() WHERE id = :id");
        return $stmt->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':phone' => $phone,
            ':birthdate' => $birthday ?: null,
            ':id' => $id
        ]);
    }

    /**
     * Agregar puntos
     */
    public function addPoints($id, $points) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET loyalty_points = COALESCE(loyalty_points, 0) + :points, updated_at = NOW() WHERE id = :id");
        return $stmt->execute([':points' => $points, ':id' => $id]);
    }

    /**
     * Obtener todos los usuarios
     */
    public function getAll($role = null) {
        if ($role) {
            $stmt = $this->conn->prepare("SELECT id, email, first_name || CASE WHEN last_name IS NOT NULL AND last_name <> '' THEN ' ' || last_name ELSE '' END AS name, phone, role, loyalty_points AS points, created_at FROM {$this->table} WHERE role = :role ORDER BY created_at DESC");
            $stmt->execute([':role' => $role]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->conn->query("SELECT id, email, first_name || CASE WHEN last_name IS NOT NULL AND last_name <> '' THEN ' ' || last_name ELSE '' END AS name, phone, role, loyalty_points AS points, created_at FROM {$this->table} ORDER BY created_at DESC");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        }
    }
}
?>


