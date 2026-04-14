<?php
require_once '../config/config.php';

$isLogged = isset($_SESSION['user_id']);
$isAdmin = $isLogged && (($_SESSION['role'] ?? '') === 'admin');

$marketplaceItems = [];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_ce_products (
        id SERIAL PRIMARY KEY,
        sku VARCHAR(100) UNIQUE NOT NULL,
        name VARCHAR(220) NOT NULL,
        description TEXT NOT NULL,
        condition_label VARCHAR(80) NOT NULL DEFAULT 'Seminuevo',
        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        stock_quantity INTEGER NOT NULL DEFAULT 1,
        image_url TEXT,
        is_active BOOLEAN NOT NULL DEFAULT true,
        created_by INTEGER REFERENCES users(id),
        updated_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $stmtCe = $pdo->query("SELECT id, sku, name, description, condition_label, unit_price, stock_quantity, image_url FROM marketplace_ce_products WHERE is_active = true ORDER BY created_at DESC LIMIT 300");
    $marketplaceItems = $stmtCe ? $stmtCe->fetchAll() : [];
} catch (Exception $e) {
    $marketplaceItems = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace CE - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
</head>
<body class="catalog-minimal">
    <header>
        <div class="header-content">
            <a href="/" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
            <nav class="nav-menu">
                <a href="/">Productos</a>
                <a href="/marketplace_ce.php" class="active">Marketplace CE</a>
                <?php if ($isAdmin): ?><a href="/admin_supply.php">Abastecimiento</a><?php endif; ?>
                <?php if ($isLogged): ?>
                    <a href="/orders.php">Pedidos</a>
                    <a href="/account.php">Mi Cuenta</a>
                <?php endif; ?>
            </nav>
            <div class="header-actions">
                <div class="theme-toggle">
                    <button type="button" data-theme-toggle-btn><span data-theme-toggle-label>Modo claro</span></button>
                </div>
            </div>
        </div>
    </header>

    <main>
        <section class="catalog-hero">
            <div class="module-badge module-main"><span class="module-glyph">CE</span> Catálogo de segunda mano</div>
            <h1>Marketplace CE</h1>
            <p>Sección independiente para artículos de medio uso. Así evitamos confusión con el catálogo principal.</p>
            <p class="text-muted" style="margin-top: 8px;">Publicaciones y disponibilidad sujetas a validación del establecimiento.</p>
            <div style="margin-top: 12px;">
                <a href="/" class="btn btn-secondary btn-small">Volver al catálogo principal</a>
            </div>
        </section>

        <section class="card">
            <div class="card-body">
                <h3>Artículos disponibles</h3>
                <?php if (empty($marketplaceItems)): ?>
                    <p class="text-muted">Todavía no hay artículos CE publicados. El administrador puede agregarlos desde Abastecimiento > Marketplace CE.</p>
                <?php else: ?>
                    <div class="catalog-grid-min">
                        <?php foreach ($marketplaceItems as $item): ?>
                            <article class="product-card-min">
                                <div class="product-media">
                                    <img
                                        class="product-gallery-image active"
                                        src="<?php echo htmlspecialchars((string)($item['image_url'] ?: 'images/products/default-product.svg'), ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="<?php echo htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        loading="lazy">
                                </div>
                                <div class="product-content">
                                    <div class="catalog-tag">Marketplace CE</div>
                                    <div class="product-code-label"><strong>Código:</strong> <strong><?php echo htmlspecialchars((string)$item['sku'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                    <h3 class="product-title"><?php echo htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p class="product-spec"><?php echo htmlspecialchars((string)$item['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div style="margin-top:0.5rem;">
                                        <span class="variant-pill"><?php echo htmlspecialchars((string)$item['condition_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <span class="stock-badge <?php echo ((int)$item['stock_quantity'] <= 2) ? 'stock-low' : 'stock-ok'; ?>">
                                        <?php echo ((int)$item['stock_quantity'] <= 2) ? 'Pocas piezas: ' : 'Disponibles: '; ?><?php echo (int)$item['stock_quantity']; ?>
                                    </span>
                                    <div class="catalog-price"><?php echo '$' . number_format((float)$item['unit_price'], 0, ',', '.'); ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="js/main.js"></script>
</body>
</html>
