<?php
require_once '../config/config.php';

ensure_xlsx_products_seeded();

$products = [];
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS technical_specs TEXT");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS image_url TEXT");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS variants_json TEXT");

    $visibilityWhere = '';
    if (db_column_exists('products', 'is_active')) {
        $visibilityWhere = " WHERE (CASE WHEN is_active IS NULL THEN 1 WHEN LOWER(CAST(is_active AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
    } elseif (db_column_exists('products', 'active')) {
        $visibilityWhere = " WHERE active = 1";
    }

    $stmt = $pdo->prepare("SELECT id, name, sku, COALESCE(unit_price, sell_price, 0) AS unit_price, category, description, technical_specs, stock_quantity, image_url, variants_json FROM products" . $visibilityWhere . " ORDER BY name LIMIT 200");
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    $products = [];
}

$xlsxSeedProducts = get_xlsx_seed_products();
if (count($products) < 10 && !empty($xlsxSeedProducts)) {
    $existingSkus = [];
    foreach ($products as $item) {
        $existingSkus[$item['sku'] ?? ''] = true;
    }
    foreach ($xlsxSeedProducts as $seed) {
        if (!isset($existingSkus[$seed['sku']])) {
            $products[] = $seed;
            $existingSkus[$seed['sku']] = true;
        }
    }
}

$demoProducts = [
    ['id' => 1001, 'name' => 'Taladro Percutor 1/2" 750W', 'sku' => 'TRUP-001', 'unit_price' => 1899, 'category' => 'Herramientas Eléctricas', 'description' => 'Taladro de alto rendimiento para concreto y metal.', 'technical_specs' => 'Potencia 750W | Velocidad variable | Mandril 1/2"', 'stock_quantity' => 35, 'image_url' => 'images/products/default-product.svg', 'variants_json' => '["Modelo Compacto", "Modelo Industrial"]'],
    ['id' => 1002, 'name' => 'Juego de Llaves Combinadas 12 pzas', 'sku' => 'TRUP-002', 'unit_price' => 799, 'category' => 'Herramientas Manuales', 'description' => 'Juego profesional de llaves de acero cromo vanadio.', 'technical_specs' => '12 piezas | Acero Cr-V | Medidas métricas', 'stock_quantity' => 50, 'image_url' => 'images/products/default-product.svg', 'variants_json' => '["6-17 mm", "8-19 mm"]'],
    ['id' => 1003, 'name' => 'Esmeriladora Angular 4-1/2" 900W', 'sku' => 'TRUP-003', 'unit_price' => 1299, 'category' => 'Herramientas Eléctricas', 'description' => 'Corte y desbaste con control y seguridad.', 'technical_specs' => '900W | Disco 4-1/2" | Guarda ajustable', 'stock_quantity' => 28, 'image_url' => 'images/products/default-product.svg', 'variants_json' => '["Con maletín", "Sin maletín"]'],
    ['id' => 1004, 'name' => 'Martillo Uña 16 oz', 'sku' => 'TRUP-004', 'unit_price' => 249, 'category' => 'Herramientas Manuales', 'description' => 'Martillo de acero forjado con mango antiderrapante.', 'technical_specs' => '16 oz | Acero templado | Mango ergonómico', 'stock_quantity' => 120, 'image_url' => 'images/products/default-product.svg', 'variants_json' => '["Mango fibra", "Mango madera"]'],
    ['id' => 1005, 'name' => 'Pinza de Electricista 8"', 'sku' => 'TRUP-005', 'unit_price' => 329, 'category' => 'Electricidad', 'description' => 'Pinza profesional para corte y sujeción.', 'technical_specs' => 'Acero Cr-V | Aislamiento 1000V', 'stock_quantity' => 64, 'image_url' => 'images/products/default-product.svg', 'variants_json' => '["8 pulgadas", "9 pulgadas"]'],
    ['id' => 1006, 'name' => 'Flexómetro 8 m', 'sku' => 'TRUP-006', 'unit_price' => 189, 'category' => 'Medición', 'description' => 'Cinta métrica con freno y carcasa resistente.', 'technical_specs' => '8 metros | Cinta de acero | Gancho magnético', 'stock_quantity' => 90, 'image_url' => 'images/products/default-product.svg', 'variants_json' => '["5 m", "8 m"]'],
    ['id' => 1007, 'name' => 'Rotomartillo SDS Plus 850W', 'sku' => 'TRUP-007', 'unit_price' => 2599, 'category' => 'Herramientas Eléctricas', 'description' => 'Perforación y cincelado para uso profesional.', 'technical_specs' => '850W | SDS Plus | 3 modos', 'stock_quantity' => 22, 'image_url' => 'images/products/default-product.svg', 'variants_json' => '["Con brocas", "Solo equipo"]'],
    ['id' => 1008, 'name' => 'Juego de Dados 40 pzas', 'sku' => 'TRUP-008', 'unit_price' => 1149, 'category' => 'Mecánica', 'description' => 'Set completo para taller automotriz.', 'technical_specs' => '1/2" | Acero Cr-V | Estuche rígido', 'stock_quantity' => 41, 'image_url' => 'images/products/default-product.svg', 'variants_json' => '["Métrico", "Mixto"]'],
    ['id' => 1009, 'name' => 'Batería de Litio 20V 4Ah', 'sku' => 'TRUP-009', 'unit_price' => 1399, 'category' => 'Accesorios', 'description' => 'Batería compatible con línea inalámbrica.', 'technical_specs' => '20V | 4Ah | Indicador LED', 'stock_quantity' => 57, 'image_url' => 'images/products/default-product.svg', 'variants_json' => '["2Ah", "4Ah", "6Ah"]'],
    ['id' => 1010, 'name' => 'Cargador Rápido 20V', 'sku' => 'TRUP-010', 'unit_price' => 699, 'category' => 'Accesorios', 'description' => 'Carga rápida para baterías de litio.', 'technical_specs' => 'Entrada 127V | Salida 20V | Protección térmica', 'stock_quantity' => 38, 'image_url' => 'images/products/default-product.svg', 'variants_json' => '["Estándar", "Rápido"]']
];

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

    return strpos($value, 'images/') === 0 || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $value) === 1;
}

