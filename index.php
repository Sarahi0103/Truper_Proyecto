<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Truper - Catálogo de Herramientas</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <span class="logo-text">Truper</span>
            </div>
            <ul class="nav-menu">
                <li><a href="/">Inicio</a></li>
                <li><a href="/views/products.php">Catálogo</a></li>
                <li><a href="/views/wholesale.php">Mayoreo</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item-dropdown">
                        <a href="#" class="nav-link">Mi Cuenta ▼</a>
                        <div class="dropdown-menu">
                            <a href="/views/dashboard.php">Dashboard</a>
                            <a href="/views/my_orders.php">Mis Pedidos</a>
                            <a href="/views/profile.php">Perfil</a>
                            <a href="/backend/controllers/logout.php">Logout</a>
                        </div>
                    </li>
                <?php else: ?>
                    <li><a href="/views/login.php">Login</a></li>
                    <li><a href="/views/register.php" class="btn-register">Registrarse</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <h1>Truper</h1>
            <p>Tu Distribuidor de Herramientas y Productos de Confianza</p>
            <a href="/views/products.php" class="btn-primary">Ver Catálogo</a>
        </div>
    </section>

    <section class="features">
        <div class="feature">
            <div class="feature-icon">📦</div>
            <h3>Catálogo Digital</h3>
            <p>Acceso rápido a nuestro completo catálogo de productos</p>
        </div>
        <div class="feature">
            <div class="feature-icon">🎁</div>
            <h3>Programa de Puntos</h3>
            <p>Acumula puntos en cada compra y disfruta de bonos especiales</p>
        </div>
        <div class="feature">
            <div class="feature-icon">🚀</div>
            <h3>Pedidos Rápidos</h3>
            <p>Ordena fácilmente desde tu navegador</p>
        </div>
        <div class="feature">
            <div class="feature-icon">💰</div>
            <h3>Ventas Mayoreo</h3>
            <p>Cotizaciones especiales para negocios mayoristas</p>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2024 Truper. Todos los derechos reservados.</p>
        <p>Contacto: info@truper.com | Teléfono: +1-234-567-8900</p>
    </footer>

    <script src="/assets/js/main.js"></script>
</body>
</html>



