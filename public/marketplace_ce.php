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
    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_ce_products (
        id SERIAL PRIMARY KEY,
        sku VARCHAR(100) UNIQUE NOT NULL,
        name VARCHAR(220) NOT NULL,
        description TEXT NOT NULL,
        condition_label VARCHAR(80) NOT NULL DEFAULT 'Seminuevo',
        category VARCHAR(120),
        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        stock_quantity INTEGER NOT NULL DEFAULT 1,
        image_url TEXT,
        variants_json TEXT,
        is_active BOOLEAN NOT NULL DEFAULT true,
        created_by INTEGER REFERENCES users(id),
        updated_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    /* Add category column if missing (migration guard) */
    try { $pdo->exec("ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS category VARCHAR(120)"); } catch (Exception $ig) {}
    try { $pdo->exec("ALTER TABLE marketplace_ce_products ADD COLUMN IF NOT EXISTS variants_json TEXT"); } catch (Exception $ig) {}

    $marketplaceVisibilityWhere = '';
    if (db_column_exists('marketplace_ce_products', 'is_active')) {
        $marketplaceVisibilityWhere = " WHERE (CASE WHEN is_active IS NULL THEN 1 WHEN LOWER(CAST(is_active AS TEXT)) IN ('1','t','true') THEN 1 ELSE 0 END) = 1";
    } elseif (db_column_exists('marketplace_ce_products', 'active')) {
        $marketplaceVisibilityWhere = " WHERE active = 1";
    }

    $stmtCe = $pdo->query("SELECT id, sku, name, description, condition_label, COALESCE(category,'Marketplace CE') AS category, unit_price, stock_quantity, COALESCE(image_url,'images/products/default-product.svg') AS image_url, variants_json FROM marketplace_ce_products" . $marketplaceVisibilityWhere . " ORDER BY created_at DESC LIMIT 300");
    $marketplaceItems = $stmtCe ? $stmtCe->fetchAll() : [];

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
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        /* ===== Marketplace CE extras ===== */
        .ce-hero {
            background: linear-gradient(135deg,rgba(0,0,0,0.94),rgba(20,20,20,0.98));
            border-radius: 16px;
            padding: 2rem 2rem 1.5rem;
            margin-bottom: 1.5rem;
            color: #fff;
            border: 1px solid #1f1f1f;
        }
        .ce-hero h1 { color: var(--theme-accent); margin: 0.25rem 0 0.5rem; font-size: 2rem; }
        .ce-hero p  { color: rgba(255,255,255,0.82); margin: 0 0 0.75rem; }

        .ce-bar {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 1.25rem;
        }
        .ce-search {
            flex: 1 1 220px;
            padding: 0.65rem 1rem;
            border: 1px solid var(--theme-border);
            border-radius: 999px;
            font-size: 0.96rem;
            background: var(--theme-surface);
            color: var(--theme-text);
            min-width: 180px;
        }
        .ce-search:focus { outline: none; border-color: var(--theme-accent); box-shadow: 0 0 0 3px var(--theme-accent-soft); }

        .ce-cats { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .ce-count { color: var(--theme-text-muted); font-size: 0.88rem; margin-bottom: 0.75rem; }

        .ce-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--theme-text-muted);
        }
        .ce-empty svg { width: 56px; height: 56px; opacity: 0.3; margin-bottom: 0.75rem; }

        /* condition badge */
        .condition-tag {
            display: inline-block;
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--theme-accent-soft);
            color: var(--theme-accent);
            border: 1px solid rgba(255,127,0,0.3);
        }
        .product-card-min .ce-add-btn {
            width: 100%;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body class="catalog-minimal"
    data-client-code="<?php echo htmlspecialchars($clientTicketCode, ENT_QUOTES, 'UTF-8'); ?>"
    data-client-number="<?php echo htmlspecialchars($clientTicketNumber, ENT_QUOTES, 'UTF-8'); ?>">

    <header>
        <div class="header-content">
            <a href="/" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
            <nav class="nav-menu">
                <a href="/">Productos</a>
                <a href="/marketplace_ce.php" class="active">Marketplace CE</a>
                <a href="/cart.php">Carrito</a>
                <?php if ($isAdmin): ?><a href="/admin_supply.php">Abastecimiento</a><?php endif; ?>
                <?php if ($isLogged): ?>
                    <a href="/orders.php">Pedidos</a>
                    <?php if ($isAdmin): ?><a href="/cashier.php">Caja</a><?php endif; ?>
                <?php endif; ?>
            </nav>
            <div class="header-actions">
                <div class="theme-toggle">
                    <button type="button" data-theme-toggle-btn><span data-theme-toggle-label>Modo obscuro</span></button>
                </div>
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
        <section class="ce-hero">
            <div class="module-badge" style="background:rgba(255,127,0,0.18);border:1px solid rgba(255,127,0,0.4);color:#ffd0a0;display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;font-size:0.82rem;margin-bottom:8px;">
                <span style="background:rgba(255,127,0,0.4);border-radius:4px;padding:1px 6px;font-weight:700;">CE</span> Segunda mano
            </div>
            <h1>Marketplace CE</h1>
            <p>Artículos de medio uso en buen estado: herramientas eléctricas, escaleras y más. Opciones accesibles con verificación del establecimiento.</p>
            <div style="display:flex;gap:0.6rem;flex-wrap:wrap;">
                <a href="/" class="btn btn-secondary btn-small">← Catálogo principal</a>
            </div>
        </section>

        <!-- FILTROS + BÚSQUEDA -->
        <div class="ce-bar">
            <input id="ceSearch" class="ce-search" type="text" placeholder="Buscar por nombre, código o condición...">
        </div>

        <?php if (!empty($allCategories)): ?>
        <div class="ce-cats">
            <button type="button" class="btn btn-ghost btn-small active" data-ce-cat="">Todos</button>
            <?php foreach ($allCategories as $cat): ?>
                <button type="button" class="btn btn-ghost btn-small" data-ce-cat="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="ce-count" id="ceCount"></div>

        <!-- GRID -->
        <?php if (empty($marketplaceItems)): ?>
            <div class="ce-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 3h-8v4h8V3z"/></svg>
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
                    // 2) Fallback: variants_json (puede ser base64 o rutas)
                    if (empty($images) && !empty($item['variants_json'])) {
                        $parsed = json_decode($item['variants_json'], true);
                        if (is_array($parsed) && count($parsed) > 0) {
                            // Filtrar base64 para no mandarlos al HTML si hay alternativa de disco
                            $nonBase64 = array_filter($parsed, fn($v) => strpos((string)$v, 'data:') !== 0);
                            $images = !empty($nonBase64) ? array_values($nonBase64) : $parsed;
                        }
                    }
                    // 3) Fallback final: image_url del producto
                    if (empty($images)) {
                        $fallbackImg = (string)($item['image_url'] ?? 'images/products/default-product.svg');
                        $images = [$fallbackImg];
                    }
                ?>
                <article class="product-card-min"
                    data-ce-item
                    data-name="<?php echo $itemName; ?>"
                    data-sku="<?php echo $itemSku; ?>"
                    data-category="<?php echo $itemCat; ?>"
                    data-condition="<?php echo $itemCond; ?>">
                    <div class="product-media" style="position:relative; overflow:hidden;">
                        <div class="gallery-track" style="display:flex; transition:transform 0.3s; width:100%; height:100%;">
                            <?php foreach ($images as $idx => $imgSrc): ?>
                                <img src="<?php echo htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8'); ?>"
                                     style="min-width:100%; object-fit:contain; border-radius:12px; pointer-events:none;"
                                     alt="<?php echo $itemName; ?>" loading="lazy">
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($images) > 1): ?>
                            <button type="button" class="gallery-btn btn-prev" onclick="moveGallery(this, -1)" style="position:absolute; left:5px; top:50%; transform:translateY(-50%); background:rgba(0,0,0,0.6); color:white; border:none; border-radius:50%; width:30px; height:30px; cursor:pointer; z-index:10; font-size:1.2rem; display:flex; align-items:center; justify-content:center;">&#8249;</button>
                            <button type="button" class="gallery-btn btn-next" onclick="moveGallery(this, 1)" style="position:absolute; right:5px; top:50%; transform:translateY(-50%); background:rgba(0,0,0,0.6); color:white; border:none; border-radius:50%; width:30px; height:30px; cursor:pointer; z-index:10; font-size:1.2rem; display:flex; align-items:center; justify-content:center;">&#8250;</button>
                        <?php endif; ?>
                    </div>
                    <div class="product-content">
                        <div class="catalog-tag">Marketplace CE</div>
                        <div class="product-code-label"><strong>Código:</strong> <strong><?php echo $itemSku; ?></strong></div>
                        <h3 class="product-title"><?php echo $itemName; ?></h3>
                        <p class="product-spec"><?php echo $itemDesc; ?></p>
                        <div style="margin-top:0.35rem;">
                            <span class="condition-tag"><?php echo $itemCond; ?></span>
                        </div>
                        <span class="stock-badge <?php echo $stockClass; ?>">
                            <?php echo $stockLabel . $itemStock; ?>
                        </span>
                        <div class="catalog-price">$<?php echo number_format($itemPrice, 2, '.', ','); ?></div>
                        <div class="product-actions">
                            <button
                                type="button"
                                class="btn btn-primary btn-small ce-add-btn"
                                data-add-product
                                data-id="<?php echo (int)$item['id']; ?>"
                                data-sku="<?php echo $itemSku; ?>"
                                data-name="[CE] <?php echo $itemName; ?>"
                                data-price="<?php echo $itemPrice; ?>">Agregar al carrito</button>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- CARRITO (mismo del catálogo principal) -->
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
    <script src="js/main.js"></script>
    <script src="js/catalog.js"></script>
    <script>
    (function () {
        const companyWhatsApp = '<?php echo htmlspecialchars($whatsappPhone, ENT_QUOTES, 'UTF-8'); ?>';

        /* ===== Filtrado CE ===== */
        const ceItems  = Array.from(document.querySelectorAll('[data-ce-item]'));
        const ceSearch = document.getElementById('ceSearch');
        const ceCatBtns = Array.from(document.querySelectorAll('[data-ce-cat]'));
        const ceCount  = document.getElementById('ceCount');

        let activeCat  = '';
        let searchQ    = '';

        function filterCe() {
            const q   = searchQ.toLowerCase().trim();
            const cat = activeCat.toLowerCase().trim();
            let visible = 0;
            ceItems.forEach(card => {
                const name  = (card.dataset.name || '').toLowerCase();
                const sku   = (card.dataset.sku  || '').toLowerCase();
                const cond  = (card.dataset.condition || '').toLowerCase();
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
            if (ceCount) {
                ceCount.textContent = `Mostrando ${visible} de ${ceItems.length} artículos`;
            }
        }

        if (ceSearch) {
            ceSearch.addEventListener('input', () => { searchQ = ceSearch.value; filterCe(); });
        }

        ceCatBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                ceCatBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activeCat = btn.getAttribute('data-ce-cat') || '';
                filterCe();
            });
        });

        filterCe(); // initial count

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
        // Gallery Navigation
        function moveGallery(btn, dir) {
            const track = btn.parentElement.querySelector('.gallery-track');
            if (!track) return;
            const images = track.querySelectorAll('img');
            const total = images.length;
            if (total <= 1) return;

            let currentIdx = parseInt(track.dataset.currentIndex || '0');
            currentIdx += dir;
            if (currentIdx < 0) currentIdx = total - 1;
            if (currentIdx >= total) currentIdx = 0;

            track.dataset.currentIndex = currentIdx;
            track.style.transform = `translateX(-${currentIdx * 100}%)`;
        }
    </script>
</body>
</html>
