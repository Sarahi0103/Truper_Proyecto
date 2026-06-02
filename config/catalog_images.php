<?php

if (!function_exists('cache_get') && file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (!function_exists('catalog_normalize_image_path')) {
    function catalog_normalize_image_path(string $path): string {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $path = preg_replace('/\?.*$/', '', $path) ?? $path;
        $path = ltrim($path, '/');
        return $path;
    }
}

if (!function_exists('catalog_local_image_exists')) {
    function catalog_local_image_exists(string $path): bool {
        $path = catalog_normalize_image_path($path);
        if ($path === '' || strpos($path, 'data:image/') === 0 || preg_match('/^https?:\/\//i', $path) === 1) {
            return $path !== '';
        }

        $candidates = [
            __DIR__ . '/../public/' . $path,
            __DIR__ . '/../' . $path,
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('catalog_is_gallery_image_reference')) {
    function catalog_is_gallery_image_reference(string $value): bool {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return strpos($value, 'images/') === 0
            || strpos($value, 'data:image/') === 0
            || preg_match('/\.(jpg|jpeg|png|webp|gif|svg)$/i', $value) === 1;
    }
}

if (!function_exists('catalog_gallery_image_priority_score')) {
    function catalog_gallery_image_priority_score(string $fileName): int {
        $name = strtoupper((string)pathinfo($fileName, PATHINFO_FILENAME));
        if (preg_match('/\+FC1$/', $name)) return 0;
        if (preg_match('/\+E1$/', $name)) return 1;
        if (preg_match('/\+D1$/', $name)) return 2;
        if (preg_match('/\+O\d+$/', $name)) return 3;
        if (strpos($name, '+') === false) return 50;
        return 90;
    }
}

if (!function_exists('catalog_resolve_gallery_images_by_sku')) {
    function catalog_resolve_gallery_images_by_sku(string $sku, array $itemRow = [], ?PDO $pdo = null): array {
        $sku = trim((string)$sku);
        $normalizedSku = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sku);

        // 1. Detectar si el producto ya tiene la imagen por defecto y no tiene variantes de imágenes
        $rowImage = catalog_normalize_image_path((string)($itemRow['image_url'] ?? ''));
        $hasRowDefaultImage = ($rowImage === '' || strpos($rowImage, 'default-product.svg') !== false);
        $hasRowVariants = false;
        if (!empty($itemRow['variants_json'])) {
            $decoded = json_decode((string)$itemRow['variants_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $item = trim((string)$item);
                    if (catalog_is_gallery_image_reference($item)) {
                        $hasRowVariants = true;
                        break;
                    }
                }
            }
        }

        if (!empty($itemRow) && $hasRowDefaultImage && !$hasRowVariants) {
            return ['images/products/default-product.svg'];
        }

        // 2. Caché persistente
        $cacheKey = 'res_gal_img_' . $normalizedSku . '_' . md5(json_encode($itemRow));
        if (function_exists('cache_get')) {
            $cached = cache_get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // 3. Resolver usando la función original renombrada
        $images = _catalog_resolve_gallery_images_by_sku_raw($sku, $itemRow, $pdo);

        // 4. Guardar en caché
        if (function_exists('cache_set')) {
            cache_set($cacheKey, $images);
        }

        return $images;
    }
}

if (!function_exists('_catalog_resolve_gallery_images_by_sku_raw')) {
    function _catalog_resolve_gallery_images_by_sku_raw(string $sku, array $itemRow = [], ?PDO $pdo = null): array {
        static $diskCache = null;

        $sku = trim((string)$sku);
        $normalizedSku = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sku);
        $images = [];

        $mergeImage = static function (string $value) use (&$images): void {
            $value = catalog_normalize_image_path($value);
            if ($value === '' || strpos($value, 'default-product.svg') !== false) {
                return;
            }
            if (!in_array($value, $images, true)) {
                $images[] = $value;
            }
        };

        $rowImage = catalog_normalize_image_path((string)($itemRow['image_url'] ?? ''));
        if ($rowImage !== '' && strpos($rowImage, 'default-product.svg') === false) {
            $mergeImage($rowImage);
        }

        if (!empty($itemRow['variants_json'])) {
            $decoded = json_decode((string)$itemRow['variants_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $item = trim((string)$item);
                    if (catalog_is_gallery_image_reference($item)) {
                        $mergeImage($item);
                    }
                }
            }
        }

        if (!empty($images)) {
            return $images;
        }

        if ($pdo instanceof PDO) {
            $tables = [];
            if (function_exists('db_table_exists') && db_table_exists('products')) {
                $tables[] = 'products';
            }
            if (function_exists('db_table_exists') && db_table_exists('marketplace_ce_products')) {
                $tables[] = 'marketplace_ce_products';
            }

            foreach ($tables as $table) {
                try {
                    $stmt = $pdo->prepare("SELECT image_url, variants_json FROM {$table} WHERE sku = ? OR sku LIKE ? LIMIT 1");
                    $stmt->execute([$sku, "%{$sku}%"]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($row['image_url'])) {
                        $mergeImage((string)$row['image_url']);
                    }
                    if (!empty($row['variants_json'])) {
                        $decodedRow = json_decode((string)$row['variants_json'], true);
                        if (is_array($decodedRow)) {
                            foreach ($decodedRow as $item) {
                                $item = trim((string)$item);
                                if (catalog_is_gallery_image_reference($item)) {
                                    $mergeImage($item);
                                }
                            }
                        }
                    }
                    if (!empty($images)) {
                        return $images;
                    }
                } catch (Exception $ignored) {
                }
            }
        }

        $skuRoots = [
            __DIR__ . '/../public/images/products/gallery/' . $normalizedSku => 'images/products/gallery',
            __DIR__ . '/../images/products/gallery/' . $normalizedSku => 'images/products/gallery',
            __DIR__ . '/../public/images/products/by_code/' . $normalizedSku => 'images/products/by_code',
            __DIR__ . '/../images/products/by_code/' . $normalizedSku => 'images/products/by_code',
        ];

        foreach ($skuRoots as $skuDir => $webPrefix) {
            if (!is_dir($skuDir)) {
                continue;
            }

            $matches = glob($skuDir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
            if (empty($matches) || !is_array($matches)) {
                continue;
            }

            usort($matches, static function ($a, $b) {
                $scoreA = catalog_gallery_image_priority_score((string)$a);
                $scoreB = catalog_gallery_image_priority_score((string)$b);
                if ($scoreA === $scoreB) {
                    return strcmp((string)$a, (string)$b);
                }

                return $scoreA <=> $scoreB;
            });

            foreach ($matches as $path) {
                $mergeImage(rtrim($webPrefix, '/') . '/' . $normalizedSku . '/' . basename((string)$path));
            }

            if (!empty($images)) {
                return $images;
            }
        }

        if ($diskCache === null) {
            $diskCache = [];
            $roots = [
                __DIR__ . '/../public/images/products/by_code' => 'images/products/by_code',
                __DIR__ . '/../public/images/products/gallery' => 'images/products/gallery',
                __DIR__ . '/../images/products/by_code' => 'images/products/by_code',
                __DIR__ . '/../images/products/gallery' => 'images/products/gallery',
            ];

            foreach ($roots as $rootPath => $webPrefix) {
                if (!is_dir($rootPath)) {
                    continue;
                }

                foreach (scandir($rootPath) ?: [] as $dir) {
                    if ($dir === '.' || $dir === '..') {
                        continue;
                    }

                    $fullDir = $rootPath . '/' . $dir;
                    if (!is_dir($fullDir)) {
                        continue;
                    }

                    $matches = glob($fullDir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
                    if (empty($matches) || !is_array($matches)) {
                        continue;
                    }

                    usort($matches, static function ($a, $b) {
                        $scoreA = catalog_gallery_image_priority_score((string)$a);
                        $scoreB = catalog_gallery_image_priority_score((string)$b);
                        if ($scoreA === $scoreB) {
                            return strcmp((string)$a, (string)$b);
                        }
                        return $scoreA <=> $scoreB;
                    });

                    $rootImages = array_map(static function ($path) use ($webPrefix, $dir) {
                        return rtrim($webPrefix, '/') . '/' . $dir . '/' . basename((string)$path);
                    }, $matches);

                    if (isset($diskCache[$dir])) {
                        $diskCache[$dir] = array_values(array_unique(array_merge($diskCache[$dir], $rootImages)));
                    } else {
                        $diskCache[$dir] = array_values(array_unique($rootImages));
                    }
                }
            }
        }

        foreach ($diskCache[$normalizedSku] ?? [] as $cachedImage) {
            $mergeImage($cachedImage);
        }

        if (empty($images)) {
            $images[] = 'images/products/default-product.svg';
        }

        return $images;
    }
}
