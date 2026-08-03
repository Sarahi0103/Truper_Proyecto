<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/image_cache.php';

$isLogged = isset($_SESSION['user_id']);
$isAdmin  = $isLogged && (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'employee');

// Load only products visible in the online store
$products = [];
try {
    $visibilityWhere = "WHERE 1=1";
    if (db_column_exists('products', 'is_active')) {
        $visibilityWhere .= " AND (CASE WHEN is_active IS NULL THEN 1 WHEN LOWER(CAST(is_active AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
    }
    if (db_column_exists('products', 'show_in_online')) {
        $visibilityWhere .= " AND (CASE WHEN show_in_online IS NULL THEN 1 WHEN LOWER(CAST(show_in_online AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
    }

    $groupSelect = db_column_exists('products', 'product_group') ? "COALESCE(product_group,'') AS product_group" : "'' AS product_group";
    $colorSelect = db_column_exists('products', 'color')         ? "COALESCE(color,'') AS color"                : "'' AS color";
    $priceSelect = db_column_exists('products', 'price_online')
        ? "COALESCE(NULLIF(price_online,0), unit_price, 0)"
        : "COALESCE(unit_price, 0)";

    $stmt = $pdo->prepare(
        "SELECT id, name, sku, {$priceSelect} AS unit_price,
                COALESCE(net_price, unit_price, 0) AS net_price,
                COALESCE(discount_percentage, 0) AS discount_percentage,
                category, description, stock_quantity, image_url, variants_json,
                {$groupSelect}, {$colorSelect}
         FROM products {$visibilityWhere} ORDER BY name LIMIT 5000"
    );
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (Exception $e) { $products = []; }

function is_gallery_img_ts($v) {
    $v = trim((string)$v);
    return $v !== '' && (strpos($v,'images/')===0 || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i',$v));
}
function norm_sku_ts($sku) { return preg_replace('/^XLS-/i','', (string)$sku); }

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

$quickCategoriesMap = [];
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
} catch (Exception $ig) {}

// Build JS product array
$jsonProducts = [];
foreach ($products as $product) {
    $dSku = norm_sku_ts($product['sku'] ?? '');
    $imgs = catalog_resolve_gallery_images_by_sku($dSku, $product, $pdo);
    if (empty($imgs)) $imgs = [$product['image_url'] ?: 'images/products/default-product.svg'];
    $variants = [];
    if (!empty($product['variants_json'])) {
        $dec = json_decode($product['variants_json'], true);
        if (is_array($dec)) foreach ($dec as $it) { $s=(string)$it; if (!is_gallery_img_ts($s)) $variants[]=$s; }
    }
    $jsonProducts[] = [
        'id'        => (int)$product['id'],
        'sku'       => $dSku,
        'name'      => decode_legacy_entities((string)($product['name']??'')),
        'desc'      => decode_legacy_entities((string)($product['description']??'')),
        'cat'       => decode_legacy_entities((string)($product['category']??'')) ?: 'General',
        'price'     => (float)$product['unit_price'],
        'net_price' => (float)$product['net_price'],
        'discount'  => (float)$product['discount_percentage'],
        'stock'     => (int)$product['stock_quantity'],
        'imgs'      => $imgs,
        'variants'  => $variants,
        'color'     => trim((string)($product['color']??'')),
        'group'     => trim((string)($product['product_group']??'')),
    ];
}

$dbProductGroups = [];
try {
    $pgStmt = $pdo->query("SELECT id, name, color FROM product_groups ORDER BY name ASC");
    if ($pgStmt) {
        $dbProductGroups = $pgStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $ignored) {}

$productGroupCounts = [];
foreach ($products as $product) {
    $grp = !empty($product['product_group']) ? trim((string)$product['product_group']) : '';
    if ($grp !== '') {
        $productGroupCounts[mb_strtolower($grp)] = ($productGroupCounts[mb_strtolower($grp)] ?? 0) + 1;
    }
}

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

$activeProductGroups = array_values(array_filter($availableProductGroups, function($g) {
    return (int)($g['count'] ?? 0) > 0;
}));

$totalOnline = count($products);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tienda en Línea – Ferretería FOX</title>
<meta name="description" content="Compra herramientas Truper en línea. Envío a domicilio, catálogo actualizado.">
<link rel="icon" type="image/png" href="/truper_logo2.png">
<link rel="stylesheet" href="<?php echo asset_url('css/styles.css'); ?>">
<link rel="stylesheet" href="<?php echo asset_url('css/theme.css'); ?>">
<link rel="stylesheet" href="<?php echo asset_url('css/responsive-complete.css'); ?>">
<link rel="stylesheet" href="<?php echo asset_url('css/dark-mode-auto.css'); ?>">
<link rel="stylesheet" href="<?php echo asset_url('css/catalog-min.css'); ?>">
<style>
    .tienda-topbar{background:linear-gradient(90deg,rgba(18,18,24,.98),rgba(10,10,14,.99));border-bottom:1px solid rgba(255,127,0,.2);padding:.5rem 1.4rem;display:flex;align-items:center;gap:1rem;}
    .tienda-back{display:inline-flex;align-items:center;gap:6px;color:#ff7f00;font-weight:700;font-size:.84rem;text-decoration:none;padding:5px 14px;border:1px solid rgba(255,127,0,.3);border-radius:8px;background:rgba(255,127,0,.07);transition:all .18s;}
    .tienda-back:hover{background:rgba(255,127,0,.18);border-color:#ff7f00;color:#fff;}
    .tienda-badge-top{font-size:.73rem;font-weight:700;color:#ff7f00;text-transform:uppercase;letter-spacing:.05em;opacity:.75;}
    
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
</style>
</head>
<body class="catalog-minimal">

<div class="tienda-topbar">
    <a href="/index.php" class="tienda-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Regresar al Catálogo Principal
    </a>
    <span class="tienda-badge-top">Tienda en Línea &nbsp;·&nbsp; <?php echo $totalOnline; ?> producto<?php echo $totalOnline!==1?'s':''; ?> disponibles</span>
</div>

<header>
    <div class="header-content">
        <a href="index.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height:42px;width:auto;object-fit:contain;"></a>
        <button class="hamburger-btn" aria-label="Toggle menu"><span></span><span></span><span></span></button>
        <nav class="nav-menu">
            <a href="tienda.php" class="active">Tienda en Línea</a>
            <a href="marketplace_ce.php?mode=online">Marketplace CE</a>
            <a href="order_tracking.php?mode=online">Seguimiento de Pedido</a>
            <a href="cart.php?mode=online">Carrito</a>
        </nav>
        <div class="header-actions">
        </div>
    </div>
</header>

<main>
    <section class="catalog-hero" style="background:linear-gradient(180deg,rgba(14,14,16,.82) 0%,rgba(23,23,26,.92) 100%),url('/img/fondo_portada_truper.png') center/cover no-repeat !important;">
        <div class="module-badge module-main"><span class="module-glyph">TL</span> Tienda en Línea</div>
        <h1>Compra en Línea — Ferretería FOX</h1>
        <p style="color:#fff!important;font-weight:600!important;font-size:1.05rem!important;text-shadow:0 2px 8px rgba(0,0,0,.9)!important;">
            Selecciona tu producto, agrégalo al carrito y recíbelo en tu domicilio.
        </p>
    </section>

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
                    $dbGroupColorMap[mb_strtolower($grpName)] = $grpColor;
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
                        <a href="product_detail.php?id=<?php echo $p['id']; ?>&mode=online" class="product-media-link" aria-label="Ver detalle de <?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>"></a>
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
            <p>No pudimos encontrar productos con los filtros seleccionados.</p>
        </div>
        <div class="sentinel" id="sentinel" style="height: 10px; margin-top: 2rem;"></div>
    </section>
</main>

<script id="products-data" type="application/json">
    <?php echo json_encode($jsonProducts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
</script>
<script id="product-groups-data" type="application/json">
    <?php echo json_encode($dbProductGroups, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
</script>
<script src="js/main.js?v=2.6"></script>
<script src="js/modals.js"></script>
<script src="js/catalog.js?v=3.1"></script>
<script src="js/mobile-optimize.js"></script>
</body>
</html>