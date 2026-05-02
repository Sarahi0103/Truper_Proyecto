<?php
/**
 * Script de inicialización de directorios de imágenes
 * Se ejecuta automáticamente en config/config.php para asegurar que
 * el directorio gallery/ existe y tiene permisos correctos
 */

function ensure_image_directories_initialized() {
    $dirs = [
        __DIR__ . '/../public/images',
        __DIR__ . '/../public/images/products',
        __DIR__ . '/../public/images/products/gallery',
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        
        // Intentar establecer permisos
        if (is_dir($dir)) {
            @chmod($dir, 0775);
        }
    }
    
    // Específicamente para gallery, intentar con 777 si 775 no funciona
    $galleryDir = __DIR__ . '/../public/images/products/gallery';
    if (is_dir($galleryDir) && !is_writable($galleryDir)) {
        @chmod($galleryDir, 0777);
    }
}

// Ejecutar al cargar config
ensure_image_directories_initialized();
?>
