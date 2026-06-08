<?php
require_once '../config/config.php';

$isLogged = isset($_SESSION['user_id']);
$isAdmin = $isLogged && (($_SESSION['role'] ?? '') === 'admin');

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$source = strtolower(trim((string)($_GET['source'] ?? '')));
$product = null;

if ($product_id > 0) {
    try {
        $queries = [];
        if ($source === 'ce') {
            $queries[] = "SELECT id, sku, name, description, unit_price, category, stock_quantity, NULL::text AS technical_specs, image_url, variants_json FROM marketplace_ce_products WHERE id = ? AND is_active = true AND NOT EXISTS (SELECT 1 FROM product_categories pc WHERE LOWER(pc.name) = LOWER(marketplace_ce_products.category) AND pc.is_active = false) LIMIT 1";
        } elseif ($source === 'product') {
            $queries[] = "SELECT id, sku, name, description, unit_price, category, stock_quantity, technical_specs, image_url, variants_json FROM products WHERE id = ? AND is_active = true AND NOT EXISTS (SELECT 1 FROM product_categories pc WHERE LOWER(pc.name) = LOWER(products.category) AND pc.is_active = false) LIMIT 1";
        } else {
            $queries[] = "SELECT id, sku, name, description, unit_price, category, stock_quantity, technical_specs, image_url, variants_json FROM products WHERE id = ? AND is_active = true AND NOT EXISTS (SELECT 1 FROM product_categories pc WHERE LOWER(pc.name) = LOWER(products.category) AND pc.is_active = false) LIMIT 1";
            $queries[] = "SELECT id, sku, name, description, unit_price, category, stock_quantity, NULL::text AS technical_specs, image_url, variants_json FROM marketplace_ce_products WHERE id = ? AND is_active = true AND NOT EXISTS (SELECT 1 FROM product_categories pc WHERE LOWER(pc.name) = LOWER(marketplace_ce_products.category) AND pc.is_active = false) LIMIT 1";
        }

        foreach ($queries as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            if ($product) {
                break;
            }
        }
    } catch (Exception $e) {
        $product = null;
    }
}

if (!$product) {
    http_response_code(404);
    header('Location: ' . ($source === 'ce' ? 'marketplace_ce.php' : 'index.php'));
    exit;
}

$rawSku = (string)($product['sku'] ?? '');
$displaySku = preg_replace('/^XLS-/i', '', $rawSku);

function image_priority_score($fileName): int {
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

function is_gallery_image_reference($value): bool {
    $value = trim((string)$value);
    if ($value === '') {
        return false;
    }

    return strpos($value, 'images/') === 0 || strpos($value, 'data:image/') === 0 || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $value) === 1;
}

