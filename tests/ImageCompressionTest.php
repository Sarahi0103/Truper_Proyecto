<?php
/**
 * Test: Image Compression and WebP Conversion during upload
 */

require_once __DIR__ . '/../config/config.php';

// Mock admin session for CLI require_admin check
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['email'] = 'admin@truper.com';

require_once __DIR__ . '/../public/api/admin_supply.php';

class ImageCompressionTest {
    public static function run(): array {
        $results = ['name' => 'Image Compression & WebP Conversion Test', 'passed' => false, 'message' => ''];
        
        $tempPng = __DIR__ . '/temp_dummy_test.png';
        if (file_exists($tempPng)) {
            @unlink($tempPng);
        }

        try {
            // Check if GD and WebP support is present
            if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
                $results['message'] = "La extensión GD o la función imagewebp no están disponibles en este sistema.";
                return $results;
            }

            // Create a dummy PNG image (1500x1000 pixels, red filled) to test resize and WebP conversion
            $img = imagecreatetruecolor(1500, 1000);
            $red = imagecolorallocate($img, 255, 0, 0);
            imagefill($img, 0, 0, $red);
            
            if (!imagepng($img, $tempPng)) {
                $results['message'] = "No se pudo crear el archivo PNG temporal de prueba.";
                imagedestroy($img);
                return $results;
            }
            imagedestroy($img);

            $fileData = [
                'name' => 'temp_dummy_test.png',
                'type' => 'image/png',
                'tmp_name' => $tempPng,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tempPng)
            ];

            // Call store_product_image
            $savedWebPath = store_product_image($fileData);
            
            // Check if it's stored and ends with webp
            if (strpos($savedWebPath, '.webp') === false) {
                $results['message'] = "El archivo guardado no tiene la extensión .webp: " . $savedWebPath;
                return $results;
            }

            $absolutePath = __DIR__ . '/../public/' . $savedWebPath;
            if (!file_exists($absolutePath)) {
                $results['message'] = "El archivo WebP no existe en el disco: " . $absolutePath;
                return $results;
            }

            // Check details of the saved image
            $savedImgData = @file_get_contents($absolutePath);
            $savedImg = @imagecreatefromstring($savedImgData);
            if (!$savedImg) {
                $results['message'] = "El archivo guardado no es una imagen válida o compatible.";
                return $results;
            }

            $w = imagesx($savedImg);
            $h = imagesy($savedImg);
            imagedestroy($savedImg);

            // Resized image should be max 1200px
            if ($w > 1200 || $h > 1200) {
                $results['message'] = "La imagen no se redimensionó correctamente. Dimensiones actuales: {$w}x{$h} (máximo esperado: 1200)";
                return $results;
            }

            // Clean up the created image from products
            @unlink($absolutePath);
            if (file_exists($absolutePath . '.sha1')) {
                @unlink($absolutePath . '.sha1');
            }

            $results['passed'] = true;
            $results['message'] = "La conversión a WebP, redimensionamiento automático a {$w}x{$h} y compresión de imagen durante la subida funcionan correctamente.";
        } catch (Exception $e) {
            $results['message'] = "Excepción capturada: " . $e->getMessage();
        } finally {
            if (file_exists($tempPng)) {
                @unlink($tempPng);
            }
        }
        return $results;
    }
}
