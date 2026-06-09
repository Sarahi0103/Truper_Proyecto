<?php
/**
 * Product Service
 * Lógica de negocio para productos
 */

require_once __DIR__ . '/../Repositories/ProductRepository.php';

class ProductService {
    private $productRepository;

    public function __construct($pdo) {
        $this->productRepository = new ProductRepository($pdo);
    }

    /**
     * Obtiene todos los productos activos
     */
    public function getAllActive($limit = 200) {
        return $this->productRepository->getAllActive($limit);
    }

    /**
     * Obtiene producto por ID
     */
    public function findById($id) {
        return $this->productRepository->findById($id);
    }

    /**
     * Obtiene producto por SKU
     */
    public function findBySku($sku) {
        return $this->productRepository->findBySku($sku);
    }

    /**
     * Busca productos
     */
    public function search($term, $limit = 50) {
        return $this->productRepository->search($term, $limit);
    }

    /**
     * Obtiene productos por categoría
     */
    public function getByCategory($category, $limit = 100) {
        return $this->productRepository->getByCategory($category, $limit);
    }

    /**
     * Obtiene productos con stock bajo
     */
    public function getLowStockProducts() {
        return $this->productRepository->getLowStockProducts();
    }

    /**
     * Crea nuevo producto con validación
     */
    public function create($data) {
        // Validaciones
        if (empty($data['sku'])) {
            throw new Exception('SKU es obligatorio');
        }
        if (empty($data['name'])) {
            throw new Exception('Nombre es obligatorio');
        }
        if ($data['unit_price'] < 0) {
            throw new Exception('Precio no puede ser negativo');
        }
        if ($data['stock_quantity'] < 0) {
            throw new Exception('Stock no puede ser negativo');
        }

        // Verificar si SKU ya existe
        $existing = $this->findBySku($data['sku']);
        if ($existing) {
            throw new Exception('SKU ya existe');
        }

        return $this->productRepository->create($data);
    }

    /**
     * Actualiza producto con validación
     */
    public function update($id, $data) {
        // Validaciones
        if (empty($data['sku'])) {
            throw new Exception('SKU es obligatorio');
        }
        if (empty($data['name'])) {
            throw new Exception('Nombre es obligatorio');
        }
        if ($data['unit_price'] < 0) {
            throw new Exception('Precio no puede ser negativo');
        }
        if ($data['stock_quantity'] < 0) {
            throw new Exception('Stock no puede ser negativo');
        }

        // Verificar si producto existe
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception('Producto no encontrado');
        }

        // Verificar si SKU ya existe en otro producto
        $skuConflict = $this->findBySku($data['sku']);
        if ($skuConflict && $skuConflict['id'] != $id) {
            throw new Exception('SKU ya existe en otro producto');
        }

        return $this->productRepository->update($id, $data);
    }

    /**
     * Elimina producto
     */
    public function delete($id) {
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception('Producto no encontrado');
        }

        return $this->productRepository->delete($id);
    }

    /**
     * Actualiza stock
     */
    public function updateStock($id, $quantity) {
        if ($quantity < 0) {
            throw new Exception('Stock no puede ser negativo');
        }

        return $this->productRepository->updateStock($id, $quantity);
    }

    /**
     * Verifica si stock está bajo
     */
    public function isStockLow($productId) {
        $product = $this->findById($productId);
        if (!$product) {
            return false;
        }

        return $product['stock_quantity'] <= $product['reorder_level'];
    }

    /**
     * Cuenta productos activos
     */
    public function countActive() {
        return $this->productRepository->countActive();
    }
}