function resolve_images_by_product_code($code, array $productRow = []): array {
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $baseDir = __DIR__ . '/images/products/by_code';
        if (is_dir($baseDir)) {
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
                        $scoreA = image_priority_score($a);
                        $scoreB = image_priority_score($b);
                        if ($scoreA === $scoreB) {
                            return strcmp((string)$a, (string)$b);
                        }
                        return $scoreA <=> $scoreB;
                    });

                    $cache[$dir] = array_map(function ($path) use ($dir) {
                        return 'images/products/by_code/' . $dir . '/' . basename($path);
                    }, $matches);
                }
            }
        }
    }

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

    if (isset($pdo)) {
        foreach (['products', 'marketplace_ce_products'] as $table) {
            try {
                $stmt = $pdo->prepare("SELECT image_url, variants_json FROM {$table} WHERE sku = ? LIMIT 1");
                $stmt->execute([$code]);
                $row = $stmt->fetch();
                if (!$row) {
                    continue;
                }

                $rowImage = trim((string)($row['image_url'] ?? ''));
                if ($rowImage !== '' && $rowImage !== 'images/products/default-product.svg') {
                    $mergeImage($rowImage);
                }

                if (!empty($row['variants_json'])) {
                    $decodedRow = json_decode((string)$row['variants_json'], true);
                    if (is_array($decodedRow)) {
                        foreach ($decodedRow as $item) {
                            $itemStr = trim((string)$item);
                            if (is_gallery_image_reference($itemStr)) {
                                $mergeImage($itemStr);
                            }
                        }
                    }
                }
            } catch (Exception $ignored) {
            }
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

$productName = decode_legacy_entities((string)($product['name'] ?? ''));
$productDescription = decode_legacy_entities((string)($product['description'] ?? ''));
$productTechnicalSpecs = decode_legacy_entities((string)($product['technical_specs'] ?? ''));
$productCategory = decode_legacy_entities((string)($product['category'] ?? ''));
$imagePath = !empty($product['image_url']) ? $product['image_url'] : 'images/products/default-product.svg';
$galleryImages = catalog_resolve_gallery_images_by_sku($displaySku, $product, $pdo);

$variants = [];
if (!empty($product['variants_json'])) {
    $decoded = json_decode($product['variants_json'], true);
    if (is_array($decoded)) {
        // Filter out image paths (which contain 'images/', file extensions, or base64 data URIs)
        // Keep only real variant names (like colors, sizes, conditions, etc.)
        foreach ($decoded as $item) {
            $itemStr = (string)$item;
            // Skip if it looks like an image path or base64 data URI
            if (strpos($itemStr, 'images/') === false && 
                strpos($itemStr, 'data:image/') === false &&
                strpos($itemStr, '.jpg') === false && 
                strpos($itemStr, '.jpeg') === false && 
                strpos($itemStr, '.png') === false && 
                strpos($itemStr, '.gif') === false && 
                strpos($itemStr, '.webp') === false &&
                strpos($itemStr, '.svg') === false) {
                $variants[] = $item;
            }
        }
    }
}

$stock = (int)($product['stock_quantity'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?> - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css?v=2.2">
    <link rel="stylesheet" href="css/theme.css?v=2.5">
    <link rel="stylesheet" href="css/responsive-complete.css?v=2.2">
    <style>
        /* ===== Product Detail Page — Premium Redesign ===== */
        body {
            background: #08080a !important;
            color: #ffffff !important;
        }

        .product-detail-page main {
            padding: 2.5rem 1.5rem !important;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Breadcrumb navigation */
        .breadcrumb {
            margin-bottom: 2rem !important;
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            color: #888888 !important;
            font-size: 0.9rem !important;
            font-weight: 500 !important;
        }

        .breadcrumb a {
            color: var(--theme-accent, #ff7f00) !important;
            text-decoration: none !important;
            font-weight: 700 !important;
            transition: all 0.2s ease !important;
        }

        .breadcrumb a:hover {
            color: #ffa522 !important;
            text-decoration: underline !important;
        }

        /* Detail container card */
        .product-detail-hero {
            display: grid !important;
            grid-template-columns: 1.1fr 0.9fr !important;
            gap: 3rem !important;
            padding: 2.5rem !important;
            background: #111111 !important;
            border: 1px solid #222222 !important;
            border-radius: 24px !important;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6) !important;
            margin-bottom: 2.5rem !important;
        }

        /* Gallery */
        .detail-gallery {
            display: flex !important;
            flex-direction: column !important;
            gap: 1.25rem !important;
        }

        .detail-main-image {
            position: relative !important;
            width: 100% !important;
            aspect-ratio: 1.2 !important;
            background: #0a0a0c !important;
            border: 1px solid #222222 !important;
            border-radius: 16px !important;
            overflow: hidden !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.6) !important;
        }

        .detail-main-image img {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
            padding: 1.5rem !important;
            transition: transform 0.3s ease !important;
        }

        .detail-main-image:hover img {
            transform: scale(1.03) !important;
        }

        .detail-thumbnails {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)) !important;
            gap: 0.75rem !important;
        }

        .detail-thumbnail {
            aspect-ratio: 1 !important;
            border-radius: 10px !important;
            overflow: hidden !important;
            cursor: pointer !important;
            border: 1px solid #222222 !important;
            transition: all 0.2s ease !important;
            background: #0d0d0f !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0.25rem !important;
        }

        .detail-thumbnail img {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
            padding: 0.25rem !important;
        }

        .detail-thumbnail:hover {
            border-color: #444444 !important;
            transform: translateY(-2px) !important;
        }

        .detail-thumbnail.active {
            border-color: var(--theme-accent, #ff7f00) !important;
            box-shadow: 0 0 10px rgba(255, 127, 0, 0.25) !important;
            background: rgba(255, 127, 0, 0.04) !important;
        }

        /* Product Info panel */
        .product-info {
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-start !important;
            gap: 1.5rem !important;
        }

        .detail-header {
            margin-bottom: 0 !important;
        }

        .detail-header .catalog-tag {
            background: rgba(255, 127, 0, 0.1) !important;
            border: 1px solid rgba(255, 127, 0, 0.25) !important;
            color: var(--theme-accent, #ff7f00) !important;
            padding: 0.35rem 0.95rem !important;
            border-radius: 999px !important;
            font-size: 0.78rem !important;
            font-weight: 700 !important;
            width: fit-content !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            margin-bottom: 1rem !important;
        }

        .detail-header h1 {
            margin: 0 0 0.5rem 0 !important;
            font-size: 2.25rem !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            letter-spacing: -0.02em !important;
            line-height: 1.25 !important;
            background: linear-gradient(90deg, #ffffff, #ffb347) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
        }

        .detail-sku {
            color: #666666 !important;
            font-size: 0.9rem !important;
            margin-bottom: 0 !important;
            font-weight: 500 !important;
        }

        .detail-sku strong {
            color: #888888 !important;
        }

        /* Price & Stock Box */
        .detail-price-box {
            background: radial-gradient(circle at 10% 20%, rgba(255, 127, 0, 0.08), transparent 60%), #0d0d0f !important;
            border: 1px solid #222222 !important;
            border-radius: 16px !important;
            padding: 1.5rem !important;
            margin-bottom: 0 !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
        }

        .detail-price {
            font-size: 2.25rem !important;
            font-weight: 800 !important;
            color: var(--theme-accent, #ff7f00) !important;
            margin-bottom: 0.75rem !important;
            letter-spacing: -0.01em !important;
        }

        .detail-stock {
            font-size: 0.85rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.03em !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 0.35rem 0.85rem !important;
            border-radius: 999px !important;
        }

        .detail-stock.in-stock {
            background: rgba(46, 204, 113, 0.1) !important;
            border: 1px solid rgba(46, 204, 113, 0.25) !important;
            color: #2ecc71 !important;
        }

        .detail-stock.low-stock {
            background: rgba(241, 196, 15, 0.1) !important;
            border: 1px solid rgba(241, 196, 15, 0.25) !important;
            color: #f1c40f !important;
        }

        .detail-stock.out-of-stock {
            background: rgba(231, 76, 60, 0.1) !important;
            border: 1px solid rgba(231, 76, 60, 0.25) !important;
            color: #e74c3c !important;
        }

        /* Description */
        .detail-description {
            margin-bottom: 0 !important;
        }

        .detail-description h3 {
            margin: 0 0 0.75rem 0 !important;
            color: #ffffff !important;
            font-size: 1.1rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            border-left: 3px solid var(--theme-accent, #ff7f00) !important;
            padding-left: 0.6rem !important;
            line-height: 1.2 !important;
        }

        .detail-description p {
            margin: 0 !important;
            color: #aaaaaa !important;
            line-height: 1.6 !important;
            font-size: 1rem !important;
        }

        /* Technical specs block */
        .detail-specs {
            margin-bottom: 0 !important;
        }

        .detail-specs h3 {
            margin: 0 0 0.75rem 0 !important;
            color: #ffffff !important;
            font-size: 1.1rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            border-left: 3px solid var(--theme-accent, #ff7f00) !important;
            padding-left: 0.6rem !important;
            line-height: 1.2 !important;
        }

        .detail-specs p {
            margin: 0 !important;
            color: #aaaaaa !important;
            line-height: 1.6 !important;
            font-family: inherit !important;
            font-size: 1rem !important;
            background: #0d0d0f !important;
            border: 1px solid #222222 !important;
            border-radius: 12px !important;
            padding: 1rem 1.25rem !important;
            white-space: pre-line !important;
        }

        /* Variants */
        .detail-variants {
            margin-bottom: 0 !important;
        }

        .detail-variants h3 {
            margin: 0 0 0.75rem 0 !important;
            color: #ffffff !important;
            font-size: 1.1rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            border-left: 3px solid var(--theme-accent, #ff7f00) !important;
            padding-left: 0.6rem !important;
            line-height: 1.2 !important;
        }

        .variants-list {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 0.5rem !important;
        }

        .variant-pill {
            background: #1e1e24 !important;
            border: 1px solid #2a2a35 !important;
            color: #ffffff !important;
            border-radius: 8px !important;
            padding: 0.4rem 0.8rem !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            transition: all 0.2s ease !important;
            cursor: pointer !important;
        }

        .variant-pill:hover, .variant-pill.active {
            border-color: var(--theme-accent, #ff7f00) !important;
            background: rgba(255, 127, 0, 0.1) !important;
            color: var(--theme-accent, #ff7f00) !important;
        }

        /* Actions panel */
        .detail-actions {
            display: flex !important;
            gap: 1rem !important;
            flex-wrap: wrap !important;
            margin-top: 1rem !important;
        }

        .detail-actions .btn-primary {
            flex-grow: 1 !important;
            background: linear-gradient(90deg, #ff6600, #ff9500) !important;
            border: none !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            padding: 0.85rem 2rem !important;
            border-radius: 999px !important;
            font-size: 1rem !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 4px 12px rgba(255, 102, 0, 0.25) !important;
            text-align: center !important;
        }

        .detail-actions .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 18px rgba(255, 102, 0, 0.4) !important;
            background: linear-gradient(90deg, #ff7711, #ffa522) !important;
        }

        .detail-actions .btn-secondary {
            background: #1a1a1a !important;
            border: 1px solid #2a2a2a !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            padding: 0.85rem 2rem !important;
            border-radius: 999px !important;
            font-size: 1rem !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            text-align: center !important;
        }

        .detail-actions .btn-secondary:hover {
            background: var(--theme-accent, #ff7f00) !important;
            border-color: var(--theme-accent, #ff7f00) !important;
            box-shadow: 0 4px 12px rgba(255, 127, 0, 0.3) !important;
        }

        /* Lightbox modal modifications */
        .modal-lightbox {
            background: rgba(0, 0, 0, 0.95) !important;
            backdrop-filter: blur(10px) !important;
        }

        .lightbox-close {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.1) !important;
            border-radius: 999px !important;
            width: 2.5rem !important;
            height: 2.5rem !important;
            line-height: 2.5rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.2s ease !important;
        }

        .lightbox-close:hover {
            background: #ef4444 !important;
        }

        .lightbox-nav {
            background: rgba(255, 255, 255, 0.1) !important;
            border-radius: 999px !important;
            width: 3rem !important;
            height: 3rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.2s ease !important;
        }

        .lightbox-nav:hover {
            background: var(--theme-accent, #ff7f00) !important;
            box-shadow: 0 0 10px rgba(255, 127, 0, 0.4) !important;
        }

        @media (max-width: 992px) {
            .product-detail-hero {
                grid-template-columns: 1fr !important;
                gap: 2rem !important;
                padding: 1.5rem !important;
            }
        }
    </style>
</head>
<body class="product-detail-page">
<header>
    <div class="header-content">
        <a href="index.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
                <nav class="nav-menu">
            <a href="index.php">Catálogo</a>
            <a href="marketplace_ce.php">Marketplace CE</a>
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
                        <a href="analytics.php">Estadísticas</a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
    </div>
    <div class="user-menu">

        <a href="index.php" class="btn btn-small btn-ghost">Volver al catálogo</a>
    </div>
</header>

<main>
    <div class="container-fluid">
        <div class="breadcrumb">
            <a href="index.php">Catálogo</a>
            <span>/</span>
            <span><?php echo htmlspecialchars($productCategory !== '' ? $productCategory : 'General', ENT_QUOTES, 'UTF-8'); ?></span>
            <span>/</span>
            <span><?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <div class="product-detail-hero">
            <div class="detail-gallery">
                <div class="detail-main-image" data-lightbox-trigger>
                    <img id="mainImage" src="<?php echo htmlspecialchars($galleryImages[0], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <?php if (count($galleryImages) > 1): ?>
                    <div class="detail-thumbnails" data-thumbnails-container>
                        <?php foreach ($galleryImages as $idx => $image): ?>
                            <div class="detail-thumbnail <?php echo $idx === 0 ? 'active' : ''; ?>" data-thumbnail data-image="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>">
                                <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="Imagen <?php echo $idx + 1; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-info">
                <div>
                    <div class="detail-header">
                        <span class="catalog-tag"><?php echo htmlspecialchars($productCategory !== '' ? $productCategory : 'General', ENT_QUOTES, 'UTF-8'); ?></span>
                        <h1><?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <div class="detail-sku"><strong>Código:</strong> <?php echo htmlspecialchars($displaySku, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="detail-price-box">
                        <div class="detail-price">$<?php echo number_format((float)$product['unit_price'], 2, '.', ','); ?></div>
                        <div class="detail-stock <?php echo $stock > 10 ? 'in-stock' : ($stock > 0 ? 'low-stock' : 'out-of-stock'); ?>">
                            <span>📦</span>
                            <span>
                                <?php if ($stock > 10): ?>
                                    Stock disponible (<?php echo $stock; ?> unidades)
                                <?php elseif ($stock > 0): ?>
                                    Stock bajo (<?php echo $stock; ?> unidades)
                                <?php else: ?>
                                    Agotado
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($productDescription !== ''): ?>
                        <div class="detail-description">
                            <h3>Descripción</h3>
                            <p><?php echo htmlspecialchars($productDescription, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($productTechnicalSpecs !== ''): ?>
                        <div class="detail-specs">
                            <h3>Especificaciones técnicas</h3>
                            <p><?php echo htmlspecialchars($productTechnicalSpecs, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="detail-variants">
                        <h3>Variantes disponibles</h3>
                        <div class="variants-list">
                            <?php if (!empty($variants)): ?>
                                <?php foreach ($variants as $variant): ?>
                                    <span class="variant-pill"><?php echo htmlspecialchars((string)$variant, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="variant-pill">Modelo Estandar</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="detail-actions">
                    <button
                        type="button"
                        class="btn btn-primary"
                        data-add-product
                        data-id="<?php echo (int)$product['id']; ?>"
                        data-sku="<?php echo htmlspecialchars($displaySku, ENT_QUOTES, 'UTF-8'); ?>"
                        data-name="<?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>"
                        data-image="<?php echo htmlspecialchars($galleryImages[0] ?? $imagePath, ENT_QUOTES, 'UTF-8'); ?>"
                        data-price="<?php echo (float)$product['unit_price']; ?>"
                        <?php echo $stock <= 0 ? 'disabled' : ''; ?>>
                        <?php echo $stock <= 0 ? 'Producto Agotado' : 'Agregar al Carrito'; ?>
                    </button>
                    <a href="cart.php" class="btn btn-secondary">Ver carrito</a>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Lightbox Modal -->
<div class="modal-lightbox" id="lightboxModal">
    <div class="lightbox-content">
        <button class="lightbox-close" id="lightboxClose">&times;</button>
        <?php if (count($galleryImages) > 1): ?>
            <button class="lightbox-nav lightbox-prev" id="lightboxPrev">&#10094;</button>
            <button class="lightbox-nav lightbox-next" id="lightboxNext">&#10095;</button>
        <?php endif; ?>
        <img id="lightboxImage" class="lightbox-image" src="" alt="">
    </div>
</div>

<script src="js/catalog.js"></script>
<script src="js/main.js?v=2.6"></script>
<script>
    (function () {
        const galleryImages = <?php echo json_encode($galleryImages); ?>;
        let currentImageIdx = 0;

        function setMainImage(src) {
            document.getElementById('mainImage').src = src;
            currentImageIdx = galleryImages.indexOf(src);
        }

        // Thumbnail clicks
        document.querySelectorAll('[data-thumbnail]').forEach((thumb) => {
            thumb.addEventListener('click', function () {
                const src = this.dataset.image;
                setMainImage(src);
                document.querySelectorAll('[data-thumbnail]').forEach((t) => t.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Lightbox
        const modal = document.getElementById('lightboxModal');
        const lightboxImage = document.getElementById('lightboxImage');
        const lightboxClose = document.getElementById('lightboxClose');
        const lightboxPrev = document.getElementById('lightboxPrev');
        const lightboxNext = document.getElementById('lightboxNext');

        function openLightbox(idx) {
            currentImageIdx = idx;
            lightboxImage.src = galleryImages[idx];
            modal.classList.add('active');
        }

        function closeLightbox() {
            modal.classList.remove('active');
        }

        function nextImage() {
            currentImageIdx = (currentImageIdx + 1) % galleryImages.length;
            lightboxImage.src = galleryImages[currentImageIdx];
        }

        function prevImage() {
            currentImageIdx = (currentImageIdx - 1 + galleryImages.length) % galleryImages.length;
            lightboxImage.src = galleryImages[currentImageIdx];
        }

        document.getElementById('mainImage').addEventListener('click', () => openLightbox(0));
        lightboxClose.addEventListener('click', closeLightbox);
        if (lightboxPrev) lightboxPrev.addEventListener('click', prevImage);
        if (lightboxNext) lightboxNext.addEventListener('click', nextImage);

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (!modal.classList.contains('active')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') prevImage();
            if (e.key === 'ArrowRight') nextImage();
        });

        // Click outside to close
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeLightbox();
        });
    })();

    // Persist add-to-cart from detail page.
    document.querySelectorAll('[data-add-product]').forEach((btn) => {
        btn.addEventListener('click', function () {
            const storageKey = 'truper_cart';
            let cart = [];
            try {
                cart = JSON.parse(localStorage.getItem(storageKey) || '[]');
            } catch (_) {
                cart = [];
            }

            const sku = this.dataset.sku;
            const existing = cart.find((item) => item.sku === sku);
            if (existing) {
                existing.quantity = Number(existing.quantity || 1) + 1;
            } else {
                cart.push({
                    id: this.dataset.id,
                    sku: this.dataset.sku,
                    name: this.dataset.name,
                    image_url: this.dataset.image || 'images/products/default-product.svg',
                    unit_price: Number(this.dataset.price || 0),
                    quantity: 1
                });
            }

            localStorage.setItem(storageKey, JSON.stringify(cart));
            if (typeof showAlert === 'function') {
                showAlert('Producto agregado al carrito', 'success');
            }
        });
    });
</script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
