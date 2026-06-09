<?php
/**
 * Sistema de caché de imágenes con APCu
 * Optimiza la carga de imágenes evitando escaneo repetido del filesystem
 */

class ImageCache {
    private static $cacheEnabled = null;
    private static $cacheTTL = 3600; // 1 hora

    /**
     * Verifica si APCu está disponible
     */
    private static function isAPCuAvailable() {
        if (self::$cacheEnabled !== null) {
            return self::$cacheEnabled;
        }

        self::$cacheEnabled = extension_loaded('apcu') && apcu_enabled();
        return self::$cacheEnabled;
    }

    /**
     * Obtiene el índice de imágenes desde caché
     */
    public static function getImageIndex($cacheKey = 'product_images_index') {
        if (!self::isAPCuAvailable()) {
            return null;
        }

        $cached = apcu_fetch($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        return null;
    }

    /**
     * Guarda el índice de imágenes en caché
     */
    public static function setImageIndex($index, $cacheKey = 'product_images_index') {
        if (!self::isAPCuAvailable()) {
            return false;
        }

        return apcu_store($cacheKey, $index, self::$cacheTTL);
    }

    /**
     * Elimina el caché de imágenes
     */
    public static function clearImageCache($cacheKey = 'product_images_index') {
        if (!self::isAPCuAvailable()) {
            return false;
        }

        return apcu_delete($cacheKey);
    }

    /**
     * Obtiene imágenes de un producto específico desde caché
     */
    public static function getProductImages($sku) {
        if (!self::isAPCuAvailable()) {
            return null;
        }

        $cacheKey = 'product_images_' . md5($sku);
        $cached = apcu_fetch($cacheKey);
        
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        return null;
    }

    /**
     * Guarda imágenes de un producto específico en caché
     */
    public static function setProductImages($sku, $images) {
        if (!self::isAPCuAvailable()) {
            return false;
        }

        $cacheKey = 'product_images_' . md5($sku);
        return apcu_store($cacheKey, $images, self::$cacheTTL);
    }

    /**
     * Elimina caché de un producto específico
     */
    public static function clearProductCache($sku) {
        if (!self::isAPCuAvailable()) {
            return false;
        }

        $cacheKey = 'product_images_' . md5($sku);
        return apcu_delete($cacheKey);
    }

    /**
     * Construye índice de imágenes con caché
     */
    public static function buildImageIndexWithCache($baseDir) {
        $cacheKey = 'product_images_index';
        
        // Intentar obtener desde caché
        $cached = self::getImageIndex($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Construir índice desde filesystem
        $index = [];
        
        if (!is_dir($baseDir)) {
            return $index;
        }

        $dirs = scandir($baseDir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $fullDir = $baseDir . '/' . $dir;
            if (!is_dir($fullDir)) {
                continue;
            }

            $matches = glob($fullDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
            if (!empty($matches)) {
                usort($matches, function ($a, $b) {
                    $scoreA = self::imagePriorityScore($a);
                    $scoreB = self::imagePriorityScore($b);
                    if ($scoreA === $scoreB) {
                        return strcmp((string)$a, (string)$b);
                    }
                    return $scoreA <=> $scoreB;
                });

                $index[$dir] = array_map(function ($path) use ($dir) {
                    return 'images/products/by_code/' . $dir . '/' . basename($path);
                }, $matches);
            }
        }

        // Guardar en caché
        self::setImageIndex($index, $cacheKey);

        return $index;
    }

    /**
     * Calcula prioridad de imagen basado en nombre
     */
    private static function imagePriorityScore($fileName) {
        $name = strtoupper((string)pathinfo($fileName, PATHINFO_FILENAME));
        
        if (preg_match('/\+FC1$/', $name)) {
            return 0;
        }
        if (preg_match('/\+E1$/', $name)) {
            return 1;
        }
        if (preg_match('/\+D1$/', $name)) {
            return 2;
        }
        if (preg_match('/\+O\d+$/', $name)) {
            return 3;
        }
        if (strpos($name, '+') === false) {
            return 50;
        }
        
        return 90;
    }

    /**
     * Invalida todo el caché de imágenes
     */
    public static function invalidateAll() {
        if (!self::isAPCuAvailable()) {
            return false;
        }

        // Eliminar todas las claves relacionadas con imágenes
        $iterator = new APCUIterator('/^product_images_/');
        foreach ($iterator as $item) {
            apcu_delete($item['key']);
        }

        return true;
    }
}
