<?php
require_once '../config/config.php';

$products = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, sku, unit_price, category FROM products WHERE is_active = true ORDER BY name LIMIT 24");
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    $products = [];
}

$isLogged = is_logged_in();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Truper - Catálogo de Productos</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <header>
        <div class="header-content">
            <a href="/" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
            <nav class="nav-menu">
                <a href="/" class="active">Productos</a>
                <?php if ($isLogged): ?>
                    <a href="/orders.php">Pedidos</a>
                    <a href="/dashboard.php">Dashboard</a>
                <?php else: ?>
                    <a href="/login.php">Iniciar Sesión</a>
                    <a href="/register.php">Registrarse</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <section class="catalog-hero">
            <h1>Catálogo Truper</h1>
            <p>Encuentra herramientas, materiales y equipos con precios actualizados.</p>
            <div class="d-flex gap-2 mt-2" style="justify-content:center;">
                <?php if ($isLogged): ?>
                    <a class="btn btn-primary" href="/orders.php?tab=newOrder">Ir a Comprar</a>
                <?php else: ?>
                    <a class="btn btn-primary" href="/register.php">Crear Cuenta</a>
                    <a class="btn btn-secondary" href="/login.php">Ya tengo cuenta</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="catalog-grid">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                    <article class="catalog-card">
                        <div class="catalog-tag"><?php echo htmlspecialchars($product['category'] ?: 'General', ENT_QUOTES, 'UTF-8'); ?></div>
                        <h3><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="text-muted">SKU: <?php echo htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="catalog-price">$<?php echo number_format((float) $product['unit_price'], 0, ',', '.'); ?></div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">Aún no hay productos cargados. Importa PRODUCTOS_EJEMPLO.sql para ver el catálogo.</div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <div class="footer-bottom">&copy; 2026 Truper Platform</div>
    </footer>
</body>
</html>