function resolve_images_by_product_code($code, array $productRow = []) {
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

    foreach ($cache[$code] ?? [] as $cachedImage) {
        $mergeImage($cachedImage);
    }

    if (empty($images)) {
        $images[] = 'images/products/default-product.svg';
    }

    return $images;
}

if (count($products) < 10) {
    $existingSkus = [];
    foreach ($products as $item) {
        $existingSkus[$item['sku'] ?? ''] = true;
    }
    foreach ($demoProducts as $demo) {
        if (count($products) >= 10) {
            break;
        }
        if (!isset($existingSkus[$demo['sku']])) {
            $products[] = $demo;
        }
    }
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

// Intento de cargar categorías desde la tabla oficial para consistencia
try {
    $catStmt = $pdo->query("SELECT name FROM product_categories WHERE is_active = true ORDER BY sort_order ASC, name ASC");
    if ($catStmt) {
        $dbCats = $catStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($dbCats)) {
            $uniqueDbCats = [];
            foreach ($dbCats as $c) {
                $c = trim((string)$c);
                if ($c === '') continue;
                $key = $normalizeCategoryKey($c);
                if (!isset($uniqueDbCats[$key])) {
                    $uniqueDbCats[$key] = $c;
                }
            }
            $quickCategories = array_values($uniqueDbCats);
        }
    }
} catch (Exception $ig) {
    // Si la tabla no existe o hay error, mantenemos $quickCategories generado dinámicamente
}

$isLogged = is_logged_in();
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS homepage_updates (
        id SERIAL PRIMARY KEY,
        update_type VARCHAR(20) NOT NULL DEFAULT 'noticia',
        title VARCHAR(220) NOT NULL,
        body TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_active BOOLEAN NOT NULL DEFAULT true,
        created_by INTEGER REFERENCES users(id),
        updated_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CHECK (update_type IN ('noticia', 'promocion', 'evento'))
    )");

    $activeColumn = null;
    if (db_column_exists('homepage_updates', 'is_active')) {
        $activeColumn = 'is_active';
    } elseif (db_column_exists('homepage_updates', 'active')) {
        $activeColumn = 'active';
    }

    $sortColumn = db_column_exists('homepage_updates', 'sort_order') ? 'sort_order' : 'id';
    $imageSelect = db_column_exists('homepage_updates', 'image_url')
        ? "COALESCE(image_url, '') AS image_url"
        : "'' AS image_url";

    $whereActive = '';
    if ($activeColumn !== null) {
        $whereActive = " WHERE (CASE WHEN {$activeColumn} IS NULL THEN 1 WHEN LOWER(CAST({$activeColumn} AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
    }

    $stmtUpdates = $pdo->query("SELECT update_type, title, body, {$imageSelect} FROM homepage_updates{$whereActive} ORDER BY {$sortColumn} ASC, id DESC LIMIT 12");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Truper - Catálogo de Productos</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
