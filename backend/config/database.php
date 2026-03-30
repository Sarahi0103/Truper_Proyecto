<?php
/**
 * Configuración de Base de Datos - TRUPPER
 * Sistema de Gestión de Inventario y Ventas
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'trupper_db');
define('DB_TYPE', 'mysql'); // mysql o sqlite

// Para desarrollo
define('APP_ENV', 'development');
define('APP_DEBUG', true);

// Rutas importantes
define('BASE_PATH', dirname(dirname(dirname(__FILE__))));
define('VIEWS_PATH', BASE_PATH . '/views');
define('ASSETS_PATH', BASE_PATH . '/assets');

// Configuración de sesión
define('SESSION_TIMEOUT', 1800); // 30 minutos

// Colores de TRUPPER
define('COLOR_PRIMARY', '#FF8C00'); // Naranja
define('COLOR_SECONDARY', '#000000'); // Negro
define('COLOR_LIGHT', '#FFFFFF'); // Blanco

class Database {
    private $connection;
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $db = DB_NAME;

    public function connect() {
        try {
            if (DB_TYPE === 'mysql') {
                $this->connection = new mysqli($this->host, $this->user, $this->pass, $this->db);
                
                if ($this->connection->connect_error) {
                    throw new Exception("Conexión fallida: " . $this->connection->connect_error);
                }
                
                $this->connection->set_charset("utf8mb4");
            }
            return $this->connection;
        } catch (Exception $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    public function getConnection() {
        if (!$this->connection) {
            $this->connect();
        }
        return $this->connection;
    }

    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// Crear conexión global
$db = new Database();
$GLOBALS['db'] = $db->connect();
?>
