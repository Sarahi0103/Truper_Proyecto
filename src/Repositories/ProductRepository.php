<?php
/**
 * Product Repository
 * Maneja operaciones de base de datos para productos
 */

class ProductRepository {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene todos los productos activos
     */
    public function getAllActive($limit = 200) {
        $sql = "SELECT id, name, sku, COALESCE(unit_price, sell_price, 0) AS unit_price, 
                       category, description, technical_specs, stock_quantity, 
                       image_url, variants_json 
                FROM products 
                WHERE is_active = true 
                ORDER BY name 
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene producto por ID
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene producto por SKU
     */
    public function findBySku($sku) {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE sku = ? LIMIT 1");
        $stmt->execute([$sku]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca productos por término
     */
    public function search($term, $limit = 50) {
        $sql = "SELECT * FROM products 
                WHERE (name ILIKE ? OR sku ILIKE ? OR description ILIKE ?)
                AND is_active = true 
                ORDER BY name 
                LIMIT ?";
        
        $searchTerm = "%{$term}%";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene productos por categoría
     */
    public function getByCategory($category, $limit = 100) {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE category = ? AND is_active = true ORDER BY name LIMIT ?");
        $stmt->execute([$category, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene productos con stock bajo
     */
    public function getLowStockProducts() {
        $sql = "SELECT * FROM products 
                WHERE stock_quantity <= reorder_level 
                AND is_active = true 
                ORDER BY stock_quantity ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crea nuevo producto
     */
    public function create($data) {
        $sql = "INSERT INTO products (sku, name, category, description, unit_price, stock_quantity, 
                                        reorder_level, image_url, is_active, variants_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['sku'],
            $data['name'],
            $data['category'],
            $data['description'],
            $data['unit_price'],
            $data['stock_quantity'],
            $data['reorder_level'],
            $data['image_url'],
            $data['is_active'],
            $data['variants_json'] ?? null
        ]);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Actualiza producto
     */
    public function update($id, $data) {
        $sql = "UPDATE products 
                SET sku = ?, name = ?, category = ?, description = ?, 
                    unit_price = ?, stock_quantity = ?, reorder_level = ?, 
                    image_url = ?, is_active = ?, variants_json = ?, updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['sku'],
            $data['name'],
            $data['category'],
            $data['description'],
            $data['unit_price'],
            $data['stock_quantity'],
            $data['reorder_level'],
            $data['image_url'],
            $data['is_active'],
            $data['variants_json'] ?? null,
            $id
        ]);
    }

    /**
     * Elimina producto
     */
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Actualiza stock
     */
    public function updateStock($id, $quantity) {
        $stmt = $this->pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        return $stmt->execute([$quantity, $id]);
    }

    /**
     * Cuenta productos activos
     */
    public function countActive() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE is_active = true");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