</head>
<body class="catalog-minimal" data-client-code="<?php echo htmlspecialchars($clientTicketCode, ENT_QUOTES, 'UTF-8'); ?>" data-client-number="<?php echo htmlspecialchars($clientTicketNumber, ENT_QUOTES, 'UTF-8'); ?>">
    <header>
        <div class="header-content">
            <a href="/" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
            <nav class="nav-menu">
                <a href="/" class="active">Productos</a>
                <a href="/marketplace_ce.php">Marketplace CE</a>
                <a href="/cart.php">Carrito</a>
                <?php if ($isAdmin): ?><a href="/admin_supply.php">Abastecimiento</a><?php endif; ?>
                <?php if ($isLogged): ?>
                    <a href="/orders.php">Pedidos</a>
                    <a href="/wholesale.php">Mayoreo</a>
                    <?php if ($isAdmin): ?><a href="/cashier.php">Caja</a><?php endif; ?>
                    <a href="/dashboard.php">Dashboard</a>
                <?php endif; ?>
            </nav>
            <div class="header-actions">
                <div class="theme-toggle">
                    <button type="button" data-theme-toggle-btn><span data-theme-toggle-label>Modo obscuro</span></button>
                </div>
                <a href="<?php echo htmlspecialchars($whatsappHelpUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-small">Dudas por WhatsApp</a>
                <?php if (!$isLogged): ?>
                    <a href="/admin_login.php" class="btn btn-primary btn-small">Solo para administradores</a>
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

        <section class="catalog-hero">
            <div class="module-badge module-main"><span class="module-glyph">CT</span> Catálogo principal</div>
            <h1>Catálogo Truper</h1>
            <p>Visualización ágil, sencilla y eficaz con precio, stock, variantes e información técnica.</p>
            <div style="margin-top: 12px;">
                <a href="/marketplace_ce.php" class="btn btn-secondary btn-small">Ir a Marketplace CE (segunda mano)</a>
            </div>
        </section>

        <section class="promo-carousel" aria-label="Noticias y promociones">
            <div class="promo-head">
                <div>
                    <div class="module-badge module-main"><span class="module-glyph">NT</span> Noticias y promociones</div>
                    <h2>Novedades del punto de venta</h2>
                    <p>Información destacada con rotación automática para mantener la portada activa y útil.</p>
                </div>
                <div class="promo-controls">
                    <button type="button" class="btn btn-ghost btn-small" data-promo-prev aria-label="Anterior">Anterior</button>
                    <button type="button" class="btn btn-ghost btn-small" data-promo-next aria-label="Siguiente">Siguiente</button>
                </div>
            </div>

            <?php if (!empty($homepageUpdates)): ?>
                <div class="promo-viewport" data-promo-viewport>
                    <div class="promo-track" data-promo-track>
                        <?php foreach ($homepageUpdates as $update): ?>
                            <article class="promo-slide" data-promo-slide>
                                <?php if (!empty($update['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($update['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($update['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="promo-image">
                                <?php endif; ?>
                                <span class="promo-kicker"><?php echo htmlspecialchars(homepage_update_label($update['update_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                <h3><?php echo htmlspecialchars((string)($update['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p><?php echo htmlspecialchars((string)($update['body'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="promo-dots" data-promo-dots></div>
            <?php else: ?>
                <div class="promo-viewport" data-promo-viewport>
                    <div class="promo-track" data-promo-track>
                        <article class="promo-slide" data-promo-slide>
                            <span class="promo-kicker">Portada</span>
                            <h3>Publica tus promociones desde Abastecimiento</h3>
                            <p>Usa el módulo Portada para crear tarjetas con imagen, título y contenido atractivo para tus clientes.</p>
                        </article>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="catalog-shell">
            <div class="catalog-categories-top">
                <div class="catalog-categories-title">Categorías</div>
                <div class="catalog-categories-actions">
                    <button type="button" class="btn btn-ghost btn-small active" data-quick-category="">Todas</button>
                    <?php foreach ($quickCategories as $categoryName): ?>
                        <button
                            type="button"
                            class="btn btn-ghost btn-small"
                            data-quick-category="<?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="catalog-toolbar">
                <input id="catalogSearch" class="catalog-search" type="text" placeholder="Buscar por nombre, código o categoría...">
            </div>

            <div class="catalog-filters">
                <input id="filterMaxPrice" type="number" min="0" step="1" placeholder="Precio maximo">
                <select id="filterStock">
                    <option value="">Todo stock</option>
                    <option value="available">Solo disponibles</option>
                    <option value="low">Stock bajo</option>
                </select>
                <button id="clearFilters" class="btn btn-ghost">Limpiar filtros</button>
            </div>

            <div class="catalog-grid-min">
                <?php foreach ($products as $product): ?>
                    <?php
                        $rawSku = (string)($product['sku'] ?? '');
                        $displaySku = normalize_product_code($rawSku);
                        $productName = decode_legacy_entities((string)($product['name'] ?? ''));
                        $productDescription = decode_legacy_entities((string)($product['description'] ?? ''));
                        $productCategory = decode_legacy_entities((string)($product['category'] ?? ''));
                        $imagePath = !empty($product['image_url']) ? $product['image_url'] : 'images/products/default-product.svg';
                        $galleryImages = resolve_images_by_product_code($displaySku, $product);
                        if (empty($galleryImages)) {
                            $galleryImages = [$imagePath];
                        }
                        $variants = [];
                        if (!empty($product['variants_json'])) {
                            $decoded = json_decode($product['variants_json'], true);
                            if (is_array($decoded)) {
                                // Filter out image paths stored in variants_json (keep only real variant labels)
                                foreach ($decoded as $item) {
                                    $itemStr = (string)$item;
                                    if (strpos($itemStr, 'images/') === false &&
                                        stripos($itemStr, '.jpg') === false &&
                                        stripos($itemStr, '.jpeg') === false &&
                                        stripos($itemStr, '.png') === false &&
                                        stripos($itemStr, '.gif') === false &&
                                        stripos($itemStr, '.webp') === false) {
                                        $variants[] = $itemStr;
                                    }
                                }
                            }
                        }
                        $stock = (int)($product['stock_quantity'] ?? 0);
                    ?>
                    <article class="product-card-min"
                        data-product-card
                        data-name="<?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>"
                        data-sku="<?php echo htmlspecialchars($displaySku, ENT_QUOTES, 'UTF-8'); ?>"
                        data-category="<?php echo htmlspecialchars($productCategory, ENT_QUOTES, 'UTF-8'); ?>"
                        data-price="<?php echo (float)$product['unit_price']; ?>"
                        data-stock="<?php echo $stock; ?>">
                        <div class="product-media" data-product-gallery>
                            <a href="product_detail.php?id=<?php echo (int)$product['id']; ?>" class="product-media-link" aria-label="Ver detalle de <?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>"></a>
                            <?php foreach ($galleryImages as $idx => $galleryImage): ?>
                                <img
                                    class="product-gallery-image <?php echo $idx === 0 ? 'active' : ''; ?>"
                                    src="<?php echo htmlspecialchars($galleryImage, ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>"
                                    loading="lazy">
                            <?php endforeach; ?>
                            <?php if (count($galleryImages) > 1): ?>
                                <button type="button" class="gallery-nav gallery-prev" data-gallery-prev aria-label="Imagen anterior">&#10094;</button>
                                <button type="button" class="gallery-nav gallery-next" data-gallery-next aria-label="Imagen siguiente">&#10095;</button>
                                <div class="gallery-counter"><span data-gallery-current>1</span>/<?php echo count($galleryImages); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="product-content">
                            <div class="catalog-tag"><?php echo htmlspecialchars($productCategory !== '' ? $productCategory : 'General', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="product-code-label"><strong>Código:</strong> <strong><?php echo htmlspecialchars($displaySku, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                            <h3 class="product-title"><?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="product-spec"><?php echo htmlspecialchars($productDescription !== '' ? $productDescription : 'Descripción pendiente', ENT_QUOTES, 'UTF-8'); ?></p>
                            <div>
                                <?php if (!empty($variants)): ?>
                                    <?php foreach ($variants as $variant): ?>
                                        <span class="variant-pill"><?php echo htmlspecialchars((string)$variant, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="variant-pill">Modelo Estándar</span>
                                <?php endif; ?>
                            </div>
                            <span class="stock-badge <?php echo $stock <= 10 ? 'stock-low' : 'stock-ok'; ?>">
                                <?php echo $stock <= 10 ? 'Stock bajo: ' : 'Stock: '; ?><?php echo $stock; ?>
                            </span>
                            <div class="catalog-price"><?php echo '$' . number_format((float)$product['unit_price'], 0, ',', '.'); ?></div>
                            <div class="product-actions">
                                <button
                                    type="button"
                                    class="btn btn-primary btn-small"
                                    data-add-product
                                    data-id="<?php echo (int)$product['id']; ?>"
                                    data-sku="<?php echo htmlspecialchars($displaySku, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-name="<?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-image="<?php echo htmlspecialchars($galleryImages[0] ?? $imagePath, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-price="<?php echo (float)$product['unit_price']; ?>">Agregar</button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
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
                <button id="printTicket" class="btn btn-primary">⬇️ Descargar Ticket</button>
                <button id="shareWhatsApp" class="btn btn-secondary">📱 Enviar cotización por WhatsApp</button>
                <button id="clearCart" class="btn btn-ghost">🗑️ Vaciar Carrito</button>
            </div>
        </div>
    </aside>

    <footer>
        <div class="footer-bottom">&copy; 2026 Truper Platform</div>
    </footer>
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/catalog.js"></script>
    <script>
        // Compartir por WhatsApp
        document.addEventListener('DOMContentLoaded', function() {
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
            const promoDelay = 5000;

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

            const shareBtn = document.getElementById('shareWhatsApp');
            if (shareBtn) {
                shareBtn.addEventListener('click', function() {
                    const items = JSON.parse(localStorage.getItem('truper_cart') || '[]');
                    if (items.length === 0) {
                        alert('El carrito está vacío');
                        return;
                    }

                    const total = items.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
                    const now = new Date();
                    const ticketCode = `TCK-${String(now.getTime()).slice(-8)}`;
                    const issueDate = now.toLocaleString('es-MX');
                    const issueDateIso = now.toISOString();
                    const clientCode = document.body?.dataset?.clientCode || 'PUBLICO';
                    const safeItems = items.map(item => ({
                        name: item.name,
                        sku: String(item.sku || '').replace(/^XLS-/i, ''),
                        quantity: Number(item.quantity || 0),
                        price: Number(item.unit_price || 0)
                    }));
                    const encodedItems = encodeURIComponent(btoa(unescape(encodeURIComponent(JSON.stringify(safeItems)))));
                    const ticketUrl = `${window.location.origin}/ticket_quote.php?folio=${encodeURIComponent(ticketCode)}&issued_at=${encodeURIComponent(issueDateIso)}&client=${encodeURIComponent(clientCode)}&total=${encodeURIComponent(total.toFixed(2))}&items=${encodedItems}&format=thermal&auto_pdf=1`;

                    let message = 'TRUPER - COTIZACION\n';
                    message += '===========================\n';
                    message += `Folio: ${ticketCode}\n`;
                    message += `Fecha: ${issueDate}\n`;
                    message += `Cliente: ${clientCode}\n`;
                    message += '---------------------------\n';
                    message += 'PRODUCTOS:\n';
                    items.forEach((item, idx) => {
                        const code = String(item.sku || '').replace(/^XLS-/i, '') || 'N/A';
                        const lineTotal = (item.unit_price * item.quantity);
                        message += `- ${item.name}\n`;
                        message += `  Codigo: ${code}\n`;
                        message += `  ${item.quantity} x $${Number(item.unit_price).toFixed(2)} = $${lineTotal.toFixed(2)}\n`;
                        if (idx < (items.length - 1)) {
                            message += '---------------------------\n';
                        }
                    });
                    message += '---------------------------\n';
                    message += `TOTAL: $${total.toFixed(2)}\n`;
                    message += `PDF/Ticket: ${ticketUrl}\n\n`;
                    message += 'Quedo atento(a) a disponibilidad y tiempo de entrega.';

                    const encodedMsg = encodeURIComponent(message);
                    const whatsappUrl = `https://wa.me/${companyWhatsApp}?text=${encodedMsg}`;
                    window.open(whatsappUrl, '_blank');
                });
            }
        });
    </script>
</body>
</html>
