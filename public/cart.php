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
    <title>Mi Carrito - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .cart-page { padding: 2rem 1rem; }
        .cart-page-header { 
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .cart-page-title {
            margin: 0;
            font-size: 2rem;
            color: var(--theme-text);
        }
        .cart-page-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--theme-accent);
            text-decoration: none;
            font-weight: 500;
        }
        .cart-page-back:hover { text-decoration: underline; }

        .cart-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
            max-width: 1200px;
        }

        .cart-items-section {
            background: var(--theme-surface);
            border: 1px solid var(--theme-border);
            border-radius: 8px;
            padding: 1.5rem;
        }

        .cart-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--theme-text-muted);
        }

        .cart-empty-icon { font-size: 3rem; margin-bottom: 1rem; }
        .cart-empty-text { margin-bottom: 1rem; }
        .cart-empty-btn { display: inline-block; }

        .cart-item {
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 1rem;
            padding: 1.5rem;
            border-bottom: 1px solid var(--theme-border);
            align-items: center;
        }

        .cart-item:last-child { border-bottom: none; }

        .cart-item-image {
            width: 80px;
            height: 80px;
            background: var(--theme-surface-strong);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cart-item-details { display: flex; flex-direction: column; gap: 0.5rem; }
        .cart-item-name { 
            font-weight: 600;
            color: var(--theme-text);
            margin: 0;
        }
        .cart-item-sku { 
            font-size: 0.85rem;
            color: var(--theme-text-muted);
        }
        .cart-item-price { 
            font-weight: 600;
            color: var(--theme-accent);
        }

        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .qty-control {
            display: flex;
            align-items: center;
            border: 1px solid var(--theme-border);
            border-radius: 4px;
            background: var(--theme-surface-strong);
        }

        .qty-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: transparent;
            color: var(--theme-text);
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-btn:hover { background: var(--theme-border); }

        .qty-input {
            width: 50px;
            border: none;
            background: transparent;
            text-align: center;
            color: var(--theme-text);
            font-weight: 600;
        }

        .qty-input::-webkit-outer-spin-button,
        .qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .qty-input[type=number] {
            -moz-appearance: textfield;
        }

        .remove-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: #ef4444;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .remove-btn:hover { background: #dc2626; }

        .cart-summary {
            background: var(--theme-surface);
            border: 1px solid var(--theme-border);
            border-radius: 8px;
            padding: 1.5rem;
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        .summary-title {
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--theme-text);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: var(--theme-text);
        }

        .summary-total {
            border-top: 2px solid var(--theme-border);
            padding-top: 1rem;
            margin-top: 1rem;
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--theme-accent);
        }

        .summary-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .btn-full { width: 100%; }

        @media (max-width: 768px) {
            .cart-container { grid-template-columns: 1fr; }
            .cart-summary { position: static; }
            .cart-item { grid-template-columns: 60px 1fr 50px; font-size: 0.9rem; }
            .cart-item-image { width: 60px; height: 60px; }
        }
    </style>
