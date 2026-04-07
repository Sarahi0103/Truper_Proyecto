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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Truper - Catálogo de Productos</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="catalog-minimal" data-client-code="<?php echo htmlspecialchars($clientTicketCode, ENT_QUOTES, 'UTF-8'); ?>" data-client-number="<?php echo htmlspecialchars($clientTicketNumber, ENT_QUOTES, 'UTF-8'); ?>">
    <header>
        <div class="header-content">
            <a href="/" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
            <nav class="nav-menu">
                <a href="/" class="active">Productos</a>
                <?php if ($isAdmin): ?><a href="/admin_supply.php">Abastecimiento</a><?php endif; ?>
                <?php if ($isLogged): ?>
                    <a href="/orders.php">Pedidos</a>
                    <a href="/wholesale.php">Mayoreo</a>
                    <?php if ($isAdmin): ?><a href="/cashier.php">Caja</a><?php endif; ?>
                    <a href="/dashboard.php">Dashboard</a>
                <?php endif; ?>
            </nav>
            <?php if (!$isLogged): ?>
                <div class="header-actions">
                    <a href="/admin_login.php" class="btn btn-primary btn-small">Solo para administradores</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <section class="catalog-hero">
            <h1>Catálogo Truper</h1>
            <p>Visualización ágil, sencilla y eficaz con precio, stock, variantes e información técnica.</p>
        </section>

        <section class="catalog-shell">
            <div class="catalog-toolbar">
                <input id="catalogSearch" class="catalog-search" type="text" placeholder="Buscar por nombre, SKU o categoría...">
            </div>

            <div class="catalog-filters">
                <select id="filterCategory">
                    <option value="">Todas las categorías</option>
                    <?php
                    $categories = [];
                    foreach ($products as $p) {
                        if (!empty($p['category'])) {
                            $categories[$p['category']] = true;
                        }
                    }
                    foreach (array_keys($categories) as $cat):
                    ?>
                    <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
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
                        $imagePath = !empty($product['image_url']) ? $product['image_url'] : 'images/products/default-product.svg';
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
                        data-sku="<?php echo htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-category="<?php echo htmlspecialchars($product['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-price="<?php echo (float)$product['unit_price']; ?>"
                        data-stock="<?php echo $stock; ?>">
                        <div class="product-media">
                            <img src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                        </div>
                        <div class="product-content">
                            <div class="catalog-tag"><?php echo htmlspecialchars($product['category'] ?: 'General', ENT_QUOTES, 'UTF-8'); ?></div>
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="text-muted">SKU: <?php echo htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <p class="product-spec"><?php echo htmlspecialchars($product['description'] ?: 'Descripción pendiente', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="product-spec"><strong>Especificaciones:</strong> <?php echo htmlspecialchars($product['technical_specs'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
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
                                    class="btn btn-ghost btn-small"
                                    data-fav-product
                                    data-fav-sku="<?php echo htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-sku="<?php echo htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">Favoritos</button>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-small"
                                    data-add-product
                                    data-id="<?php echo (int)$product['id']; ?>"
                                    data-sku="<?php echo htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
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
            <div class="btn-group">
                <button id="printTicket" class="btn btn-primary">⬇️ Descargar Ticket</button>
                <button id="printTicketA4" class="btn btn-ghost">📄 Formato A4</button>
                <button id="clearCart" class="btn btn-secondary">🗑️ Vaciar Carrito</button>
            </div>
        </div>
    </aside>

    <footer>
        <div class="footer-bottom">&copy; 2026 Truper Platform</div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/catalog.js"></script>
</body>
</html>
