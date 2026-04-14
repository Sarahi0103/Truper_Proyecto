<?php
require_once '../config/config.php';

$isLogged = isset($_SESSION['user_id']);
$isAdmin = $isLogged && (($_SESSION['role'] ?? '') === 'admin');
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
                <h3>Próxima carga de artículos</h3>
                <p class="text-muted">Aquí se mostrarán herramientas y equipos de segunda mano (100-150 artículos estimados), con foto, descripción técnica, condición y precio.</p>
            </div>
        </section>
    </main>

    <script src="js/main.js"></script>
</body>
</html>
