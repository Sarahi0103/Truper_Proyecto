<?php
/**
 * Compatibilidad de Base de Datos - Truper
 * Reutiliza la conexión PostgreSQL central del proyecto.
 */

require_once __DIR__ . '/../../config/database.php';

class Database {
    private ?PDO $connection = null;

    public function connect() {
        global $pdo;

        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        if (isset($pdo) && $pdo instanceof PDO) {
            $this->connection = $pdo;
            $GLOBALS['db'] = $pdo;
            return $this->connection;
        }

        throw new RuntimeException('No se pudo inicializar la conexión a la base de datos.');
    }

    public function getConnection() {
        return $this->connect();
    }

    public function close() {
        $this->connection = null;
    }
}

$db = new Database();
$GLOBALS['db'] = $db->connect();
?>



