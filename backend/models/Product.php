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
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (name, sku, description, cost_price, sell_price, unit, category, created_at) VALUES (:name, :sku, :description, :cost_price, :sell_price, :unit, :category, NOW()) RETURNING id");
        $stmt->execute([
            ':name' => $name,
            ':sku' => $sku,
            ':description' => $description,
            ':cost_price' => $cost_price,
            ':sell_price' => $sell_price,
            ':unit' => $unit,
            ':category' => $category,
        ]);

        $productId = $stmt->fetchColumn();
        if ($productId) {
            return ['success' => true, 'product_id' => (int)$productId];
        }
        return ['success' => false];
    }

    /**
     * Obtener todos los productos
     */
    public function getAll() {
        $stmt = $this->conn->query("SELECT * FROM {$this->table} WHERE COALESCE(active, is_active) = true ORDER BY name ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Obtener por ID
     */
    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id AND COALESCE(active, is_active) = true");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener por SKU
     */
    public function getBySku($sku) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE sku = :sku AND COALESCE(active, is_active) = true");
        $stmt->execute([':sku' => $sku]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
        $needle = '%' . $query . '%';
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE (name ILIKE :needle OR sku ILIKE :needle OR description ILIKE :needle) AND COALESCE(active, is_active) = true");
        $stmt->execute([':needle' => $needle]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener productos por categoría
     */
    public function getByCategory($category) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE category = :category AND COALESCE(active, is_active) = true ORDER BY name ASC");
        $stmt->execute([':category' => $category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualizar producto
     */
    public function update($id, $name, $description, $cost_price, $sell_price, $category) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET name = :name, description = :description, cost_price = :cost_price, sell_price = :sell_price, category = :category WHERE id = :id");
        return $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':cost_price' => $cost_price,
            ':sell_price' => $sell_price,
            ':category' => $category,
            ':id' => $id,
        ]);
    }
}
?>


