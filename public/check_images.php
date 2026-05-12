<?php
require_once __DIR__ . '/../config/config.php';

header('Cache-Control: no-store, no-cache, must-revalidate');

$persist = '/var/www/data/images';
$publicImages = __DIR__ . '/images';

$result = [
    'timestamp' => date('c'),
    'persist_exists' => is_dir($persist),
    'persist_writable' => is_writable($persist),
    'public_images_symlink' => is_link($publicImages),
    'public_images_dir' => is_dir($publicImages),
    'gallery_sample' => [],
];

// Gather a few sample files if available
if (is_dir($persist) || is_dir($publicImages)) {
    $galleryRoot = is_dir($persist) ? rtrim($persist, '/') . '/products/gallery' : $publicImages . '/products/gallery';
    if (is_dir($galleryRoot)) {
        $skus = array_values(array_filter(scandir($galleryRoot), fn($d) => $d !== '.' && $d !== '..' && is_dir($galleryRoot . '/' . $d)));
        $result['gallery_count'] = count($skus);
        $result['gallery_sample'] = array_slice($skus, 0, 5);
        foreach ($result['gallery_sample'] as $i => $sku) {
            $files = glob("{$galleryRoot}/{$sku}/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP}", GLOB_BRACE) ?: [];
            $result['gallery_sample_files'][$sku] = array_map(fn($f) => str_replace(realpath(__DIR__) . '/', '/', $f), array_slice($files, 0, 5));
        }
    } else {
        $result['gallery_count'] = 0;
    }
} else {
    $result['gallery_count'] = 0;
}

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Check Images - Truper</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>body{font-family:Arial,sans-serif;background:#111;color:#eee;padding:18px}pre{background:#000;padding:12px;border-radius:6px;overflow:auto}</style>
</head>
<body>
    <h2>Verificación de imágenes</h2>
    <p><strong>Persist dir:</strong> <?php echo htmlspecialchars($persist); ?></p>
    <pre><?php echo htmlspecialchars(print_r($result, true)); ?></pre>
    <p>Formato JSON: <a href="?format=json">?format=json</a></p>
</body>
</html>