</head>
<body data-theme="light">
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
            <nav class="nav-menu">
                <a href="index.php">Productos</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <a href="cart.php" class="active">Carrito</a>
                <?php if ($isAdmin): ?><a href="admin_supply.php">Abastecimiento</a><?php endif; ?>
                <?php if ($isLogged): ?>
                    <a href="orders.php">Pedidos</a>
                    <?php if ($isAdmin): ?><a href="cashier.php">Caja</a><?php endif; ?>
                    <a href="dashboard.php">Dashboard</a>
                <?php endif; ?>
            </nav>
            <div class="header-actions">
                <div class="theme-toggle">
                    <button type="button" data-theme-toggle-btn><span data-theme-toggle-label>Modo obscuro</span></button>
                </div>
                <a href="https://wa.me/<?php echo htmlspecialchars(whatsapp_phone_digits(), ENT_QUOTES, 'UTF-8'); ?>?text=Hola%2C+tengo+una+duda+sobre+mi+carrito+y+cotizaciones." target="_blank" rel="noopener" class="btn btn-secondary btn-small">Dudas por WhatsApp</a>
                <?php if (!$isLogged): ?>
                    <a href="admin_login.php" class="btn btn-primary btn-small">Solo para administradores</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="cart-page">
        <div class="cart-page-header">
            <h1 class="cart-page-title">🛒 Mi Carrito</h1>
            <a href="index.php" class="cart-page-back">← Volver al Catálogo</a>
        </div>

        <div class="cart-container">
            <div class="cart-items-section">
                <div id="cartList" style="min-height: 200px;"></div>
            </div>

            <div class="cart-summary">
                <div class="summary-title">Resumen de Carrito</div>
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="cartSubtotal">$0</span>
                </div>
                <div class="summary-row">
                    <span>Artículos:</span>
                    <span id="cartItems">0</span>
                </div>
                <div class="summary-total">
                    <span>Total:</span>
                    <span id="cartTotalAmount">$0</span>
                </div>
                <div class="summary-actions">
                    <button id="printTicket" class="btn btn-primary btn-full">⬇️ Descargar Ticket</button>
                    <button id="shareWhatsApp" class="btn btn-secondary btn-full">📱 WhatsApp</button>
                    <button id="clearCart" class="btn btn-ghost btn-full">🗑️ Vaciar Carrito</button>
                </div>
            </div>
        </div>
    </main>

    <footer style="margin-top: 3rem; padding: 2rem; text-align: center; border-top: 1px solid var(--theme-border); color: var(--theme-text-muted);">
        <p>&copy; 2026 Truper Platform</p>
    </footer>

    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/catalog.js"></script>
    <script>
        function decodeCartText(value) {
            let result = String(value || '');
            if (!result) return '';

            const textarea = document.createElement('textarea');
            for (let i = 0; i < 3; i += 1) {
                textarea.innerHTML = result;
                const decoded = textarea.value;
                if (decoded === result) break;
                result = decoded;
            }

            return result;
        }

        function getStoredCart() {
            try {
                return JSON.parse(localStorage.getItem('truper_cart') || '[]');
            } catch (_) {
                return [];
            }
        }

        async function hydrateCartImages() {
            const cart = getStoredCart();
            const needsHydration = cart.some((item) => !item.image_url || item.image_url === 'images/products/default-product.svg');
            if (!needsHydration || cart.length === 0) {
                return cart;
            }

            try {
                const response = await fetch('/api/products.php?action=list', {
                    credentials: 'same-origin'
                });
                const payload = await response.json();
                const catalog = Array.isArray(payload?.products) ? payload.products : [];
                if (catalog.length === 0) {
                    return cart;
                }

                const bySku = new Map();
                const byId = new Map();
                catalog.forEach((product) => {
                    const sku = String(product?.sku || '').replace(/^XLS-/i, '').trim();
                    const id = String(product?.id || '').trim();
                    if (sku) bySku.set(sku, product);
                    if (id) byId.set(id, product);
                });

                let changed = false;
                const nextCart = cart.map((item) => {
                    const normalizedSku = String(item?.sku || '').replace(/^XLS-/i, '').trim();
                    const candidate = bySku.get(normalizedSku) || byId.get(String(item?.id || '').trim());
                    if (!candidate) {
                        return item;
                    }

                    const nextItem = { ...item };
                    if (!nextItem.image_url || nextItem.image_url === 'images/products/default-product.svg') {
                        nextItem.image_url = candidate.image_url || nextItem.image_url || 'images/products/default-product.svg';
                    }
                    if (!nextItem.name || nextItem.name !== decodeCartText(nextItem.name)) {
                        nextItem.name = candidate.name || decodeCartText(nextItem.name);
                    }

                    if (nextItem.image_url !== item.image_url || nextItem.name !== item.name) {
                        changed = true;
                    }

                    return nextItem;
                });

                if (changed) {
                    localStorage.setItem('truper_cart', JSON.stringify(nextCart));
                    return nextCart;
                }
            } catch (_) {
                return cart;
            }

            return cart;
        }

        function renderCartPage(cart = getStoredCart()) {
            const cartList = document.getElementById('cartList');

            if (cart.length === 0) {
                cartList.innerHTML = `
                    <div class="cart-empty">
                        <div class="cart-empty-icon">🛒</div>
                        <p class="cart-empty-text">Tu carrito está vacío</p>
                        <a href="index.php" class="btn btn-primary cart-empty-btn">Ir al Catálogo</a>
                    </div>
                `;
                updateSummary(cart);
                return;
            }

            cartList.innerHTML = cart.map((item, idx) => `
                <div class="cart-item">
                    <div class="cart-item-image">
                        <img src="${item.image_url || 'images/products/default-product.svg'}" alt="${decodeCartText(item.name)}" onerror="this.src='images/products/default-product.svg'">
                    </div>
                    <div class="cart-item-details">
                        <p class="cart-item-name">${decodeCartText(item.name)}</p>
                        <span class="cart-item-sku">SKU: ${String(item.sku || '').replace(/^XLS-/i, '')}</span>
                        <span class="cart-item-price">$${Number(item.unit_price).toFixed(2)}</span>
                    </div>
                    <div class="cart-item-actions">
                        <div class="qty-control">
                            <button class="qty-btn" onclick="changeCartQty('${item.sku}', -1)">−</button>
                            <input type="number" class="qty-input" value="${item.quantity}" onchange="setCartQty('${item.sku}', this.value)" min="1">
                            <button class="qty-btn" onclick="changeCartQty('${item.sku}', 1)">+</button>
                        </div>
                        <button class="remove-btn" onclick="removeFromCart('${item.sku}')">✕</button>
                    </div>
                </div>
            `).join('');

            updateSummary(cart);
        }

        function updateSummary(cart = getStoredCart()) {
            const items = cart.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
            const total = cart.reduce((sum, item) => sum + (Number(item.unit_price || 0) * Number(item.quantity || 0)), 0);

            document.getElementById('cartItems').textContent = items;
            document.getElementById('cartSubtotal').textContent = '$' + total.toFixed(2);
            document.getElementById('cartTotalAmount').textContent = '$' + total.toFixed(2);
        }

        function changeCartQty(sku, delta) {
            const cart = getStoredCart();
            const item = cart.find(p => p.sku === sku);
            if (item) {
                item.quantity = Math.max(1, Number(item.quantity || 1) + delta);
                localStorage.setItem('truper_cart', JSON.stringify(cart));
                renderCartPage();
            }
        }

        function setCartQty(sku, qty) {
            const cart = getStoredCart();
            const item = cart.find(p => p.sku === sku);
            if (item) {
                item.quantity = Math.max(1, Number(qty) || 1);
                localStorage.setItem('truper_cart', JSON.stringify(cart));
                renderCartPage();
            }
        }

        function removeFromCart(sku) {
            if (!confirm('¿Eliminar este producto del carrito?')) return;
            const cart = getStoredCart();
            const filtered = cart.filter(p => p.sku !== sku);
            localStorage.setItem('truper_cart', JSON.stringify(filtered));
            renderCartPage();
        }

        document.getElementById('clearCart')?.addEventListener('click', function() {
            if (!confirm('¿Vaciar todo el carrito?')) return;
            localStorage.removeItem('truper_cart');
            renderCartPage();
        });

        document.addEventListener('DOMContentLoaded', async function() {
            const cart = await hydrateCartImages();
            renderCartPage(cart);
        });
    </script>
</body>
</html>
