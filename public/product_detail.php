<?php
require_once '../config/config.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

if ($product_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, sku, name, description, unit_price, category, stock_quantity, technical_specs, image_url, variants_json FROM products WHERE id = ? AND is_active = true LIMIT 1");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
    } catch (Exception $e) {
        $product = null;
    }
}

if (!$product) {
    http_response_code(404);
    header('Location: index.php');
    exit;
}

$rawSku = (string)($product['sku'] ?? '');
$displaySku = preg_replace('/^XLS-/i', '', $rawSku);

$imagePath = !empty($product['image_url']) ? $product['image_url'] : 'images/products/default-product.svg';
$galleryImages = [];

// Resolver imágenes por código de producto
$baseDir = __DIR__ . '/images/products/by_code';
if (is_dir($baseDir)) {
    $fullDir = $baseDir . '/' . $displaySku;
    if (is_dir($fullDir)) {
        $matches = glob($fullDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
        if (!empty($matches)) {
            usort($matches, function ($a, $b) {
                $scoreA = image_priority_score($a);
                $scoreB = image_priority_score($b);
                return $scoreA === $scoreB ? strcmp($a, $b) : $scoreA <=> $scoreB;
            });
            $galleryImages = array_map(function ($path) use ($displaySku) {
                return 'images/products/by_code/' . $displaySku . '/' . basename($path);
            }, $matches);
        }
    }
}

if (empty($galleryImages)) {
    $galleryImages = [$imagePath];
} else if (!empty($product['image_url']) && $product['image_url'] !== 'images/products/default-product.svg') {
    if (!in_array($product['image_url'], $galleryImages, true)) {
        array_unshift($galleryImages, $product['image_url']);
    }
}

$variants = [];
if (!empty($product['variants_json'])) {
    $decoded = json_decode($product['variants_json'], true);
    if (is_array($decoded)) {
        $variants = $decoded;
    }
}

$stock = (int)($product['stock_quantity'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?> - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
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
            <a href="orders.php">Mi Carrito</a>
            <a href="account.php">Mi Cuenta</a>
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
            <span><?php echo htmlspecialchars($product['category'] ?: 'General', ENT_QUOTES, 'UTF-8'); ?></span>
            <span>/</span>
            <span><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <div class="product-detail-hero">
            <div class="detail-gallery">
                <div class="detail-main-image" data-lightbox-trigger>
                    <img id="mainImage" src="<?php echo htmlspecialchars($galleryImages[0], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">
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
                        <span class="catalog-tag"><?php echo htmlspecialchars($product['category'] ?: 'General', ENT_QUOTES, 'UTF-8'); ?></span>
                        <h1><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                        <div class="detail-sku"><strong>Código:</strong> <?php echo htmlspecialchars($displaySku, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="detail-price-box">
                        <div class="detail-price">$<?php echo number_format((float)$product['unit_price'], 0, ',', '.'); ?></div>
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

                    <?php if (!empty($product['description'])): ?>
                        <div class="detail-description">
                            <h3>Descripción</h3>
                            <p><?php echo htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($product['technical_specs'])): ?>
                        <div class="detail-specs">
                            <h3>Especificaciones técnicas</h3>
                            <p><?php echo htmlspecialchars($product['technical_specs'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($variants)): ?>
                        <div class="detail-variants">
                            <h3>Variantes disponibles</h3>
                            <div class="variants-list">
                                <?php foreach ($variants as $variant): ?>
                                    <span class="variant-pill"><?php echo htmlspecialchars((string)$variant, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="detail-actions">
                    <button
                        type="button"
                        class="btn btn-primary"
                        data-add-product
                        data-id="<?php echo (int)$product['id']; ?>"
                        data-sku="<?php echo htmlspecialchars($displaySku, ENT_QUOTES, 'UTF-8'); ?>"
                        data-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-price="<?php echo (float)$product['unit_price']; ?>"
                        <?php echo $stock <= 0 ? 'disabled' : ''; ?>>
                        <?php echo $stock <= 0 ? 'Producto Agotado' : 'Agregar al Carrito'; ?>
                    </button>
                    <a href="orders.php" class="btn btn-secondary">Ver carrito</a>
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

    // Reuse cart functionality from catalog.js
    document.querySelectorAll('[data-add-product]').forEach((btn) => {
        btn.addEventListener('click', function () {
            if (typeof addToCart === 'function') {
                addToCart({
                    id: this.dataset.id,
                    sku: this.dataset.sku,
                    name: this.dataset.name,
                    unit_price: this.dataset.price,
                });
            }
        });
    });
</script>
</body>
</html>
