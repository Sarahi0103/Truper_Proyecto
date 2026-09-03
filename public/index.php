<?php
// Failsafe for hosting rewrites: serve known static assets if this request was routed to index.php.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('#^/(css|js|images|img)/#', $requestPath) === 1 || preg_match('#\.(css|js|png|jpe?g|gif|webp|svg)$#i', $requestPath) === 1) {
    $assetPath = __DIR__ . '/' . ltrim($requestPath, '/');
    $assetReal = realpath($assetPath);
    $publicReal = realpath(__DIR__);

    if ($assetReal !== false && $publicReal !== false && strpos($assetReal, $publicReal) === 0 && is_file($assetReal)) {
        $ext = strtolower(pathinfo($assetReal, PATHINFO_EXTENSION));
        $mimeMap = [
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];
        header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=2592000');
        readfile($assetReal);
        exit;
    }
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/image_cache.php';

$products = [];
try {
    $visibilityWhere = '';
    if (db_column_exists('products', 'show_in_pos')) {
        $visibilityWhere = " WHERE (CASE WHEN show_in_pos IS NULL THEN (CASE WHEN is_active IS NULL THEN 1 WHEN LOWER(CAST(is_active AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) WHEN LOWER(CAST(show_in_pos AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
    } elseif (db_column_exists('products', 'is_active')) {
        $visibilityWhere = " WHERE (CASE WHEN is_active IS NULL THEN 1 WHEN LOWER(CAST(is_active AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
    } elseif (db_column_exists('products', 'active')) {
        $visibilityWhere = " WHERE active = 1";
    } else {
        $visibilityWhere = " WHERE 1 = 1";
    }
    $visibilityWhere .= " AND NOT EXISTS (
        SELECT 1 FROM product_categories pc 
        WHERE LOWER(pc.name) = LOWER(products.category) 
        AND pc.is_active = false
    )";

    $groupSelect = db_column_exists('products', 'product_group') ? "COALESCE(product_group, '') AS product_group" : "'' AS product_group";
    $colorSelect = db_column_exists('products', 'color') ? "COALESCE(color, '') AS color" : "'' AS color";

    $stmt = $pdo->prepare("SELECT id, name, sku, COALESCE(unit_price, sell_price, 0) AS unit_price, COALESCE(net_price, unit_price, sell_price, 0) AS net_price, COALESCE(discount_percentage, 0) AS discount_percentage, category, description, technical_specs, stock_quantity, image_url, variants_json, {$groupSelect}, {$colorSelect} FROM products" . $visibilityWhere . " ORDER BY name LIMIT 5000");
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    $products = [];
}

function normalize_product_code($sku) {
    $sku = (string)$sku;
    return preg_replace('/^XLS-/i', '', $sku);
}

function image_priority_score($fileName) {
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

function is_gallery_image_reference($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return false;
    }

    return strpos($value, 'images/') === 0 || strpos($value, 'data:image/') === 0 || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $value) === 1;
}

function resolve_images_by_product_code($code, array $productRow = []) {
    global $pdo;
    static $cache = null;

    $code = trim((string)$code);
    $images = [];

    $mergeImage = function (string $value) use (&$images) {
        $value = trim($value);
        if ($value === '' || strpos($value, 'default-product.svg') !== false) {
            return;
        }
        if (!in_array($value, $images, true)) {
            $images[] = $value;
        }
    };

    $imageUrl = trim((string)($productRow['image_url'] ?? ''));
    if ($imageUrl !== '' && $imageUrl !== 'images/products/default-product.svg') {
        $mergeImage($imageUrl);
    }

    if (!empty($productRow['variants_json'])) {
        $decoded = json_decode((string)$productRow['variants_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $itemStr = trim((string)$item);
                if (is_gallery_image_reference($itemStr)) {
                    $mergeImage($itemStr);
                }
            }
        }
    }

    // If the row already has a defined cover/gallery order, use it first.
    if (!empty($images)) {
        return $images;
    }

    // OPTIMIZACIÓN: Usar caché APCu para evitar escaneo de filesystem repetido
    if ($cache === null) {
        // Intentar obtener desde caché APCu
        $cache = ImageCache::getImageIndex();
        
        if ($cache === null) {
            // Si no está en caché, construir desde filesystem
            $cache = [];
            // 1) Directorio by_code (catálogo XLSX original)
            $baseDir = __DIR__ . '/images/products/by_code';
            if (is_dir($baseDir)) {
                $dirs = scandir($baseDir);
                foreach ($dirs as $dir) {
                    if ($dir === '.' || $dir === '..') continue;
                    $fullDir = $baseDir . '/' . $dir;
                    if (!is_dir($fullDir)) continue;
                    $matches = glob($fullDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
                    if (!empty($matches)) {
                        usort($matches, function ($a, $b) {
                            $scoreA = image_priority_score($a);
                            $scoreB = image_priority_score($b);
                            if ($scoreA === $scoreB) return strcmp((string)$a, (string)$b);
                            return $scoreA <=> $scoreB;
                        });
                        $cache[$dir] = array_map(function ($path) use ($dir) {
                            return 'images/products/by_code/' . $dir . '/' . basename($path);
                        }, $matches);
                    }
                }
            }

            // 2) Directorio gallery (subidas desde el admin)
            $galleryBase = __DIR__ . '/images/products/gallery';
            if (is_dir($galleryBase)) {
                $skuDirs = scandir($galleryBase);
                foreach ($skuDirs as $skuDir) {
                    if ($skuDir === '.' || $skuDir === '..') continue;
                    $fullDir = $galleryBase . '/' . $skuDir;
                    if (!is_dir($fullDir)) continue;
                    $matches = glob($fullDir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
                    if (!empty($matches)) {
                        $galleryImages = array_map(function ($path) use ($skuDir) {
                            return 'images/products/gallery/' . $skuDir . '/' . basename($path);
                        }, $matches);
                        // Prepend gallery images (admin-uploaded take priority)
                        if (isset($cache[$skuDir])) {
                            $cache[$skuDir] = array_merge($galleryImages, $cache[$skuDir]);
                        } else {
                            $cache[$skuDir] = $galleryImages;
                        }
                    }
                }
            }

            // Guardar en caché APCu para futuras peticiones
            ImageCache::setImageIndex($cache);
        }
    }

    foreach ($cache[$code] ?? [] as $cachedImage) {
        $mergeImage($cachedImage);
    }

    if (empty($images)) {
        $images[] = 'images/products/default-product.svg';
    }

    return $images;
}

$quickCategoriesMap = [];
$normalizeCategoryKey = function ($value) {
    $text = trim((string)$value);
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }
    return strtr($text, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ü' => 'u',
        'ñ' => 'n',
    ]);
};

