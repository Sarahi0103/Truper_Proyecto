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

if (empty($products)) {
    $products = [
        ['name' => 'Taladro Percutor 1/2" 750W', 'sku' => 'TRUP-001', 'unit_price' => 1899, 'category' => 'Herramientas Eléctricas'],
        ['name' => 'Juego de Llaves Combinadas 12 pzas', 'sku' => 'TRUP-002', 'unit_price' => 799, 'category' => 'Herramientas Manuales'],
        ['name' => 'Esmeriladora Angular 4-1/2" 900W', 'sku' => 'TRUP-003', 'unit_price' => 1299, 'category' => 'Herramientas Eléctricas'],
        ['name' => 'Caja de Herramientas 19" Reforzada', 'sku' => 'TRUP-004', 'unit_price' => 499, 'category' => 'Almacenamiento'],
        ['name' => 'Martillo Uña 16 oz Mango Fibra', 'sku' => 'TRUP-005', 'unit_price' => 249, 'category' => 'Herramientas Manuales'],
        ['name' => 'Cinta Métrica 8m Uso Rudo', 'sku' => 'TRUP-006', 'unit_price' => 179, 'category' => 'Medición'],
        ['name' => 'Pistola para Pintar HVLP', 'sku' => 'TRUP-007', 'unit_price' => 999, 'category' => 'Pintura'],
        ['name' => 'Compresor de Aire 24L 2HP', 'sku' => 'TRUP-008', 'unit_price' => 3599, 'category' => 'Equipo Industrial']
    ];
}

$isLogged = is_logged_in();
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
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
                    <a href="/wholesale.php">Mayoreo</a>
                    <?php if ($isAdmin): ?><a href="/cashier.php">Caja</a><?php endif; ?>
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
            <?php foreach ($products as $product): ?>
                <article class="catalog-card">
                    <div class="catalog-tag"><?php echo htmlspecialchars($product['category'] ?: 'General', ENT_QUOTES, 'UTF-8'); ?></div>
                    <h3><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="text-muted">SKU: <?php echo htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="catalog-price">$<?php echo number_format((float) $product['unit_price'], 0, ',', '.'); ?></div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>

    <footer>
        <div class="footer-bottom">&copy; 2026 Truper Platform</div>
    </footer>
</body>
</html>
