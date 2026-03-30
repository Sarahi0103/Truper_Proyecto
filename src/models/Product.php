<?php
/**
 * Modelo de Producto
 */

class Product {
    private $pdo;
    private $table = 'products';

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll($limit = 100) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table} 
            WHERE is_active = true 
            ORDER BY name 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ? AND is_active = true");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getByBarcode($barcode) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE barcode = ? AND is_active = true");
        $stmt->execute([$barcode]);
        return $stmt->fetch();
    }

    public function getBySku($sku) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE sku = ? AND is_active = true");
        $stmt->execute([$sku]);
        return $stmt->fetch();
    }

    public function search($term) {
        $search = "%$term%";
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table} 
            WHERE is_active = true 
            AND (name ILIKE ? OR sku ILIKE ? OR barcode ILIKE ?)
            ORDER BY name
        ");
        $stmt->execute([$search, $search, $search]);
        return $stmt->fetchAll();
    }

    public function create($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} 
            (sku, name, description, category, unit_price, barcode, reorder_level)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $data['sku'],
            $data['name'],
            $data['description'] ?? null,
            $data['category'] ?? null,
            $data['unit_price'],
            $data['barcode'] ?? null,
            $data['reorder_level'] ?? 10
        ]);
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

    public function getByCategory($category) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table} 
            WHERE category = ? AND is_active = true 
            ORDER BY name
        ");
        $stmt->execute([$category]);
        return $stmt->fetchAll();
    }
}
?>
