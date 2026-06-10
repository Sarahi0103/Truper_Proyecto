<?php
/**
 * Servicio de caché para productos
 * Implementa caché APCu para catálogo de productos
 */

class ProductCacheService {
    private $ttl = 600; // 10 minutos en segundos

    /**
     * Obtener productos en caché
     */
    public function getProducts($cacheKey = 'products_all') {
        $cached = apcu_fetch($cacheKey, $success);
        if ($success) {
            return $cached;
        }
        return null;
    }

    /**
     * Guardar productos en caché
     */
    public function setProducts($cacheKey, $products) {
        return apcu_store($cacheKey, $products, $this->ttl);
    }

    /**
     * Invalidar caché de productos
     */
    public function invalidateProducts($cacheKey = 'products_all') {
        return apcu_delete($cacheKey);
    }

    /**
     * Invalidar todo el caché de productos
     */
    public function invalidateAll() {
        $iterator = new APCUIterator('/^products_/');
        apcu_delete($iterator);
        return true;
    }

    /**
     * Generar clave de caché basada en filtros
     */
    public function generateCacheKey($filters = []) {
        $key = 'products_';
        if (!empty($filters['category'])) {
            $key .= 'cat_' . $filters['category'] . '_';
        }
        if (!empty($filters['search'])) {
            $key .= 'search_' . md5($filters['search']) . '_';
        }
        if (!empty($filters['page'])) {
            $key .= 'page_' . $filters['page'] . '_';
        }
        return rtrim($key, '_') ?: 'products_all';
    }
}
