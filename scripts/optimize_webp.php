<?php
/**
 * Script para optimizar imágenes a formato WebP
 * Convierte imágenes existentes a WebP para mejor rendimiento
 */

require_once __DIR__ . '/../config/config.php';

// Directorios de imágenes
$directories = [
    __DIR__ . '/../public/images/products',
    __DIR__ . '/../public/images',
];

echo "Iniciando optimización de imágenes a WebP...\n\n";

$totalConverted = 0;
$totalSkipped = 0;
$totalErrors = 0;

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        echo "Directorio no encontrado: $dir\n";
        continue;
    }

    echo "Procesando directorio: $dir\n";
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $extension = strtolower($file->getExtension());
            
            // Solo procesar imágenes JPEG, PNG y JPG
            if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                $originalPath = $file->getPathname();
                $webpPath = $originalPath . '.webp';
                
                // Si ya existe WebP, saltar
                if (file_exists($webpPath)) {
                    $totalSkipped++;
                    continue;
                }
                
                try {
                    // Cargar imagen según el tipo
                    $image = null;
                    switch ($extension) {
                        case 'jpg':
                        case 'jpeg':
                            $image = imagecreatefromjpeg($originalPath);
                            break;
                        case 'png':
                            $image = imagecreatefrompng($originalPath);
                            break;
                    }
                    
                    if ($image === false) {
                        $totalErrors++;
                        echo "  Error cargando: $originalPath\n";
                        continue;
                    }
                    
                    // Guardar como WebP con calidad 80
                    $result = imagewebp($image, $webpPath, 80);
                    
                    if ($result) {
                        $totalConverted++;
                        $originalSize = filesize($originalPath);
                        $webpSize = filesize($webpPath);
                        $reduction = round((1 - $webpSize / $originalSize) * 100, 1);
                        echo "  ✓ Convertido: $originalPath ($reduction% reducción)\n";
                    } else {
                        $totalErrors++;
                        echo "  ✗ Error guardando WebP: $originalPath\n";
                    }
                    
                    // Liberar memoria
                    imagedestroy($image);
                    
                } catch (Exception $e) {
                    $totalErrors++;
                    echo "  ✗ Excepción: $originalPath - " . $e->getMessage() . "\n";
                }
            }
        }
    }
}

echo "\n=== Resumen ===\n";
echo "Convertidas: $totalConverted\n";
echo "Saltadas (ya existían): $totalSkipped\n";
echo "Errores: $totalErrors\n";
echo "Total procesadas: " . ($totalConverted + $totalSkipped + $totalErrors) . "\n";

if ($totalConverted > 0) {
    echo "\n✓ Optimización completada exitosamente\n";
} else {
    echo "\nℹ No se convirtieron nuevas imágenes\n";
}
