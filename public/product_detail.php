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
            $queries[] = "SELECT id, sku, name, description, unit_price, category, stock_quantity, NULL::text AS technical_specs, image_url, variants_json FROM marketplace_ce_products WHERE id = ? AND is_active = true LIMIT 1";
        } elseif ($source === 'product') {
            $queries[] = "SELECT id, sku, name, description, unit_price, category, stock_quantity, technical_specs, image_url, variants_json FROM products WHERE id = ? AND is_active = true LIMIT 1";
        } else {
            $queries[] = "SELECT id, sku, name, description, unit_price, category, stock_quantity, technical_specs, image_url, variants_json FROM products WHERE id = ? AND is_active = true LIMIT 1";
            $queries[] = "SELECT id, sku, name, description, unit_price, category, stock_quantity, NULL::text AS technical_specs, image_url, variants_json FROM marketplace_ce_products WHERE id = ? AND is_active = true LIMIT 1";
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
    <link rel="stylesheet" href="css/styles.css?v=2.1">
    <link rel="stylesheet" href="css/theme.css?v=2.1">
    <link rel="stylesheet" href="css/responsive-complete.css">
    <style>
        .product-detail-hero {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
            background: var(--ui-surface);
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .detail-gallery {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .detail-main-image {
            position: relative;
            width: 100%;
            aspect-ratio: 1;
            background: var(--ui-surface-soft);
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .detail-main-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 1rem;
        }

        .detail-thumbnails {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 0.5rem;
        }

        .detail-thumbnail {
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border 0.2s;
            background: var(--ui-surface-soft);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .detail-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 0.5rem;
        }

        .detail-thumbnail.active {
            border-color: var(--color-naranja);
        }

        .product-info {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .detail-header {
            margin-bottom: 1.5rem;
        }

        .detail-header .catalog-tag {
            margin-bottom: 0.75rem;
        }

        .detail-header h1 {
            margin: 0 0 0.5rem;
            font-size: 2rem;
            color: var(--ui-text);
        }

        .detail-sku {
            color: var(--ui-text-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .detail-price-box {
            background: rgba(255, 127, 0, 0.1);
            border: 1px solid rgba(255, 127, 0, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .detail-price {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--color-naranja);
            margin-bottom: 0.5rem;
        }

        .detail-stock {
            font-size: 0.9rem;
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .detail-stock.in-stock {
            color: #10b981;
        }

        .detail-stock.low-stock {
            color: #f59e0b;
        }

        .detail-stock.out-of-stock {
            color: #ef4444;
        }

        .detail-description {
            margin-bottom: 1.5rem;
        }

        .detail-description h3 {
            margin: 0 0 0.75rem;
            color: var(--ui-text);
            font-size: 1rem;
        }

        .detail-description p {
            margin: 0;
            color: var(--ui-text-secondary);
            line-height: 1.6;
        }

        .detail-specs {
            margin-bottom: 1.5rem;
        }

        .detail-specs h3 {
            margin: 0 0 0.75rem;
            color: var(--ui-text);
            font-size: 1rem;
        }

        .detail-specs p {
            margin: 0;
            color: var(--ui-text-secondary);
            line-height: 1.6;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .detail-variants {
            margin-bottom: 1.5rem;
        }

        .detail-variants h3 {
            margin: 0 0 0.75rem;
            color: var(--ui-text);
            font-size: 1rem;
        }

        .variants-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .detail-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .breadcrumb {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--ui-text-muted);
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: var(--color-naranja);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .product-detail-hero {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1rem;
            }

            .detail-header h1 {
                font-size: 1.5rem;
            }

            .detail-price {
                font-size: 1.5rem;
            }

            .detail-actions {
                flex-direction: column;
            }

            .detail-actions .btn {
                width: 100%;
            }
        }

        .modal-lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            padding: 2rem;
            overflow: auto;
        }

        .modal-lightbox.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lightbox-content {
            position: relative;
            max-width: 90%;
            max-height: 90vh;
        }

        .lightbox-image {
            max-width: 100%;
            max-height: 85vh;
            object-fit: contain;
        }

        .lightbox-close {
            position: absolute;
            top: -2rem;
            right: 0;
            background: transparent;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 0;
            width: 2rem;
            height: 2rem;
        }

        .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 1rem;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .lightbox-nav:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        .lightbox-prev {
            left: -3.5rem;
        }

        .lightbox-next {
            right: -3.5rem;
        }

        @media (max-width: 768px) {
            .modal-lightbox {
                padding: 1rem;
            }

            .lightbox-close {
                top: 1rem;
                right: 1rem;
            }

            .lightbox-nav {
                top: 50%;
                transform: translateY(-50%);
            }

            .lightbox-prev {
                left: 0;
            }

            .lightbox-next {
                right: 0;
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
            <a href="cart.php">Carrito</a>
            <?php if ($isAdmin): ?><a href="admin_supply.php?nocache=true">Abastecimiento</a><?php endif; ?>
            <?php if ($isAdmin): ?><a href="tickets.php">Tickets</a><?php endif; ?>
            <?php if ($isLogged): ?>
                <a href="orders.php">Pedidos</a>
                <a href="wholesale.php">Mayoreo</a>
                <?php if ($isAdmin): ?><a href="cashier.php">Caja</a><?php endif; ?>
                <a href="dashboard.php">Dashboard</a>
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
<script src="js/main.js"></script>
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
