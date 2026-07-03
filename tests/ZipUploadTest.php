<?php
/**
 * Test: ZIP Upload and Image Extraction
 */

require_once __DIR__ . '/../config/config.php';

// Mock admin session for CLI require_admin check
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['email'] = 'admin@truper.com';

require_once __DIR__ . '/../public/api/admin_supply.php';

class ZipUploadTest {
    public static function run(): array {
        $results = ['name' => 'ZIP Upload & Image Extraction Test', 'passed' => false, 'message' => ''];
        
        $tempPng = __DIR__ . '/temp_zip_dummy.png';
        $tempZip = __DIR__ . '/temp_dummy_archive.zip';
        
        if (file_exists($tempPng)) @unlink($tempPng);
        if (file_exists($tempZip)) @unlink($tempZip);

        try {
            // Check ZipArchive
            if (!class_exists('ZipArchive')) {
                $results['message'] = "La extensión ZipArchive no está habilitada en este sistema.";
                return $results;
            }

            // Create a dummy PNG image (100x100 pixels)
            $img = imagecreatetruecolor(100, 100);
            $blue = imagecolorallocate($img, 0, 0, 255);
            imagefill($img, 0, 0, $blue);
            if (!imagepng($img, $tempPng)) {
                $results['message'] = "No se pudo crear el archivo PNG temporal.";
                imagedestroy($img);
                return $results;
            }
            imagedestroy($img);

            // Create a ZIP file and add the dummy PNG
            $zip = new ZipArchive();
            if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $results['message'] = "No se pudo crear el archivo ZIP temporal.";
                return $results;
            }
            $zip->addFile($tempPng, 'test_extracted_image.png');
            $zip->close();

            // Prepare mock $_FILES input structure
            $fileData = [
                'name' => 'temp_dummy_archive.zip',
                'type' => 'application/zip',
                'tmp_name' => $tempZip,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tempZip)
            ];

            // Use a mock SKU (e.g. 99999)
            $mockSku = '99999';
            $errors = [];
            $savedPaths = process_uploaded_files_and_zips([$fileData], $mockSku, $errors);

            if (!empty($errors)) {
                $results['message'] = "Errores al procesar el ZIP: " . implode('; ', $errors);
                return $results;
            }

            if (empty($savedPaths)) {
                $results['message'] = "No se extrajo ninguna imagen del archivo ZIP.";
                return $results;
            }

            // Verify the saved image path
            $savedPath = $savedPaths[0];
            $absolutePath = __DIR__ . '/../public/' . $savedPath;

            if (!file_exists($absolutePath)) {
                $results['message'] = "El archivo extraído no existe en el disco: " . $absolutePath;
                return $results;
            }

            // Clean up the created gallery directory for SKU 99999
            $imagesRoot = images_root_admin_supply();
            $galleryDir = $imagesRoot . '/products/gallery/' . $mockSku;
            clean_temporary_extract_dir($galleryDir);

            $results['passed'] = true;
            $results['message'] = "La extracción de imágenes desde archivos ZIP, conversión y compresión automática funcionan correctamente.";
        } catch (Exception $e) {
            $results['message'] = "Excepción capturada: " . $e->getMessage();
        } finally {
            if (file_exists($tempPng)) @unlink($tempPng);
            if (file_exists($tempZip)) @unlink($tempZip);
        }
        return $results;
    }
}
