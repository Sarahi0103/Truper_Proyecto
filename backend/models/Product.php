<?php
/**
 * Modelo de Productos - Truper
 */

require_once __DIR__ . '/../config/database.php';

class Product {
    private $conn;
    private $table = 'products';

    public function __construct() {
        $this->conn = $GLOBALS['db'];
    }

    /**
     * Crear producto
     */
    public function create($name, $sku, $description, $cost_price, $sell_price, $category, $unit) {
        $query = "INSERT INTO {$this->table} (name, sku, description, cost_price, sell_price, category, unit, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sssddss", $name, $sku, $description, $cost_price, $sell_price, $category, $unit);
        
        if ($stmt->execute()) {
            return ['success' => true, 'product_id' => $stmt->insert_id];
        }
        return ['success' => false];
    }

    /**
     * Obtener todos los productos
     */
    public function getAll() {
        $query = "SELECT * FROM {$this->table} WHERE active = 1 ORDER BY name ASC";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtener por ID
     */
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = ? AND active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Obtener por SKU
     */
    public function getBySku($sku) {
        $query = "SELECT * FROM {$this->table} WHERE sku = ? AND active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $sku);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Calcular precio con margin
     */
    public function calculatePrice($cost_price, $margin_percent = 30) {
        return $cost_price * (1 + ($margin_percent / 100));
    }

    /**
     * Buscar productos
     */
    public function search($query) {
        $query = "%" . $query . "%";
        $sql = "SELECT * FROM {$this->table} WHERE name LIKE ? OR sku LIKE ? OR description LIKE ? AND active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sss", $query, $query, $query);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtener productos por categorÃ­a
     */
    public function getByCategory($category) {
        $query = "SELECT * FROM {$this->table} WHERE category = ? AND active = 1 ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $category);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Actualizar producto
     */
    public function update($id, $name, $description, $cost_price, $sell_price, $category) {
        $query = "UPDATE {$this->table} SET name = ?, description = ?, cost_price = ?, sell_price = ?, category = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssddsi", $name, $description, $cost_price, $sell_price, $category, $id);
        
        return $stmt->execute();
    }
}
?>


