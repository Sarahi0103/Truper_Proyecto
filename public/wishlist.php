<?php
/**
 * Lista de Deseos / Favoritos - Ferretería FOX
 * Truper Platform
 */
require_once __DIR__ . '/../config/config.php';

$isLogged = isset($_SESSION['user_id']);
$user_name = htmlspecialchars($_SESSION['name'] ?? ($_SESSION['first_name'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8');
$userRole = $_SESSION['role'] ?? 'client';
$isAdmin = $isLogged && in_array($userRole, ['admin', 'employee']);
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mis Favoritos — Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.2">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <link rel="stylesheet" href="css/toast-notifications.css">
    <style>
        body {
            background: #08080a;
            color: #ffffff;
            font-family: var(--theme-font, 'Outfit', 'Inter', sans-serif);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        .wishlist-hero {
            background: radial-gradient(circle at 10% 20%, rgba(255, 127, 0, 0.12), transparent 45%),
                        linear-gradient(135deg, #0e0e13 0%, #08080a 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 2.5rem 1rem;
            text-align: center;
        }

        .wishlist-hero h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            background: linear-gradient(90deg, #ffffff, #ff7f00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .wishlist-hero p {
            color: #94a3b8;
            font-size: 1rem;
            margin: 0 auto;
            max-width: 600px;
        }

        .wishlist-container {
            max-width: 1280px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1.25rem;
            flex: 1;
            box-sizing: border-box;
        }

        .wishlist-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .wishlist-count {
            font-size: 1rem;
            color: #cbd5e1;
            font-weight: 600;
        }

        .wishlist-count span {
            color: #ff7f00;
            font-size: 1.1rem;
        }

        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .wishlist-card {
            background: rgba(20, 20, 26, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }

        .wishlist-card:hover {
            transform: translateY(-4px);
            border-color: rgba(255, 127, 0, 0.4);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5), 0 0 20px rgba(255, 127, 0, 0.1);
        }

        .card-img-wrap {
            height: 200px;
            background: #111115;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
        }

        .card-img-wrap img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .wishlist-card:hover .card-img-wrap img {
            transform: scale(1.05);
        }

        .btn-remove-fav {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ef4444;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1.1rem;
        }

        .btn-remove-fav:hover {
            background: #ef4444;
            color: #ffffff;
            transform: scale(1.1);
        }

        .card-body {
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .card-category {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #ff7f00;
            letter-spacing: 0.05em;
            margin-bottom: 0.35rem;
        }

        .card-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 0.5rem 0;
            line-height: 1.35;
            flex: 1;
        }

        .card-sku {
            font-family: monospace;
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 0.75rem;
        }

        .card-price-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .card-price {
            font-size: 1.4rem;
            font-weight: 800;
            color: #ffffff;
        }

        .card-stock-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
        }

        .stock-in {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .stock-out {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .card-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }

        .btn-add-cart {
            background: linear-gradient(135deg, #ff7f00, #ff9900);
            color: #ffffff;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(255, 127, 0, 0.25);
        }

        .btn-add-cart:hover {
            background: linear-gradient(135deg, #ff8f1a, #ffa826);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(255, 127, 0, 0.35);
        }

        .btn-view-detail {
            background: #1e1e24;
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.6rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-view-detail:hover {
            background: #282832;
            color: #ffffff;
        }

        /* Empty state */
        .empty-wishlist {
            text-align: center;
            padding: 5rem 1rem;
            background: rgba(20, 20, 26, 0.4);
            border: 1px dashed rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            margin: 2rem 0;
        }

        .empty-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            opacity: 0.7;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .empty-desc {
            color: #94a3b8;
            font-size: 0.95rem;
            margin-bottom: 1.75rem;
        }

        .btn-browse {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #ff7f00, #ff9900);
            color: #ffffff;
            padding: 0.85rem 2rem;
            border-radius: 999px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(255, 127, 0, 0.3);
            transition: all 0.2s ease;
        }

        .btn-browse:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 22px rgba(255, 127, 0, 0.45);
        }

        .toast-msg {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1e1e26;
            color: #ffffff;
            border: 1px solid rgba(255, 127, 0, 0.4);
            padding: 0.85rem 1.4rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.6);
            z-index: 999999;
            animation: toastSlideUp 0.3s ease;
        }

        @keyframes toastSlideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Navbar Header -->
    <header>
        <div class="header-content">
            <a href="index.php" class="logo">
                <img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px; width: auto; object-fit: contain;">
            </a>
            <nav class="nav-menu">
                <a href="index.php">Productos</a>
                <a href="tienda.php">Tienda en Línea</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <a href="wishlist.php" class="active">Favoritos</a>
                <a href="cart.php">Carrito</a>
                <?php if ($isLogged): ?>
                    <a href="customer_dashboard.php">Mi Cuenta</a>
                    <?php if ($isAdmin): ?>
                        <a href="dashboard.php" style="color: #ff7f00;">Panel Admin</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php">Iniciar Sesión</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Hero -->
    <div class="wishlist-hero">
        <h1>❤️ Mis Productos Favoritos</h1>
        <p>Gestiona tus herramientas guardadas, consulta disponibilidad en tiempo real y agrégalas a tu carrito con un solo clic.</p>
    </div>

    <!-- Content -->
    <main class="wishlist-container">
        <div class="wishlist-controls">
            <div class="wishlist-count" id="wishlistCountLabel">
                Cargando favoritos...
            </div>
            <div>
                <a href="tienda.php" class="btn-view-detail" style="display: inline-block; padding: 0.5rem 1rem;">
                    ← Seguir Comprando
                </a>
            </div>
        </div>

        <div id="wishlistGrid" class="wishlist-grid">
            <!-- Renderizado dinámicamente -->
        </div>

        <div id="emptyState" class="empty-wishlist" style="display: none;">
            <div class="empty-icon">🤍</div>
            <div class="empty-title">Tu lista de favoritos está vacía</div>
            <div class="empty-desc">Explora nuestro catálogo y presiona el corazón en cualquier herramienta para guardarla aquí.</div>
            <a href="tienda.php" class="btn-browse">Explorar Catálogo de Herramientas</a>
        </div>
    </main>

    <script>
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>';
        const isUserLogged = <?php echo $isLogged ? 'true' : 'false'; ?>;
        const CART_KEY = 'truper_cart_items';

        async function loadWishlist() {
            const grid = document.getElementById('wishlistGrid');
            const emptyState = document.getElementById('emptyState');
            const countLabel = document.getElementById('wishlistCountLabel');

            if (!isUserLogged) {
                // Modo invitado: cargar desde localStorage
                let localFavorites = JSON.parse(localStorage.getItem('user_favorites') || '[]');
                if (localFavorites.length === 0) {
                    grid.innerHTML = '';
                    emptyState.style.display = 'block';
                    countLabel.innerHTML = 'Total guardados: <span>0</span> productos';
                    return;
                }

                // Cargar detalles de productos
                countLabel.innerHTML = `Total guardados: <span>${localFavorites.length}</span> productos`;
                renderLocalWishlist(localFavorites);
                return;
            }

            try {
                const response = await fetch('/api/wishlist.php?action=list');
                const data = await response.json();

                if (data.success && Array.isArray(data.wishlist) && data.wishlist.length > 0) {
                    emptyState.style.display = 'none';
                    countLabel.innerHTML = `Total guardados: <span>${data.wishlist.length}</span> productos`;
                    renderWishlistCards(data.wishlist);
                } else {
                    grid.innerHTML = '';
                    emptyState.style.display = 'block';
                    countLabel.innerHTML = 'Total guardados: <span>0</span> productos';
                }
            } catch (err) {
                console.error('Error cargando wishlist:', err);
                grid.innerHTML = '';
                emptyState.style.display = 'block';
                countLabel.innerHTML = 'No se pudo cargar la lista';
            }
        }

        function renderWishlistCards(items) {
            const grid = document.getElementById('wishlistGrid');
            grid.innerHTML = items.map(item => {
                const price = parseFloat(item.price || 0).toFixed(2);
                const inStock = (parseInt(item.stock || 0) > 0);
                const stockLabel = inStock ? `Stock: ${item.stock}` : 'Agotado';
                const stockClass = inStock ? 'stock-in' : 'stock-out';

                return `
                    <div class="wishlist-card" id="card-${item.id}">
                        <div class="card-img-wrap">
                            <img src="${item.image_url || 'images/products/default-product.svg'}" alt="${item.name}">
                            <button type="button" class="btn-remove-fav" onclick="removeFavorite(${item.id})" title="Quitar de favoritos">
                                &times;
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="card-category">${item.category || 'General'}</div>
                            <div class="card-title">${item.name}</div>
                            <div class="card-sku">SKU: ${item.sku || 'N/A'}</div>
                            
                            <div class="card-price-row">
                                <div class="card-price">$${price} <span style="font-size:0.75rem; color:#888;">MXN</span></div>
                                <span class="card-stock-badge ${stockClass}">${stockLabel}</span>
                            </div>

                            <div class="card-actions">
                                <button type="button" class="btn-add-cart" onclick="addToCartFromWishlist(${item.id}, '${escapeHtml(item.name)}', ${price}, '${item.sku}', '${item.image_url}')">
                                    🛒 Agregar al Carrito
                                </button>
                                <a href="product_detail.php?id=${item.id}" class="btn-view-detail">
                                    Ver Detalles Técnicos
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderLocalWishlist(items) {
            // Renderiza los items cacheados localmente para invitados
            renderWishlistCards(items);
        }

        async function removeFavorite(productId) {
            if (!isUserLogged) {
                let localFavorites = JSON.parse(localStorage.getItem('user_favorites') || '[]');
                localFavorites = localFavorites.filter(p => p.id !== productId);
                localStorage.setItem('user_favorites', JSON.stringify(localFavorites));
                showToast('Producto eliminado de tus favoritos');
                loadWishlist();
                return;
            }

            try {
                const response = await fetch('/api/wishlist.php?action=remove', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.csrfToken
                    },
                    body: JSON.stringify({ product_id: productId })
                });
                const res = await response.json();
                if (res.success) {
                    showToast('Producto eliminado de favoritos');
                    const card = document.getElementById(`card-${productId}`);
                    if (card) {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => loadWishlist(), 250);
                    } else {
                        loadWishlist();
                    }
                } else {
                    showToast(res.error || 'Error al eliminar');
                }
            } catch (err) {
                console.error('Error:', err);
                showToast('Error de conexión');
            }
        }

        function addToCartFromWishlist(id, name, price, sku, image) {
            let cart = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
            const existing = cart.find(i => i.id == id);
            if (existing) {
                existing.quantity = (existing.quantity || 1) + 1;
            } else {
                cart.push({
                    id: id,
                    name: name,
                    unit_price: parseFloat(price),
                    price: parseFloat(price),
                    sku: sku,
                    image_url: image,
                    quantity: 1
                });
            }
            localStorage.setItem(CART_KEY, JSON.stringify(cart));
            showToast('✅ Producto agregado a tu carrito');
        }

        function showToast(msg) {
            const old = document.querySelector('.toast-msg');
            if (old) old.remove();
            const el = document.createElement('div');
            el.className = 'toast-msg';
            el.textContent = msg;
            document.body.appendChild(el);
            setTimeout(() => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 300);
            }, 3000);
        }

        function escapeHtml(str) {
            return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        document.addEventListener('DOMContentLoaded', loadWishlist);
    </script>
</body>
</html>
