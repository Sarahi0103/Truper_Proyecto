<?php
require_once '../config/config.php';

$isLogged  = isset($_SESSION['user_id']);
$isAdmin   = $isLogged && (($_SESSION['role'] ?? '') === 'admin');

$clientTicketCode   = 'PUBLICO';
$clientTicketNumber = $isLogged ? (string)($_SESSION['user_id'] ?? '0') : '0';
if ($isLogged && db_column_exists('users', 'user_code')) {
    try {
        $stmtUC = $pdo->prepare("SELECT COALESCE(user_code, '') AS user_code FROM users WHERE id = ? LIMIT 1");
        $stmtUC->execute([$_SESSION['user_id']]);
        $ucRow = $stmtUC->fetch();
        if (!empty($ucRow['user_code'])) { $clientTicketCode = (string)$ucRow['user_code']; }
    } catch (Exception $ignored) {}
}

$marketplaceItems = [];
$allCategories    = [];
try {
    $pdo->exec("ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS image_url TEXT");
    $pdo->exec("ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS variants_json TEXT");
    $pdo->exec("ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS condition_label VARCHAR(80) DEFAULT 'Seminuevo'");

    $hasUnitPrice = db_column_exists('marketplace_ce_products', 'unit_price');
    $hasSellPrice = db_column_exists('marketplace_ce_products', 'sell_price');
    $priceExpr = '0';
    if ($hasUnitPrice && $hasSellPrice) {
        $priceExpr = 'COALESCE(unit_price, sell_price, 0)';
    } elseif ($hasUnitPrice) {
        $priceExpr = 'COALESCE(unit_price, 0)';
    } elseif ($hasSellPrice) {
        $priceExpr = 'COALESCE(sell_price, 0)';
    }

    $nameExpr = db_column_exists('marketplace_ce_products', 'name') ? 'name' : "''";
    $skuExpr = db_column_exists('marketplace_ce_products', 'sku') ? 'sku' : "''";
    $categoryExpr = db_column_exists('marketplace_ce_products', 'category') ? 'category' : "'Marketplace CE'";
    $descriptionExpr = db_column_exists('marketplace_ce_products', 'description') ? 'description' : "''";
    $conditionExpr = db_column_exists('marketplace_ce_products', 'condition_label') ? 'condition_label' : "'Seminuevo'";
    $stockExpr = db_column_exists('marketplace_ce_products', 'stock_quantity') ? 'stock_quantity' : '0';
    $imageExpr = db_column_exists('marketplace_ce_products', 'image_url') ? 'image_url' : "'images/products/default-product.svg'";
    $variantsExpr = db_column_exists('marketplace_ce_products', 'variants_json') ? 'variants_json' : "'[]'";

    $productsVisibilityWhere = '';
    if (db_column_exists('marketplace_ce_products', 'is_active')) {
        $productsVisibilityWhere = " WHERE (CASE WHEN is_active IS NULL THEN 1 WHEN LOWER(CAST(is_active AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
    } elseif (db_column_exists('marketplace_ce_products', 'active')) {
        $productsVisibilityWhere = " WHERE active = 1";
    } else {
        $productsVisibilityWhere = " WHERE 1 = 1";
    }
    $productsVisibilityWhere .= " AND NOT EXISTS (
        SELECT 1 FROM product_categories pc 
        WHERE LOWER(pc.name) = LOWER(marketplace_ce_products.category) 
        AND pc.is_active = false
    )";

    $sqlCe = "SELECT id, {$nameExpr} AS name, {$skuExpr} AS sku, {$priceExpr} AS unit_price, {$categoryExpr} AS category, {$descriptionExpr} AS description, {$conditionExpr} AS condition_label, {$stockExpr} AS stock_quantity, {$imageExpr} AS image_url, {$variantsExpr} AS variants_json FROM marketplace_ce_products" . $productsVisibilityWhere . " ORDER BY name LIMIT 300";
    $stmtCe = $pdo->prepare($sqlCe);
    $stmtCe->execute();
    $marketplaceItems = $stmtCe->fetchAll();

    // Intentar cargar categorías directamente de la base de datos para mostrar incluso las vacías
    $categoriesTotals = [];
    try {
        $catStmt = $pdo->query("SELECT name FROM product_categories WHERE is_active = true ORDER BY sort_order ASC, name ASC");
        if ($catStmt) {
            $dbCats = $catStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($dbCats as $c) {
                $c = trim((string)$c);
                if ($c !== '') {
                    $catNorm = strtr(function_exists('mb_strtolower') ? mb_strtolower($c, 'UTF-8') : strtolower($c), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
                    $categoriesTotals[$catNorm] = $c;
                }
            }
        }
    } catch (Exception $ig) { }

    // Fallback dinámico si la BD de categorías está vacía o falla
    if (empty($categoriesTotals)) {
        foreach ($marketplaceItems as $mi) {
            $cat = trim((string)($mi['category'] ?? ''));
            if ($cat && $cat !== 'Marketplace CE') {
                $catLower = function_exists('mb_strtolower') 
                    ? mb_strtolower($cat, 'UTF-8') 
                    : strtolower($cat);
                $catNorm = strtr($catLower, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
                if (!isset($categoriesTotals[$catNorm])) {
                    $categoriesTotals[$catNorm] = $cat;
                }
            }
        }
    }
    
    $allCategories = array_values($categoriesTotals);
} catch (Exception $e) {
    $marketplaceItems = [];
}

$whatsappPhone = function_exists('whatsapp_phone_digits') ? whatsapp_phone_digits() : '';

function marketplace_ce_gallery_images_by_sku(string $sku, array $itemRow = []): array {
    static $cache = null;
    global $pdo;

    $normalizedSku = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sku);
    $images = [];
    $mergeImage = static function (string $value) use (&$images): void {
        $value = trim($value);
        if ($value === '' || stripos($value, 'default-product.svg') !== false) {
            return;
        }
        if (!in_array($value, $images, true)) {
            $images[] = $value;
        }
    };

    $itemImage = trim((string)($itemRow['image_url'] ?? ''));
    if ($itemImage !== '') {
        $mergeImage($itemImage);
    }

    if (!empty($itemRow['variants_json'])) {
        $parsed = json_decode((string)$itemRow['variants_json'], true);
        if (is_array($parsed)) {
            foreach ($parsed as $value) {
                $value = trim((string)$value);
                if (strpos($value, 'images/') === 0 || strpos($value, 'data:image/') === 0 || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $value) === 1) {
                    $mergeImage($value);
                }
            }
        }
    }

    if (empty($images) && isset($pdo) && $pdo instanceof PDO && db_table_exists('marketplace_ce_products')) {
        try {
            $stmt = $pdo->prepare("SELECT image_url, variants_json FROM marketplace_ce_products WHERE sku = ? OR sku LIKE ? LIMIT 1");
            $stmt->execute([$sku, "%{$sku}%"]);
            $productRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            if (!empty($productRow['image_url'])) {
                $mergeImage((string)$productRow['image_url']);
            }

            if (!empty($productRow['variants_json'])) {
                $parsed = json_decode((string)$productRow['variants_json'], true);
                if (is_array($parsed)) {
                    foreach ($parsed as $value) {
                        $value = trim((string)$value);
                        if (strpos($value, 'images/') === 0 || strpos($value, 'data:image/') === 0 || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $value) === 1) {
                            $mergeImage($value);
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

    if (!empty($images)) {
        return $images;
    }

    if ($cache === null) {
        $cache = [];
        $galleryBase = __DIR__ . '/images/products/gallery';
        if (is_dir($galleryBase)) {
            foreach (scandir($galleryBase) ?: [] as $skuDir) {
                if ($skuDir === '.' || $skuDir === '..') {
                    continue;
                }
                $fullDir = $galleryBase . '/' . $skuDir;
                if (!is_dir($fullDir)) {
                    continue;
                }
                $diskImages = glob($fullDir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
                if (empty($diskImages)) {
                    continue;
                }
                $cache[$skuDir] = array_map(static function ($di) use ($skuDir) {
                    return 'images/products/gallery/' . $skuDir . '/' . basename($di);
                }, $diskImages);
            }
        }
    }

    return $normalizedSku !== '' && !empty($cache[$normalizedSku]) ? array_values($cache[$normalizedSku]) : [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace CE — Artículos de segunda mano | Truper</title>
    <meta name="description" content="Marketplace CE de Truper: herramientas y artículos de medio uso con precio accesible. Consulta disponibilidad y cotiza fácilmente.">
    <link rel="stylesheet" href="css/styles.css?v=2.2">
    <link rel="stylesheet" href="css/theme.css?v=2.5">
    <link rel="stylesheet" href="css/responsive-complete.css?v=2.2">
    <style>
        /* ===== Marketplace CE — Premium Redesign ===== */

        /* Premium mesh gradient background for catalog hero */
        .catalog-hero {
            background: radial-gradient(circle at 10% 20%, rgba(255, 127, 0, 0.15), transparent 45%),
                        radial-gradient(circle at 90% 80%, rgba(255, 127, 0, 0.08), transparent 45%),
                        linear-gradient(135deg, #0e0e10 0%, #17171a 100%) !important;
            border: 1px solid #222222 !important;
            border-radius: 24px !important;
            padding: 2.5rem 2.25rem !important;
            margin-bottom: 2rem !important;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6) !important;
            position: relative;
            overflow: hidden;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            text-align: center !important;
        }

        .catalog-hero h1 {
            font-size: 2.5rem !important;
            font-weight: 800 !important;
            letter-spacing: -0.02em !important;
            color: #ffffff !important;
            margin-bottom: 0.75rem !important;
            background: linear-gradient(90deg, #ffffff, #ffb347) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
        }

        .catalog-hero p {
            color: #aaaaaa !important;
            font-size: 1.1rem !important;
            max-width: 600px !important;
            line-height: 1.6 !important;
            text-align: center !important;
        }

        /* Toolbar and inputs */
        .catalog-toolbar {
            background: #111111 !important;
            border: 1px solid #222222 !important;
            border-radius: 16px !important;
            padding: 1.25rem !important;
            margin-bottom: 1.5rem !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }

        #ceSearch {
            width: 100%;
            box-sizing: border-box;
            padding: 0.8rem 1rem !important;
            border-radius: 10px !important;
            border: 1px solid #222222 !important;
            background: #0d0d0d !important;
            color: #ffffff !important;
            font-size: 1rem;
            outline: none;
            transition: all 0.2s ease !important;
        }

        #ceSearch::placeholder {
            color: #666666 !important;
        }

        #ceSearch:focus {
            border-color: var(--theme-accent, #ff7f00) !important;
            box-shadow: 0 0 0 3px rgba(255, 127, 0, 0.15) !important;
            background: #111111 !important;
        }

        /* Category Pill Buttons */
        .catalog-categories-top {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin-bottom: 1.5rem !important;
        }

        .catalog-categories-title {
            font-size: 1.2rem !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            margin-bottom: 1rem !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
        }

        .catalog-categories-actions {
            display: flex !important;
            gap: 0.5rem !important;
            overflow-x: auto !important;
            padding-bottom: 0.5rem !important;
            scrollbar-width: none !important;
        }

        .catalog-categories-actions::-webkit-scrollbar {
            display: none !important;
        }

        .catalog-categories-actions button {
            background: #111111 !important;
            border: 1px solid #222222 !important;
            color: #888888 !important;
            border-radius: 999px !important;
            padding: 0.65rem 1.35rem !important;
            font-weight: 600 !important;
            font-size: 0.9rem !important;
            transition: all 0.2s ease !important;
            cursor: pointer !important;
        }

        .catalog-categories-actions button:hover {
            background: #1a1a1a !important;
            color: #ffffff !important;
            border-color: #333333 !important;
        }

        .catalog-categories-actions button.active {
            background: var(--theme-accent, #ff7f00) !important;
            color: #ffffff !important;
            border-color: var(--theme-accent, #ff7f00) !important;
            box-shadow: 0 4px 12px rgba(255, 127, 0, 0.3) !important;
        }

        /* Product Cards Grid & Card Redesign */
        .catalog-grid-min {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)) !important;
            gap: 2rem !important;
            margin-top: 2rem !important;
        }

        .product-card-min {
            background: #111111 !important;
            border: 1px solid #222222 !important;
            border-radius: 20px !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            animation: ce-fade-in 0.35s ease both;
        }

        @keyframes ce-fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .product-card-min:hover {
            transform: translateY(-6px) !important;
            border-color: var(--theme-accent, #ff7f00) !important;
            box-shadow: 0 12px 36px rgba(255, 127, 0, 0.15), 0 12px 36px rgba(0, 0, 0, 0.6) !important;
        }

        .product-media {
            height: 220px !important;
            border-bottom: 1px solid #222222 !important;
            background: #0a0a0c !important;
            position: relative;
            overflow: hidden;
        }

        .product-gallery-image {
            object-fit: contain !important;
            width: 100% !important;
            height: 100% !important;
            padding: 1.25rem !important;
            transition: transform 0.5s ease !important;
        }

        .product-card-min:hover .product-gallery-image.active {
            transform: scale(1.05);
        }

        .product-content {
            padding: 1.5rem !important;
            display: flex !important;
            flex-direction: column !important;
            flex-grow: 1 !important;
        }

        .catalog-tag {
            background: rgba(255, 127, 0, 0.1) !important;
            border: 1px solid rgba(255, 127, 0, 0.25) !important;
            color: var(--theme-accent, #ff7f00) !important;
            padding: 0.25rem 0.75rem !important;
            border-radius: 999px !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            width: fit-content !important;
            text-transform: uppercase !important;
            letter-spacing: 0.03em !important;
            margin-bottom: 0.75rem !important;
        }

        .product-code-label {
            font-size: 0.8rem !important;
            color: #666666 !important;
            margin-bottom: 0.5rem !important;
        }

        .product-code-label strong {
            color: #888888 !important;
        }

        .product-title {
            font-size: 1.15rem !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            line-height: 1.4 !important;
            margin-bottom: 0.5rem !important;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 3.2rem;
        }

        .product-spec {
            font-size: 0.85rem !important;
            color: #777777 !important;
            line-height: 1.5 !important;
            margin-bottom: 1.25rem !important;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.5rem;
        }

        /* condition badge */
        .condition-tag {
            display: inline-block;
            padding: 0.25rem 0.75rem !important;
            border-radius: 999px !important;
            font-size: 0.72rem !important;
            font-weight: 700 !important;
            background: rgba(255,127,0,0.12) !important;
            color: var(--theme-accent, #ff7f00) !important;
            border: 1px solid rgba(255,127,0,0.3) !important;
            margin-bottom: 0.5rem !important;
            text-transform: uppercase !important;
            letter-spacing: 0.02em !important;
        }

        /* Stock Badges Capsule style */
        .stock-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            padding: 0.25rem 0.75rem !important;
            border-radius: 999px !important;
            font-size: 0.72rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.02em !important;
            margin-bottom: 0.75rem !important;
            width: fit-content;
        }

        .stock-badge.stock-ok {
            background: rgba(46, 204, 113, 0.1) !important;
            border: 1px solid rgba(46, 204, 113, 0.25) !important;
            color: #2ecc71 !important;
        }

        .stock-badge.stock-low {
            background: rgba(231, 76, 60, 0.1) !important;
            border: 1px solid rgba(231, 76, 60, 0.25) !important;
            color: #e74c3c !important;
        }

        .catalog-price {
            font-size: 1.35rem !important;
            font-weight: 800 !important;
            color: var(--theme-accent, #ff7f00) !important;
            margin-top: auto !important;
            margin-bottom: 0.75rem !important;
        }

        .product-actions {
            border-top: 1px solid #222222 !important;
            padding-top: 1rem !important;
            margin-top: 0.25rem !important;
        }

        .product-actions .btn-primary {
            width: 100% !important;
            background: linear-gradient(90deg, #ff6600, #ff9500) !important;
            border: none !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            padding: 0.6rem 1.25rem !important;
            border-radius: 999px !important;
            font-size: 0.88rem !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 4px 10px rgba(255, 102, 0, 0.2) !important;
        }

        .product-actions .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(255, 102, 0, 0.35) !important;
            background: linear-gradient(90deg, #ff7711, #ffa522) !important;
        }

        #ceCount {
            color: rgba(255,255,255,0.45);
            font-size: 0.85rem;
            margin-bottom: 1rem;
            letter-spacing: 0.02em;
        }
    </style>
</head>
<body class="catalog-minimal"
    data-client-code="<?php echo htmlspecialchars($clientTicketCode, ENT_QUOTES, 'UTF-8'); ?>"
    data-client-number="<?php echo htmlspecialchars($clientTicketNumber, ENT_QUOTES, 'UTF-8'); ?>">

    <header>
        <div class="header-content">
            <a href="/" class="logo"><img src="img/logo_truper.1.1.png" alt="Truper" style="height: 40px; width: auto; object-fit: contain;"></a>
            <nav class="nav-menu">
                <a href="/">Productos</a>
                <a href="/marketplace_ce.php" class="active">Marketplace CE</a>
                <?php if ($isLogged): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="/dashboard.php">Dashboard</a>
                            <a href="/orders.php">Pedidos</a>
                            <a href="/wholesale.php">Mayoreo</a>
                            <a href="/account.php#historyTab">Historial</a>
                            <a href="/profile.php">Perfil</a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="/cashier.php">Caja</a>
                            <a href="/admin_supply.php?nocache=true">Abastecimiento</a>
                            <a href="/tickets.php">Tickets</a>
                            <a href="/tasks.php">Tareas</a>
                            <a href="/analytics.php">Estadísticas</a>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
            <div class="header-actions">
                <?php if ($whatsappPhone): ?>
                <a href="https://wa.me/<?php echo htmlspecialchars($whatsappPhone,ENT_QUOTES,'UTF-8'); ?>?text=Hola%2C+me+interesa+un+art%C3%ADculo+del+Marketplace+CE"
                   target="_blank" rel="noopener" class="btn btn-secondary btn-small">Dudas por WhatsApp</a>
                <?php if (!$isLogged): ?>
                    <a href="/admin_login.php" class="btn btn-primary btn-small">Solo para administradores</a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <!-- HERO -->
        <section class="catalog-hero">
            <div class="module-badge module-main"><span class="module-glyph">CE</span> Segunda mano</div>
            <h1>Marketplace CE</h1>
            <p>Artículos de medio uso en buen estado: herramientas eléctricas, escaleras y más. Opciones accesibles con verificación del establecimiento.</p>
            <div style="margin-top: 12px;">
                <a href="/" class="btn btn-secondary btn-small">← Catálogo principal</a>
            </div>
        </section>

        <section class="catalog-shell">
            <!-- BÚSQUEDA (encima de categorías) -->
            <div class="catalog-toolbar">
                <input id="ceSearch" type="text" placeholder="Buscar por nombre, código o condición...">
            </div>

            <!-- CATEGORÍAS -->
            <?php if (!empty($allCategories)): ?>
            <div class="catalog-categories-top">
                <div class="catalog-categories-title">Categorías</div>
                <div class="catalog-categories-actions">
                    <button type="button" class="btn btn-ghost btn-small active" data-ce-cat="">Todos</button>
                    <?php foreach ($allCategories as $cat): ?>
                        <button type="button" class="btn btn-ghost btn-small" data-ce-cat="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div id="ceCount"></div>

            <!-- GRID -->
            <?php if (empty($marketplaceItems)): ?>
                <div style="text-align:center;padding:3rem 1rem;color:var(--theme-text-muted,#888);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:56px;height:56px;opacity:0.3;margin-bottom:0.75rem;display:block;margin-left:auto;margin-right:auto;"><path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 3h-8v4h8V3z"/></svg>
                    <p>Todavía no hay artículos CE publicados.</p>
                    <?php if ($isAdmin): ?>
                    <p><a href="/admin_supply.php" class="btn btn-primary btn-small">Agregar desde Abastecimiento › Marketplace CE</a></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="catalog-grid-min" id="ceGrid">
                    <?php foreach ($marketplaceItems as $item):
                        $itemSku   = htmlspecialchars((string)$item['sku'], ENT_QUOTES, 'UTF-8');
                        $itemName  = htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8');
                        $itemDesc  = htmlspecialchars((string)$item['description'], ENT_QUOTES, 'UTF-8');
                        $itemCond  = htmlspecialchars((string)($item['condition_label'] ?? 'Seminuevo'), ENT_QUOTES, 'UTF-8');
                        $itemCat   = htmlspecialchars((string)($item['category'] ?? 'Marketplace CE'), ENT_QUOTES, 'UTF-8');
                        $itemImg   = htmlspecialchars((string)($item['image_url'] ?: 'images/products/default-product.svg'), ENT_QUOTES, 'UTF-8');
                        $itemPrice = (float)($item['unit_price'] ?? 0);
                        $itemStock = (int)($item['stock_quantity'] ?? 0);
                        $stockClass = $itemStock <= 2 ? 'stock-low' : 'stock-ok';
                        $stockLabel = $itemStock <= 2 ? 'Pocas piezas: ' : 'Disponibles: ';

                        $images = marketplace_ce_gallery_images_by_sku((string)($item['sku'] ?? ''), $item);
                        if (empty($images) && !empty($item['variants_json'])) {
                            $parsed = json_decode($item['variants_json'], true);
                            if (is_array($parsed) && count($parsed) > 0) {
                                $nonBase64 = array_filter($parsed, fn($v) => strpos((string)$v, 'data:') !== 0);
                                $images = !empty($nonBase64) ? array_values($nonBase64) : $parsed;
                            }
                        }
                        if (empty($images)) {
                            $fallbackImg = (string)($item['image_url'] ?? 'images/products/default-product.svg');
                            $images = [$fallbackImg];
                        }
                    ?>
                    <article class="product-card-min"
                        data-ce-item
                        data-id="<?php echo (int)$item['id']; ?>"
                        data-name="<?php echo $itemName; ?>"
                        data-sku="<?php echo $itemSku; ?>"
                        data-category="<?php echo $itemCat; ?>"
                        data-condition="<?php echo $itemCond; ?>">
                        <div class="product-media" data-product-gallery>
                            <a href="product_detail.php?id=<?php echo (int)$item['id']; ?>&source=ce" class="product-media-link" aria-label="Ver detalle de <?php echo $itemName; ?>"></a>
                            <?php foreach ($images as $idx => $imgSrc): ?>
                                <img
                                    class="product-gallery-image <?php echo $idx === 0 ? 'active' : ''; ?>"
                                    src="<?php echo htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo $itemName; ?>"
                                    loading="lazy">
                            <?php endforeach; ?>
                            <?php if (count($images) > 1): ?>
                                <button type="button" class="gallery-nav gallery-prev" data-gallery-prev aria-label="Imagen anterior">&#10094;</button>
                                <button type="button" class="gallery-nav gallery-next" data-gallery-next aria-label="Imagen siguiente">&#10095;</button>
                                <div class="gallery-counter"><span data-gallery-current>1</span>/<?php echo count($images); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="product-content">
                            <div class="catalog-tag"><?php echo $itemCat; ?></div>
                            <div class="product-code-label"><strong>Código:</strong> <strong><?php echo $itemSku; ?></strong></div>
                            <h3 class="product-title"><?php echo $itemName; ?></h3>
                            <p class="product-spec"><?php echo $itemDesc ?: 'Artículo de segunda mano verificado'; ?></p>
                            <div>
                                <span class="condition-tag"><?php echo $itemCond; ?></span>
                            </div>
                            <span class="stock-badge <?php echo $stockClass; ?>">
                                <?php echo $stockLabel . $itemStock; ?>
                            </span>
                            <div class="catalog-price">$<?php echo number_format($itemPrice, 2, '.', ','); ?></div>
                            <div class="product-actions">
                                <button
                                    type="button"
                                    class="btn btn-primary btn-small"
                                    data-add-product
                                    data-id="<?php echo (int)$item['id']; ?>"
                                    data-sku="<?php echo $itemSku; ?>"
                                    data-name="[CE] <?php echo $itemName; ?>"
                                    data-image="<?php echo htmlspecialchars($images[0] ?? $itemImg, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-price="<?php echo $itemPrice; ?>">Agregar al carrito</button>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- CARRITO -->
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
                <button id="printTicket" class="btn btn-primary">⬇️ Descargar Ticket</button>
                <button id="shareWhatsApp" class="btn btn-secondary">📱 Enviar cotización por WhatsApp</button>
                <button id="clearCart" class="btn btn-ghost">🗑️ Vaciar Carrito</button>
            </div>
        </div>
    </aside>

    <footer>
        <div class="footer-bottom">&copy; 2026 Truper Platform — Marketplace CE</div>
    </footer>

    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/main.js?v=2.6"></script>
    <script src="js/catalog.js?v=3.1"></script>
    <script>
    (function () {
        const companyWhatsApp = '<?php echo htmlspecialchars($whatsappPhone, ENT_QUOTES, 'UTF-8'); ?>';

        /* ===== Filtrado CE ===== */
        const ceItems   = Array.from(document.querySelectorAll('[data-ce-item]'));
        const ceSearch  = document.getElementById('ceSearch');
        const ceCatBtns = Array.from(document.querySelectorAll('[data-ce-cat]'));
        const ceCount   = document.getElementById('ceCount');

        let activeCat = '';
        let searchQ   = '';

        function filterCe() {
            const q   = searchQ.toLowerCase().trim();
            const cat = activeCat.toLowerCase().trim();
            let visible = 0;
            ceItems.forEach(card => {
                const name    = (card.dataset.name || '').toLowerCase();
                const sku     = (card.dataset.sku  || '').toLowerCase();
                const cond    = (card.dataset.condition || '').toLowerCase();
                const cardCat = (card.dataset.category || '').toLowerCase();

                const matchSearch = !q || name.includes(q) || sku.includes(q) || cond.includes(q);
                const matchCat    = !cat || cardCat === cat;

                if (matchSearch && matchCat) {
                    card.style.display = '';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });
            if (ceCount) ceCount.textContent = `Mostrando ${visible} de ${ceItems.length} artículos`;
        }

        if (ceSearch) ceSearch.addEventListener('input', () => { searchQ = ceSearch.value; filterCe(); });

        ceCatBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                ceCatBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activeCat = btn.getAttribute('data-ce-cat') || '';
                filterCe();
            });
        });

        filterCe();

        /* ===== WhatsApp share ===== */
        const shareBtn = document.getElementById('shareWhatsApp');
        if (shareBtn) {
            shareBtn.addEventListener('click', function () {
                const items = JSON.parse(localStorage.getItem('truper_cart') || '[]');
                if (!items.length) { alert('El carrito está vacío'); return; }

                const total = items.reduce((s, i) => s + i.unit_price * i.quantity, 0);
                const now   = new Date();
                const ticketCode = 'CE-TCK-' + String(now.getTime()).slice(-8);
                const issueDate  = now.toLocaleString('es-MX');
                const issueDateIso = now.toISOString();
                const clientCode = document.body?.dataset?.clientCode || 'PUBLICO';

                const safeItems = items.map(i => ({
                    name: i.name, sku: String(i.sku||'').replace(/^XLS-/i,''),
                    quantity: Number(i.quantity||0), price: Number(i.unit_price||0)
                }));
                const encodedItems = encodeURIComponent(btoa(unescape(encodeURIComponent(JSON.stringify(safeItems)))));
                const ticketUrl = `${window.location.origin}/ticket_quote.php?folio=${encodeURIComponent(ticketCode)}&issued_at=${encodeURIComponent(issueDateIso)}&client=${encodeURIComponent(clientCode)}&total=${encodeURIComponent(total.toFixed(2))}&items=${encodedItems}&format=thermal&auto_pdf=1`;

                let msg = 'TRUPER - COTIZACION MARKETPLACE CE\n===========================\n';
                msg += `Folio: ${ticketCode}\nFecha: ${issueDate}\nCliente: ${clientCode}\n---------------------------\nARTICULOS:\n`;
                items.forEach(i => {
                    const code  = String(i.sku||'').replace(/^XLS-/i,'') || 'N/A';
                    const total_line = i.unit_price * i.quantity;
                    msg += `- ${i.name}\n  Codigo: ${code}\n  ${i.quantity} x $${Number(i.unit_price).toFixed(2)} = $${total_line.toFixed(2)}\n`;
                });
                msg += `---------------------------\nTOTAL: $${total.toFixed(2)}\nPDF/Ticket: ${ticketUrl}\n\nQuedo atento(a) a disponibilidad.`;

                if (companyWhatsApp) {
                    window.open(`https://wa.me/${companyWhatsApp}?text=${encodeURIComponent(msg)}`, '_blank');
                } else {
                    window.open(`https://wa.me/?text=${encodeURIComponent(msg)}`, '_blank');
                }
            });
        }
    })();
    </script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