foreach ($products as $item) {
    $rawCategory = trim((string)($item['category'] ?? ''));
    if ($rawCategory === '') {
        continue;
    }

    $categoryParts = preg_split('/\s*,\s*/', $rawCategory) ?: [];
    foreach ($categoryParts as $categoryPart) {
        $category = trim((string)$categoryPart);
        if ($category === '') {
            continue;
        }
        $key = $normalizeCategoryKey($category);
        if (!isset($quickCategoriesMap[$key])) {
            $quickCategoriesMap[$key] = $category;
        }
    }
}

$quickCategories = array_values($quickCategoriesMap);
$priorityCategories = [
    'material electrico' => 0,
    'fontaneria' => 1,
    'cerrajeria' => 2,
    'herreria' => 3,
];

$normalizeCategoryOrderKey = function ($value) {
    $text = trim((string)$value);
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }

    return strtr($text, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ü' => 'u',
        'ñ' => 'n',
    ]);
};

usort($quickCategories, function ($a, $b) use ($priorityCategories, $normalizeCategoryOrderKey) {
    $keyA = $normalizeCategoryOrderKey($a);
    $keyB = $normalizeCategoryOrderKey($b);

    $priorityA = $priorityCategories[$keyA] ?? 999;
    $priorityB = $priorityCategories[$keyB] ?? 999;

    if ($priorityA !== $priorityB) {
        return $priorityA <=> $priorityB;
    }

    return strcasecmp((string)$a, (string)$b);
});

// Intento de cargar categorías desde la tabla oficial para consistencia y sincronizar colores dinámicos
$dbRawCategoryColors = [];
$dbCategoryColors = [];
try {
    $catStmt = $pdo->query("SELECT name, color FROM product_categories WHERE is_active = true ORDER BY sort_order ASC, name ASC");
    if ($catStmt) {
        $dbCats = $catStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbCats)) {
            $uniqueDbCats = [];
            foreach ($dbCats as $row) {
                $c = trim((string)($row['name'] ?? ''));
                if ($c === '') continue;
                $key = $normalizeCategoryKey($c);
                if (!isset($uniqueDbCats[$key])) {
                    $uniqueDbCats[$key] = $c;
                }
                if (!empty($row['color'])) {
                    $dbRawCategoryColors[$key] = $row['color'];
                    $dbCategoryColors[$key] = create_category_color_style($row['color']);
                }
            }
            $quickCategories = array_values($uniqueDbCats);
        }
    }
} catch (Exception $ig) {
    // Si la tabla no existe o hay error, mantenemos $quickCategories generado dinámicamente
}

// Compile normalized products list for JS-based search, filtering, sorting, and infinite scroll
$jsonProducts = [];
foreach ($products as $product) {
    $rawSku = (string)($product['sku'] ?? '');
    $displaySku = normalize_product_code($rawSku);
    $productName = decode_legacy_entities((string)($product['name'] ?? ''));
    $productDescription = decode_legacy_entities((string)($product['description'] ?? ''));
    $productCategory = decode_legacy_entities((string)($product['category'] ?? ''));
    $imagePath = !empty($product['image_url']) ? $product['image_url'] : 'images/products/default-product.svg';
    $galleryImages = catalog_resolve_gallery_images_by_sku($displaySku, $product, $pdo);
    if (empty($galleryImages)) {
        $galleryImages = [$imagePath];
    }
    $variants = [];
    if (!empty($product['variants_json'])) {
        $decoded = json_decode($product['variants_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $itemStr = (string)$item;
                if (strpos($itemStr, 'images/') === false &&
                    strpos($itemStr, 'data:image/') === false &&
                    stripos($itemStr, '.jpg') === false &&
                    stripos($itemStr, '.jpeg') === false &&
                    stripos($itemStr, '.png') === false &&
                    stripos($itemStr, '.gif') === false &&
                    stripos($itemStr, '.webp') === false &&
                    stripos($itemStr, '.svg') === false) {
                    $variants[] = $itemStr;
                }
            }
        }
    }
    $stock = (int)($product['stock_quantity'] ?? 0);
    
    $jsonProducts[] = [
        'id' => (int)$product['id'],
        'sku' => $displaySku,
        'name' => $productName,
        'desc' => $productDescription,
        'cat' => $productCategory !== '' ? $productCategory : 'General',
        'price' => (float)$product['unit_price'],
        'net_price' => (float)$product['net_price'],
        'discount' => (float)$product['discount_percentage'],
        'stock' => $stock,
        'imgs' => $galleryImages,
        'variants' => $variants,
        'color' => !empty($product['color']) ? trim((string)$product['color']) : '',
        'group' => !empty($product['product_group']) ? trim((string)$product['product_group']) : ''
    ];
}

