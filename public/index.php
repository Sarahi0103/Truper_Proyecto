<?php
require_once '../config/config.php';

ensure_xlsx_products_seeded();

$products = [];
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS technical_specs TEXT");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS image_url TEXT");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS variants_json TEXT");

    $stmt = $pdo->prepare("SELECT id, name, sku, unit_price, category, description, technical_specs, stock_quantity, image_url, variants_json FROM products WHERE is_active = true ORDER BY name LIMIT 200");
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
    if (strpos($name, '+') === false) {
        return 0;
    }
    if (preg_match('/\+FC1$/', $name)) {
        return 1;
    }
    if (preg_match('/\+E1$/', $name)) {
        return 2;
    }
    if (preg_match('/\+D1$/', $name)) {
        return 3;
    }
    return 9;
}

function resolve_images_by_product_code($code) {
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
    return $cache[$code] ?? [];
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

$isLogged = is_logged_in();
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
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

    $stmtUpdates = $pdo->query("SELECT update_type, title, body FROM homepage_updates WHERE is_active = true ORDER BY sort_order ASC, id DESC LIMIT 12");
    $homepageUpdates = $stmtUpdates ? $stmtUpdates->fetchAll() : [];
} catch (Exception $ignored) {
    $homepageUpdates = [];
}

if (empty($homepageUpdates)) {
    $homepageUpdates = [
        [
            'update_type' => 'promocion',
            'title' => 'Combo de herramientas eléctricas con precio especial',
            'body' => 'Consulta disponibilidad por WhatsApp y recibe confirmación de stock para entrega rápida.'
        ],
        [
            'update_type' => 'evento',
            'title' => 'Capacitación técnica para instaladores este viernes',
            'body' => 'Incluye demostración de productos y recomendaciones de uso en material eléctrico y herrería.'
        ],
        [
            'update_type' => 'noticia',
            'title' => 'Nuevos ingresos en fontanería y cerrajería',
            'body' => 'Ya disponibles nuevas variantes de producto con ficha técnica y precio actualizado en catálogo.'
        ]
    ];
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
                <?php if ($isAdmin): ?><a href="/admin_supply.php">Abastecimiento</a><?php endif; ?>
                <?php if ($isLogged): ?>
                    <a href="/orders.php">Pedidos</a>
                    <a href="/account.php">Mi Cuenta</a>
                    <a href="/wholesale.php">Mayoreo</a>
                    <?php if ($isAdmin): ?><a href="/cashier.php">Caja</a><?php endif; ?>
                    <a href="/dashboard.php">Dashboard</a>
                <?php endif; ?>
            </nav>
            <div class="header-actions">
                <div class="theme-toggle">
                    <button type="button" data-theme-toggle-btn><span data-theme-toggle-label>Modo claro</span></button>
                </div>
                <a href="<?php echo htmlspecialchars($whatsappHelpUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-small">Dudas por WhatsApp</a>
                <?php if (!$isLogged): ?>
                    <a href="/admin_login.php" class="btn btn-primary btn-small">Solo para administradores</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
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
        </section>

        <section class="catalog-shell">
            <div class="catalog-categories-top">
                <div class="catalog-categories-title">Categorías</div>
                <div class="catalog-categories-actions">
                    <button type="button" class="btn btn-ghost btn-small active" data-quick-category="">Todas</button>
                    <button type="button" class="btn btn-ghost btn-small" data-quick-category="Material eléctrico">Material eléctrico</button>
                    <button type="button" class="btn btn-ghost btn-small" data-quick-category="Fontanería">Fontanería</button>
                    <button type="button" class="btn btn-ghost btn-small" data-quick-category="Cerrajería">Cerrajería</button>
                    <button type="button" class="btn btn-ghost btn-small" data-quick-category="Herrería">Herrería</button>
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
                        $imagePath = !empty($product['image_url']) ? $product['image_url'] : 'images/products/default-product.svg';
                        $galleryImages = resolve_images_by_product_code($displaySku);
                        if (empty($galleryImages)) {
                            $galleryImages = [$imagePath];
                        } elseif (!empty($product['image_url']) && $product['image_url'] !== 'images/products/default-product.svg') {
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
                    <article class="product-card-min"
                        data-product-card
                        data-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-sku="<?php echo htmlspecialchars($displaySku, ENT_QUOTES, 'UTF-8'); ?>"
                        data-category="<?php echo htmlspecialchars($product['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-price="<?php echo (float)$product['unit_price']; ?>"
                        data-stock="<?php echo $stock; ?>">
                        <a href="product_detail.php?id=<?php echo (int)$product['id']; ?>" class="product-media-link" data-product-gallery>
                            <div class="product-media" data-product-gallery>
                            <?php foreach ($galleryImages as $idx => $galleryImage): ?>
                                <img
                                    class="product-gallery-image <?php echo $idx === 0 ? 'active' : ''; ?>"
                                    src="<?php echo htmlspecialchars($galleryImage, ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    loading="lazy">
                            <?php endforeach; ?>
                            <?php if (count($galleryImages) > 1): ?>
                                <button type="button" class="gallery-nav gallery-prev" data-gallery-prev aria-label="Imagen anterior">&#10094;</button>
                                <button type="button" class="gallery-nav gallery-next" data-gallery-next aria-label="Imagen siguiente">&#10095;</button>
                                <div class="gallery-counter"><span data-gallery-current>1</span>/<?php echo count($galleryImages); ?></div>
                            <?php endif; ?>
                            </div>
                        </a>
                        <div class="product-content">
                            <div class="catalog-tag"><?php echo htmlspecialchars($product['category'] ?: 'General', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="product-code-label"><strong>Código:</strong> <strong><?php echo htmlspecialchars($displaySku, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="product-spec"><?php echo htmlspecialchars($product['description'] ?: 'Descripción pendiente', ENT_QUOTES, 'UTF-8'); ?></p>
                            <div>
                                <?php if (!empty($variants)): ?>
                                    <?php foreach ($variants as $variant): ?>
                                        <span class="variant-pill"><?php echo htmlspecialchars((string)$variant, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="variant-pill">Modelo estándar</span>
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
                                    data-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-price="<?php echo (float)$product['unit_price']; ?>">Agregar</button>
                                <a href="product_detail.php?id=<?php echo (int)$product['id']; ?>" class="btn btn-secondary btn-small">Detalles</a>
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
