<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Truper - Catálogo de Herramientas</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="stylesheet" href="/public/css/styles.css">
    <link rel="stylesheet" href="/public/css/theme.css">
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
                <li><a href="/admin_login.php" class="btn-primary">Solo para administradores</a></li>
            </ul>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
        <link rel="icon" type="image/png" href="/truper_logo2.png">
            <h1>Truper</h1>
            <p>Tu Distribuidor de Herramientas y Productos de Confianza</p>
            <a href="/views/products.php" class="btn-primary">Ver Catálogo</a>
        </div>
    </section>

                <div class="logo">
                    <span class="logo-text">Truper</span>
                </div>
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

    <button id="openCart" class="cart-fab" style="display: none;">Carrito (<span id="cartCount">0</span>)</button>
    <aside id="cartDrawer" class="cart-drawer" style="display: none;">
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

    <footer class="footer">
        <p>&copy; 2024 Truper. Todos los derechos reservados.</p>
        <p>Contacto: info@truper.com | Teléfono: +1-234-567-8900</p>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script src="/public/js/jspdf.umd.min.js"></script>
    <script src="/public/js/catalog.js"></script>
    <script>
        // Manejo del carrito en página principal
        document.addEventListener('DOMContentLoaded', function() {
            const openCartBtn = document.getElementById('openCart');
            const cartDrawer = document.getElementById('cartDrawer');
            const closeCartBtn = document.getElementById('closeCart');
            
            // Actualizar visibilidad del botón de carrito
            function updateCartVisibility() {
                try {
                    const cart = JSON.parse(localStorage.getItem('truper_cart') || '[]');
                    if (openCartBtn) {
                        openCartBtn.style.display = cart.length > 0 ? 'block' : 'none';
                    }
                } catch (e) {}
            }
            
            if (openCartBtn) {
                openCartBtn.addEventListener('click', function() {
                    if (cartDrawer) {
                        cartDrawer.classList.add('open');
                    }
                });
            }
            
            if (closeCartBtn) {
                closeCartBtn.addEventListener('click', function() {
                    if (cartDrawer) {
                        cartDrawer.classList.remove('open');
                    }
                });
            }
            
            // Cerrar carrito al hacer clic fuera
            if (cartDrawer) {
                document.addEventListener('click', function(e) {
                    if (cartDrawer.classList.contains('open') && 
                        !cartDrawer.contains(e.target) && 
                        !openCartBtn.contains(e.target)) {
                        cartDrawer.classList.remove('open');
                    }
                });
            }
            
            updateCartVisibility();
            
            // Actualizar visibilidad cuando cambie el carrito
            window.addEventListener('storage', updateCartVisibility);
        });
    </script>
</body>
</html>