// Fetch saved product groups from database only — completely separate from categories
$dbProductGroups = [];
try {
    $pgStmt = $pdo->query("SELECT id, name, color FROM product_groups ORDER BY name ASC");
    if ($pgStmt) {
        $dbProductGroups = $pgStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $ignored) {}

// Build colorProductGroups (for category color pills) separately — still needed for categories bar
$colorProductGroups = [];
foreach ($products as $idx => $product) {
    $p = $jsonProducts[$idx];
    $cat = $p['cat'];
    $key = $normalizeCategoryKey($cat);
    $customColor = !empty($product['color']) ? trim((string)$product['color']) : null;
    $catStyle = get_category_color_style($cat, $customColor ?? ($dbRawCategoryColors[$key] ?? null));
    $colorHex = strtoupper($catStyle['border']);
    if (!isset($colorProductGroups[$colorHex])) {
        $colorProductGroups[$colorHex] = ['color' => $colorHex, 'style' => $catStyle, 'categories' => [], 'count' => 0];
    }
    $colorProductGroups[$colorHex]['count']++;
    if (!in_array($cat, $colorProductGroups[$colorHex]['categories'], true)) {
        $colorProductGroups[$colorHex]['categories'][] = $cat;
    }
}

// Build product group counts purely from product_group field — NEVER mix with categories
$productGroupCounts = [];
foreach ($products as $product) {
    $grp = !empty($product['product_group']) ? trim((string)$product['product_group']) : '';
    if ($grp !== '') {
        $productGroupCounts[mb_strtolower($grp)] = ($productGroupCounts[mb_strtolower($grp)] ?? 0) + 1;
    }
}

// availableProductGroups = DB groups with counts (only groups defined by admin)
$availableProductGroups = [];
foreach ($dbProductGroups as $g) {
    $gName = trim($g['name']);
    if ($gName === '') continue;
    $availableProductGroups[] = [
        'name'  => $gName,
        'color' => strtoupper($g['color'] ?? '#FF7F00'),
        'count' => $productGroupCounts[mb_strtolower($gName)] ?? 0
    ];
}

// Storefront display: Only show groups that have products assigned (count > 0)
$activeProductGroups = array_values(array_filter($availableProductGroups, function($g) {
    return (int)($g['count'] ?? 0) > 0;
}));
$dbGroupColorMap = [];
foreach ($availableProductGroups as $ag) {
    $dbGroupColorMap[mb_strtolower($ag['name'])] = $ag['color'];
}

$isLogged = is_logged_in();
$isAdmin = (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'employee');
$showSessionExpiredNotice = (($_GET['error'] ?? '') === 'expired');
$whatsappHelpUrl = whatsapp_url('Hola, tengo una duda sobre los productos y cotizaciones.');
$clientTicketCode = 'PUBLICO';
$clientTicketNumber = $isLogged ? (string)($_SESSION['user_id'] ?? '0') : '0';

if ($isLogged && db_column_exists('users', 'user_code')) {
    try {
        $stmtUserCode = $pdo->prepare("SELECT COALESCE(user_code, '') AS user_code FROM users WHERE id = ? LIMIT 1");
        $stmtUserCode->execute([$_SESSION['user_id']]);
        $userData = $stmtUserCode->fetch();
        if (!empty($userData['user_code'])) {
            $clientTicketCode = (string)$userData['user_code'];
        }
    } catch (Exception $ignored) {
        $clientTicketCode = 'PUBLICO';
    }
}

$homepageUpdates = [];
try {
    $activeColumn = null;
    if (db_column_exists('homepage_updates', 'is_active')) {
        $activeColumn = 'is_active';
    } elseif (db_column_exists('homepage_updates', 'active')) {
        $activeColumn = 'active';
    }

    $sortColumn = db_column_exists('homepage_updates', 'sort_order') ? 'sort_order' : (db_column_exists('homepage_updates', 'position') ? 'position' : 'id');
    $imageSelect = db_column_exists('homepage_updates', 'image_url')
        ? "COALESCE(image_url, '') AS image_url"
        : "'' AS image_url";
    $briefDescSelect = db_column_exists('homepage_updates', 'brief_description')
        ? "COALESCE(brief_description, '') AS brief_description"
        : "'' AS brief_description";

    $whereActive = '';
    if ($activeColumn !== null) {
        $whereActive = " WHERE (CASE WHEN {$activeColumn} IS NULL THEN 1 WHEN LOWER(CAST({$activeColumn} AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
    }

    $stmtUpdates = $pdo->query("SELECT id, update_type, title, body, {$briefDescSelect}, {$imageSelect} FROM homepage_updates{$whereActive} ORDER BY {$sortColumn} ASC, id DESC LIMIT 12");
    $homepageUpdates = $stmtUpdates ? $stmtUpdates->fetchAll() : [];
} catch (Exception $ignored) {
    $homepageUpdates = [];
}

function homepage_update_label($type) {
    $value = strtolower(trim((string)$type));
    if ($value === 'promocion') {
        return 'Promoción';
    }
    if ($value === 'evento') {
        return 'Evento';
    }
    return 'Noticia';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Ferretería FOX - Catálogo de Productos</title>
    <meta name="description" content="Catálogo digital de Ferretería FOX en Fraccionamiento Colinas del Roble, C.P. 45645, Jalisco. Productos Truper, herramientas, material eléctrico, fontanería y artículos de segunda mano Marketplace CE.">
    <meta name="keywords" content="Ferretería FOX, Truper, Colinas del Roble, Jalisco, herramientas, material eléctrico, fontanería, cerrajería, herrería, Marketplace CE">
    
    <!-- Open Graph (Facebook / WhatsApp sharing preview) -->
    <meta property="og:title" content="Ferretería FOX - Catálogo de Productos">
    <meta property="og:description" content="Explora nuestro catálogo completo de herramientas Truper y artículos CE en Fraccionamiento Colinas del Roble, C.P. 45645, Jal.">
    <meta property="og:image" content="/img/logo_fox.png">
    <meta property="og:type" content="website">

    <!-- Schema.org LocalBusiness Structured Data for Google Search -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "HardwareStore",
      "name": "Ferretería FOX",
      "image": "https://truper-web-eg3h.onrender.com/img/logo_fox.png",
      "@id": "https://truper-web-eg3h.onrender.com/#store",
      "url": "https://truper-web-eg3h.onrender.com",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Fraccionamiento Colinas del Roble",
        "addressLocality": "Colinas del Roble",
        "addressRegion": "Jalisco",
        "postalCode": "45645",
        "addressCountry": "MX"
      },
      "geo": {
        "@type": "GeoCoordinates",
        "latitude": 20.5049398,
        "longitude": -103.3957862
      },
      "openingHoursSpecification": {
        "@type": "OpeningHoursSpecification",
        "dayOfWeek": [
          "Monday",
          "Tuesday",
          "Wednesday",
          "Thursday",
          "Friday",
          "Saturday",
          "Sunday"
        ],
        "opens": "08:00",
        "closes": "20:00"
      }
    }
    </script>
    <link rel="stylesheet" href="<?php echo asset_url('css/styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/theme.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/responsive-complete.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/dark-mode-auto.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/onboarding.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/toast-notifications.css'); ?>">
    <style>
        .color-chip-filter {
            border-radius: 20px !important;
            font-size: 0.82rem !important;
            padding: 5px 14px !important;
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid var(--grp-color, rgba(255, 255, 255, 0.2)) !important;
            color: rgba(255, 255, 255, 0.85) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 7px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
            opacity: 0.8;
        }
        .color-chip-filter:hover {
            opacity: 1 !important;
            background: rgba(255, 255, 255, 0.12) !important;
            color: #ffffff !important;
            transform: translateY(-1px);
        }
        .color-chip-filter.active {
            opacity: 1 !important;
            background: var(--grp-color, var(--theme-accent, #ff7f00)) !important;
            color: #ffffff !important;
            border-color: #ffffff !important;
            box-shadow: 0 0 14px var(--grp-color, rgba(255, 127, 0, 0.6)) !important;
            font-weight: 700 !important;
            transform: scale(1.05) !important;
        }
        .color-chip-filter.active .cat-dot {
            background: #ffffff !important;
            border-color: #ffffff !important;
            box-shadow: 0 0 8px #ffffff !important;
        }
        .color-chip-filter .count-badge {
            background: rgba(0, 0, 0, 0.4);
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: bold;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .color-chip-filter.active .count-badge {
            background: rgba(0, 0, 0, 0.5);
            border-color: rgba(255, 255, 255, 0.4);
        }
        
        /* Pulsing Glow Button for Online Store */
        @keyframes pulse-glow-btn {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 127, 0, 0.6), 0 8px 24px rgba(255, 127, 0, 0.3);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(255, 127, 0, 0), 0 8px 24px rgba(255, 127, 0, 0.3);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 127, 0, 0), 0 8px 24px rgba(255, 127, 0, 0.3);
            }
        }
        .btn-glow-pulse:hover {
            transform: scale(1.06) translateY(-2px) !important;
            box-shadow: 0 12px 30px rgba(255, 127, 0, 0.6) !important;
            background: linear-gradient(135deg, #ffb01f 0%, #ff7f00 100%) !important;
            filter: brightness(1.1);
        }
    </style>
    <link rel="stylesheet" href="<?php echo asset_url('css/catalog-min.css'); ?>">
</head>
<body class="catalog-minimal" data-client-code="<?php echo htmlspecialchars($clientTicketCode, ENT_QUOTES, 'UTF-8'); ?>" data-client-number="<?php echo htmlspecialchars($clientTicketNumber, ENT_QUOTES, 'UTF-8'); ?>">
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px; width: auto; object-fit: contain;"></a>
            <button class="hamburger-btn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav class="nav-menu">
                <a href="index.php" class="active">Productos</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <?php if ($isAdmin): ?>
                    <a href="guest_tickets.php">Tickets sin Registro</a>
                <?php endif; ?>
                <a href="cart.php">Carrito</a>
                <?php if ($isLogged): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="dashboard.php">Dashboard</a>
                            <a href="orders.php">Pedidos</a>
                            <a href="wholesale.php">Mayoreo</a>
                            <a href="account.php#historyTab">Historial</a>
                            <a href="profile.php">Perfil</a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="cashier.php">Caja</a>
                            <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                            <a href="tickets.php">Tickets</a>
                            <a href="tasks.php">Tareas</a>
                            <a href="gastos.php">Gastos</a>
                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                <a href="analytics.php">Estadísticas</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
            <div class="header-actions">
                <a href="<?php echo htmlspecialchars($whatsappHelpUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-small">Dudas por WhatsApp</a>
                <?php if (!$isLogged): ?>
                    <a href="admin_login.php" class="btn btn-primary btn-small">Solo para administradores</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <?php if ($showSessionExpiredNotice): ?>
            <section id="sessionExpiredNotice" class="alert alert-warning" role="status" aria-live="polite" style="margin-bottom: 1rem;">
                Tu sesión expiró por seguridad. Inicia sesión nuevamente para continuar.
            </section>
        <?php endif; ?>

        <section class="catalog-hero" style="background: linear-gradient(180deg, rgba(14, 14, 16, 0.75) 0%, rgba(23, 23, 26, 0.85) 100%), url('/img/fondo_portada_truper.png') center/cover no-repeat !important; background-size: cover !important;">
            <div class="module-badge module-main"><span class="module-glyph">CT</span> Catálogo principal</div>
            <h1>Catálogo Ferretería FOX</h1>
            <p style="color: #ffffff !important; font-weight: 600 !important; font-size: 1.15rem !important; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.9) !important; opacity: 1 !important;">Visualización ágil, sencilla y eficaz con precio, stock, variantes e información técnica.</p>
            <div style="margin-top: 16px; display: flex; gap: 12px; justify-content: center; align-items: center; flex-wrap: wrap;">
                <a href="/tienda.php" class="btn btn-primary btn-small btn-glow-pulse" style="background: linear-gradient(135deg, #ff9f00 0%, #ff6600 100%) !important; border: 1px solid #ff9f00 !important; color: #fff !important; font-weight: 800 !important; font-size: 1.02rem !important; display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px; border-radius: 50px; text-shadow: 0 1px 2px rgba(0,0,0,0.3); transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: 0 8px 24px rgba(255, 127, 0, 0.4); animation: pulse-glow-btn 2s infinite;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 1px 1px rgba(0,0,0,0.3));">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    Comprar en línea
                </a>
                <a href="/marketplace_ce.php" class="btn btn-secondary btn-small" style="padding: 10px 24px; border-radius: 50px; font-weight: 700; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 8px;">
                    Ir a Marketplace CE (segunda mano)
                </a>
            </div>
        </section>

        <?php if (!empty($homepageUpdates)): ?>
        <section class="promo-carousel" aria-label="Noticias y promociones">
            <div class="promo-head">
                <div>
                    <div class="module-badge module-main"><span class="module-glyph">NT</span> Noticias y promociones</div>
                    <h2>Novedades del punto de venta</h2>
                </div>
                <div class="promo-controls">
                    <button type="button" class="btn btn-ghost btn-small" data-promo-prev aria-label="Anterior">← Anterior</button>
                    <button type="button" class="btn btn-ghost btn-small" data-promo-next aria-label="Siguiente">Siguiente →</button>
                </div>
            </div>

            <div class="promo-viewport" data-promo-viewport>
                <div class="promo-track" data-promo-track>
                    <?php foreach ($homepageUpdates as $update): ?>
                        <article class="promo-slide" data-promo-slide>
                            <div class="promo-slide-content <?php echo !empty($update['image_url']) ? 'has-image' : ''; ?>">
                                <div class="promo-slide-text">
                                    <span class="promo-kicker"><?php echo htmlspecialchars(homepage_update_label($update['update_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <h3><?php echo htmlspecialchars((string)($update['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p><?php echo htmlspecialchars((string)(($update['brief_description'] ?? '') !== '' ? $update['brief_description'] : ($update['body'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div style="margin-top: 1rem;">
                                        <a href="update_detail.php?id=<?php echo (int)$update['id']; ?>" class="btn btn-primary btn-small" style="font-weight: 700; border-radius: 8px;">Ver más</a>
                                    </div>
                                </div>
                                <?php if (!empty($update['image_url'])): ?>
                                    <div class="promo-slide-media">
                                        <img src="<?php echo htmlspecialchars($update['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($update['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="promo-image">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="promo-dots" data-promo-dots></div>
        </section>
        <?php endif; ?>

        <section class="catalog-shell">
            <div class="catalog-categories-top">
                <div class="catalog-categories-title">Categorías</div>
                <div class="catalog-categories-actions">
                    <button type="button" class="btn btn-ghost btn-small active" data-quick-category="">Todas</button>
                    <?php foreach ($quickCategories as $categoryName):
                        $catStyle = get_category_color_style($categoryName, $dbRawCategoryColors[$normalizeCategoryKey($categoryName)] ?? null);
                    ?>
                        <button
                            type="button"
                            class="btn btn-ghost btn-small category-colored-pill"
                            style="--cat-bg: <?php echo $catStyle['bg']; ?>; --cat-border: <?php echo $catStyle['border']; ?>; --cat-color: <?php echo $catStyle['color']; ?>; --cat-dot: <?php echo $catStyle['dot']; ?>;"
                            data-quick-category="<?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="cat-dot" style="background: <?php echo $catStyle['dot']; ?>;"></span>
                            <?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Apartado de Agrupaciones de Productos -->
                <?php if (!empty($activeProductGroups)): ?>
                <div class="catalog-colors-bar" style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; margin-top:0.85rem; padding:0.75rem 1.1rem; background:rgba(20,20,24,0.85); border:1px solid rgba(255,255,255,0.1); border-radius:14px; backdrop-filter:blur(10px); box-shadow:0 4px 20px rgba(0,0,0,0.4);">
                    <span style="font-size:0.85rem; font-weight:700; color:var(--theme-accent, #ff7f00); margin-right:4px; text-transform:uppercase; letter-spacing:0.04em;">
                        AGRUPACIONES:
                    </span>
                    <button type="button" class="btn btn-ghost btn-small color-chip-filter active" data-color-filter="" data-grp-color="#ff7f00" style="--grp-color:#ff7f00;">
                        Todas las agrupaciones
                    </button>
                    <button type="button" class="btn btn-ghost btn-small color-chip-filter" data-color-filter="__NONE__" data-grp-color="#64748b" style="--grp-color:#64748b;">
                        Sin agrupación
                    </button>
                    <?php foreach ($activeProductGroups as $gInfo): 
                        $grpName = $gInfo['name'];
                        $grpColor = !empty($gInfo['color']) ? $gInfo['color'] : '#FF7F00';
                    ?>
                        <button type="button" 
                                class="btn btn-ghost btn-small color-chip-filter" 
                                data-color-filter="<?php echo htmlspecialchars($grpName, ENT_QUOTES, 'UTF-8'); ?>"
                                data-grp-color="<?php echo $grpColor; ?>"
                                style="--grp-color:<?php echo $grpColor; ?>;"
                                title="Agrupación: <?php echo htmlspecialchars($grpName, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="cat-dot" style="background:<?php echo $grpColor; ?>; width:11px; height:11px; border-radius:50%; border:1.5px solid #ffffff; box-shadow:0 0 6px <?php echo $grpColor; ?>;"></span>
                            <?php echo htmlspecialchars($grpName, ENT_QUOTES, 'UTF-8'); ?>
                            <span class="count-badge"><?php echo $gInfo['count']; ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="catalog-toolbar">
                <input id="catalogSearch" class="catalog-search" type="text" placeholder="Buscar por nombre, código o categoría...">
            </div>

            <div class="catalog-filters">
                <?php if (!empty($activeProductGroups)): ?>
                <select id="filterGroup" style="min-width:210px; font-weight:600; border-color:var(--theme-accent, #ff7f00); cursor:pointer;">
                    <option value="">Todos los Grupos de Productos</option>
                    <option value="__NONE__">Sin agrupación</option>
                    <?php foreach ($activeProductGroups as $gInfo): ?>
                        <option value="<?php echo htmlspecialchars($gInfo['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($gInfo['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo $gInfo['count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <select id="filterStock">
                    <option value="">Todo stock</option>
                    <option value="available">Solo disponibles</option>
                    <option value="low">Stock bajo</option>
                </select>
                <select id="filterSort">
                    <option value="name_asc">Nombre A-Z</option>
                    <option value="name_desc">Nombre Z-A</option>
                    <option value="price_asc">Precio menor a mayor</option>
                    <option value="price_desc">Precio mayor a menor</option>
                    <option value="stock_desc">Más stock primero</option>
                </select>
                <button id="clearFilters" class="btn btn-ghost">Limpiar filtros</button>
            </div>

            <div class="catalog-grid-min">
                <?php
                $initialBatch = array_slice($jsonProducts, 0, 24);
                foreach ($initialBatch as $p):
                    $firstImg = !empty($p['imgs'][0]) ? $p['imgs'][0] : 'images/products/default-product.svg';
                ?>
                    <article class="product-card-min"
                        data-product-card
                        data-name="<?php echo htmlspecialchars(strtolower($p['name']), ENT_QUOTES, 'UTF-8'); ?>"
                        data-sku="<?php echo htmlspecialchars(strtolower($p['sku']), ENT_QUOTES, 'UTF-8'); ?>"
                        data-category="<?php echo htmlspecialchars($p['cat'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-price="<?php echo $p['price']; ?>"
                        data-stock="<?php echo $p['stock']; ?>">
                        <div class="product-media" data-product-gallery>
                            <a href="product_detail.php?id=<?php echo $p['id']; ?>" class="product-media-link" aria-label="Ver detalle de <?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>"></a>
                            <?php foreach ($p['imgs'] as $idx => $galleryImage): ?>
                                <img
                                    class="product-gallery-image <?php echo $idx === 0 ? 'active' : ''; ?>"
                                    src="<?php echo htmlspecialchars($galleryImage, ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    loading="lazy"
                                    decoding="async"
                                    fetchpriority="<?php echo $idx === 0 ? 'high' : 'low'; ?>"
                                    width="300"
                                    height="300">
                            <?php endforeach; ?>
                            <?php if (count($p['imgs']) > 1): ?>
                                <button type="button" class="gallery-nav gallery-prev" data-gallery-prev aria-label="Imagen anterior">&#10094;</button>
                                <button type="button" class="gallery-nav gallery-next" data-gallery-next aria-label="Imagen siguiente">&#10095;</button>
                                <div class="gallery-counter"><span data-gallery-current>1</span>/<?php echo count($p['imgs']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="product-content">
                            <?php $catStyle = get_category_color_style($p['cat']); ?>
                            <div class="catalog-tag category-colored-tag" style="background: <?php echo $catStyle['bg']; ?>; color: <?php echo $catStyle['color']; ?>; border: 1px solid <?php echo $catStyle['border']; ?>;">
                                <span class="cat-dot" style="background: <?php echo $catStyle['dot']; ?>;"></span>
                                <?php echo htmlspecialchars($p['cat'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <?php if (!empty($p['group'])): 
                                $grpTrim = trim((string)$p['group']);
                                $grpColor = $dbGroupColorMap[mb_strtolower($grpTrim)] ?? '#FF7F00';
                            ?>
                                <div class="catalog-tag group-colored-tag" style="background:rgba(0,0,0,0.5); color:#ffffff; border:1px solid <?php echo $grpColor; ?>; font-weight:600; margin-top:3px;">
                                    <span class="cat-dot" style="background:<?php echo $grpColor; ?>; box-shadow:0 0 6px <?php echo $grpColor; ?>;"></span>
                                    <?php echo htmlspecialchars($grpTrim, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="product-code-label"><strong>Código:</strong> <strong><?php echo htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                            <h3 class="product-title"><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="product-spec"><?php echo htmlspecialchars($p['desc'] !== '' ? $p['desc'] : 'Descripción pendiente', ENT_QUOTES, 'UTF-8'); ?></p>
                            <div>
                                <?php if (!empty($p['variants'])): ?>
                                    <?php foreach ($p['variants'] as $variant): ?>
                                        <span class="variant-pill"><?php echo htmlspecialchars((string)$variant, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="variant-pill">Modelo Estandar</span>
                                <?php endif; ?>
                            </div>
                            <span class="stock-badge <?php echo $p['stock'] <= 10 ? 'stock-low' : 'stock-ok'; ?>">
                                <?php echo $p['stock'] <= 10 ? 'Stock bajo: ' : 'Stock: '; ?><?php echo $p['stock']; ?>
                            </span>
                            <?php if ($p['discount'] > 0): ?>
                                <div class="catalog-price-container" style="display: flex; flex-direction: column; gap: 2px; margin-bottom: 8px;">
                                    <div class="original-price-wrap" style="display: flex; align-items: center; gap: 8px;">
                                        <span class="price-base" style="text-decoration: line-through; color: rgba(255, 255, 255, 0.4); font-size: 0.85rem;">
                                            $<?php echo number_format($p['net_price'], 2, '.', ','); ?>
                                        </span>
                                        <span class="discount-badge" style="background: rgba(255, 102, 0, 0.15); color: var(--color-naranja, #ff6600); font-size: 0.75rem; font-weight: bold; padding: 2px 6px; border-radius: 4px;">
                                            <?php echo $p['discount']; ?>% OFF
                                        </span>
                                    </div>
                                    <div class="catalog-price" style="color: #fff; font-weight: 700; font-size: 1.2rem; padding: 0;">
                                        $<?php echo number_format($p['price'], 2, '.', ','); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="catalog-price">$<?php echo number_format($p['price'], 2, '.', ','); ?></div>
                            <?php endif; ?>
                            <div class="product-actions">
                                <button
                                    type="button"
                                    class="btn btn-primary btn-small"
                                    data-add-product
                                    data-id="<?php echo $p['id']; ?>"
                                    data-sku="<?php echo htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-image="<?php echo htmlspecialchars($firstImg, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-price="<?php echo $p['price']; ?>">Agregar</button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Empty State -->
            <div id="catalogEmptyState" style="display: none; text-align: center; padding: 4rem 2rem; color: #888; grid-column: 1 / -1;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                <h3 style="color: #fff; margin-bottom: 0.5rem;">No se encontraron productos</h3>
                <p>Intenta con otros términos de búsqueda o filtros.</p>
            </div>

            <!-- Loading Spinner for Infinite Scroll -->
            <div id="catalogLoading" style="display: none; justify-content: center; align-items: center; padding: 2rem 0; grid-column: 1 / -1;">
                <div class="premium-spinner"></div>
            </div>

            <!-- Infinite Scroll Sentinel -->
            <div id="catalogSentinel" style="height: 20px; margin-bottom: 2rem; grid-column: 1 / -1;"></div>

            <!-- CSS style for premium spinner -->
            <style>
                .premium-spinner {
                    width: 40px;
                    height: 40px;
                    border: 3px solid rgba(255, 127, 0, 0.1);
                    border-radius: 50%;
                    border-top-color: var(--theme-accent, #ff7f00);
                    animation: spin 0.8s linear infinite;
                }
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            </style>
        </section>
    </main>

    <button id="openCart" class="cart-fab">Carrito (<span id="cartCount">0</span>)</button>
    <aside id="cartDrawer" class="cart-drawer">
        <div class="d-flex justify-between align-center">
            <h3>Tu Carrito</h3>
            <button id="closeCart" class="btn btn-small btn-ghost">✕</button>
        </div>
        <div id="cartList" class="cart-list"></div>
        <div class="cart-summary">
            <div class="d-flex justify-between align-center">
                <span><strong>Total:</strong></span>
                <span class="cart-total"><strong id="cartTotalAmount">$0</strong></span>
            </div>
            <div class="btn-group" style="flex-direction: column; gap: 8px;">
                <a href="cart.php" class="btn btn-primary" style="text-align:center; text-decoration:none; font-weight:800; display:block; width:100%; padding:10px; background:linear-gradient(135deg,#ff7f00,#ff5500); color:#fff; border-radius:8px;">Ver Carrito / Realizar Pedido</a>
                <button id="printTicket" class="btn btn-secondary">⬇️ Enviar cotización</button>
                <button id="shareWhatsApp" class="btn btn-secondary"
                        data-company-whatsapp="<?php echo htmlspecialchars(whatsapp_phone_digits(), ENT_QUOTES, 'UTF-8'); ?>"
                        data-client-code="<?php echo htmlspecialchars($clientCode ?? 'PUBLICO', ENT_QUOTES, 'UTF-8'); ?>">📱 Enviar cotización por WhatsApp</button>
                <button id="clearCart" class="btn btn-ghost">🗑️ Vaciar Carrito</button>
            </div>
        </div>
    </aside>

    <footer>
        <div class="footer-bottom">&copy; 2026 Ferretería FOX</div>
    </footer>
    <script src="<?php echo asset_url('js/jspdf.umd.min.js'); ?>"></script>
    <script src="<?php echo asset_url('js/main.js'); ?>"></script>
    <script src="<?php echo asset_url('js/modals.js'); ?>"></script>
    <script src="<?php echo asset_url('js/toast-notifications.js'); ?>"></script>
    <script id="product-groups-data" type="application/json"><?php echo json_encode($availableProductGroups); ?></script>
    <script id="products-data" type="application/json"><?php echo json_encode($jsonProducts); ?></script>
    <script>
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>';
        window.categoryColorMap = <?php echo json_encode(array_merge(get_category_color_map(), $dbCategoryColors)); ?>;
    </script>
    <script src="<?php echo asset_url('js/catalog.js'); ?>"></script>
    <script>
        // Compartir por WhatsApp
        document.addEventListener('DOMContentLoaded', function() {
            // Lightbox para imágenes de Portada
            function openLightbox(src, alt) {
                let lightbox = document.getElementById('promoLightbox');
                if (!lightbox) {
                    lightbox = document.createElement('div');
                    lightbox.id = 'promoLightbox';
                    lightbox.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100vw;
                        height: 100vh;
                        background: rgba(0, 0, 0, 0.85);
                        backdrop-filter: blur(8px);
                        -webkit-backdrop-filter: blur(8px);
                        z-index: 9999;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        opacity: 0;
                        transition: opacity 0.3s ease;
                        cursor: zoom-out;
                    `;
                    lightbox.innerHTML = `
                        <button type="button" style="
                            position: absolute;
                            top: 1.5rem;
                            right: 1.5rem;
                            background: rgba(255, 255, 255, 0.1);
                            border: 1px solid rgba(255, 255, 255, 0.2);
                            color: #fff;
                            border-radius: 50%;
                            width: 44px;
                            height: 44px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 1.5rem;
                            cursor: pointer;
                            transition: all 0.2s;
                            font-weight: bold;
                        " onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.transform='scale(1.05)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.transform='none'">✕</button>
                        <img id="promoLightboxImg" src="" alt="" style="
                            max-width: 90%;
                            max-height: 90%;
                            object-fit: contain;
                            border-radius: 8px;
                            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
                            transform: scale(0.95);
                            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                        ">
                    `;
                    document.body.appendChild(lightbox);
                    
                    const close = () => {
                        lightbox.style.opacity = '0';
                        lightbox.querySelector('img').style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            lightbox.style.display = 'none';
                        }, 300);
                    };
                    
                    lightbox.addEventListener('click', close);
                    lightbox.querySelector('button').addEventListener('click', (e) => {
                        e.stopPropagation();
                        close();
                    });
                }
                
                const img = document.getElementById('promoLightboxImg');
                img.src = src;
                img.alt = alt || '';
                
                lightbox.style.display = 'flex';
                lightbox.offsetHeight;
                lightbox.style.opacity = '1';
                img.style.transform = 'scale(1)';
            }

            // Event delegation for promo images (handles cloned slides in infinite loop carousels)
            document.addEventListener('click', function(e) {
                const img = e.target.closest('.promo-image');
                if (img) {
                    e.preventDefault();
                    e.stopPropagation();
                    openLightbox(img.src, img.alt);
                }
            });

            // Menú hamburguesa responsivo
            const hamburgerBtn = document.querySelector('.hamburger-btn');
            const navMenu = document.querySelector('.nav-menu');

            if (hamburgerBtn && navMenu) {
                hamburgerBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    navMenu.classList.toggle('active');
                    hamburgerBtn.classList.toggle('active');
                });

                // Dropdown toggle en móvil
                const dropdownBtns = document.querySelectorAll('.nav-dropdown-btn');
                dropdownBtns.forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const dropdown = this.closest('.nav-dropdown');
                        dropdown.classList.toggle('active');
                    });
                });

                // Cerrar menú al hacer click en un enlace
                navMenu.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', function() {
                        navMenu.classList.remove('active');
                        hamburgerBtn.classList.remove('active');
                    });
                });

                // Cerrar menú al hacer click fuera
                document.addEventListener('click', function(e) {
                    if (!hamburgerBtn.contains(e.target) && !navMenu.contains(e.target)) {
                        navMenu.classList.remove('active');
                        hamburgerBtn.classList.remove('active');
                    }
                });
            }

            const sessionExpiredNotice = document.getElementById('sessionExpiredNotice');
            if (sessionExpiredNotice) {
                window.setTimeout(function () {
                    sessionExpiredNotice.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
                    sessionExpiredNotice.style.opacity = '0';
                    sessionExpiredNotice.style.transform = 'translateY(-4px)';
                    window.setTimeout(function () {
                        if (sessionExpiredNotice.parentNode) {
                            sessionExpiredNotice.parentNode.removeChild(sessionExpiredNotice);
                        }
                    }, 360);
                }, 5000);
            }

            const companyWhatsApp = '<?php echo htmlspecialchars(whatsapp_phone_digits(), ENT_QUOTES, 'UTF-8'); ?>';

            // Carrusel de noticias/promociones
            const promoTrack = document.querySelector('[data-promo-track]');
            const promoViewport = document.querySelector('[data-promo-viewport]');
            const promoSlides = Array.from(document.querySelectorAll('[data-promo-slide]'));
            const promoDotsHost = document.querySelector('[data-promo-dots]');
            const prevPromoBtn = document.querySelector('[data-promo-prev]');
            const nextPromoBtn = document.querySelector('[data-promo-next]');

            let promoIndex = 0;
            let promoTimer = null;
            const promoDelay = 4000;

            function renderPromoDots() {
                if (!promoDotsHost || promoSlides.length <= 1) return;
                promoDotsHost.innerHTML = promoSlides.map((_, idx) =>
                    `<button type="button" class="promo-dot ${idx === 0 ? 'active' : ''}" data-promo-dot="${idx}" aria-label="Ir a noticia ${idx + 1}"></button>`
                ).join('');

                promoDotsHost.querySelectorAll('[data-promo-dot]').forEach((dot) => {
                    dot.addEventListener('click', function () {
                        promoIndex = Number(this.getAttribute('data-promo-dot')) || 0;
                        updatePromo();
                        restartPromoAuto();
                    });
                });
            }

            function updatePromo() {
                if (!promoTrack || promoSlides.length === 0) return;
                promoTrack.style.transform = `translateX(-${promoIndex * 100}%)`;

                if (!promoDotsHost) return;
                promoDotsHost.querySelectorAll('.promo-dot').forEach((dot, idx) => {
                    dot.classList.toggle('active', idx === promoIndex);
                });
            }

            function nextPromo() {
                promoIndex = (promoIndex + 1) % promoSlides.length;
                updatePromo();
            }

            function prevPromo() {
                promoIndex = (promoIndex - 1 + promoSlides.length) % promoSlides.length;
                updatePromo();
            }

            function startPromoAuto() {
                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || promoSlides.length <= 1) {
                    return;
                }
                stopPromoAuto();
                promoTimer = window.setInterval(nextPromo, promoDelay);
            }

            function stopPromoAuto() {
                if (promoTimer) {
                    window.clearInterval(promoTimer);
                    promoTimer = null;
                }
            }

            function restartPromoAuto() {
                stopPromoAuto();
                startPromoAuto();
            }

            if (promoSlides.length > 0) {
                renderPromoDots();
                updatePromo();
                startPromoAuto();

                if (prevPromoBtn) {
                    prevPromoBtn.addEventListener('click', function () {
                        prevPromo();
                        restartPromoAuto();
                    });
                }

                if (nextPromoBtn) {
                    nextPromoBtn.addEventListener('click', function () {
                        nextPromo();
                        restartPromoAuto();
                    });
                }

                if (promoViewport) {
                    promoViewport.addEventListener('mouseenter', stopPromoAuto);
                    promoViewport.addEventListener('mouseleave', startPromoAuto);
                }

                document.addEventListener('visibilitychange', function () {
                    if (document.hidden) {
                        stopPromoAuto();
                    } else {
                        startPromoAuto();
                    }
                });
            }

        });
    </script>
    <script src="<?php echo asset_url('js/mobile-optimize.js'); ?>"></script>
    <script src="<?php echo asset_url('js/onboarding.js'); ?>"></script>
</body>
</html>
