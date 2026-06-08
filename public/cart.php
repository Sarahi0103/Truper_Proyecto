<?php
require_once '../config/config.php';

$isLogged = isset($_SESSION['user_id']);
$isAdmin = $isLogged && (($_SESSION['role'] ?? '') === 'admin');
$is_admin = $isAdmin;

$clientTicketCode = 'PUBLICO';
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
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mi Carrito - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css?v=2.2">
    <link rel="stylesheet" href="css/theme.css?v=2.5">
    <link rel="stylesheet" href="css/responsive-complete.css?v=2.2">
    <style>
        /* ===== Cart Page — Premium Redesign ===== */
        body {
            background: #08080a !important;
            color: #ffffff !important;
        }

        .cart-page {
            padding: 2.5rem 1.5rem !important;
            max-width: 1200px;
            margin: 0 auto;
        }

        .cart-page-header {
            margin-bottom: 2.5rem !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 1rem !important;
            border-bottom: 1px solid #222222 !important;
            padding-bottom: 1.25rem !important;
        }

        .cart-page-title {
            margin: 0 !important;
            font-size: 2.25rem !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            background: linear-gradient(90deg, #ffffff, #ffb347) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            letter-spacing: -0.02em !important;
        }

        .cart-page-back {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            color: var(--theme-accent, #ff7f00) !important;
            text-decoration: none !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            transition: all 0.2s ease !important;
            background: rgba(255, 127, 0, 0.1) !important;
            padding: 0.5rem 1rem !important;
            border-radius: 999px !important;
            border: 1px solid rgba(255, 127, 0, 0.2) !important;
        }

        .cart-page-back:hover {
            background: var(--theme-accent, #ff7f00) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(255,127,0,0.3) !important;
            text-decoration: none !important;
        }

        .cart-container {
            display: grid !important;
            grid-template-columns: 1fr 350px !important;
            gap: 2rem !important;
        }

        .cart-items-section {
            background: #111111 !important;
            border: 1px solid #222222 !important;
            border-radius: 20px !important;
            padding: 1.5rem !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
        }

        .cart-empty {
            text-align: center !important;
            padding: 4rem 2rem !important;
            color: #888888 !important;
        }

        .cart-empty-icon {
            font-size: 3.5rem !important;
            margin-bottom: 1.25rem !important;
            display: block;
        }

        .cart-empty-text {
            font-size: 1.15rem !important;
            margin-bottom: 1.5rem !important;
            color: #aaaaaa !important;
        }

        .cart-empty-btn {
            display: inline-block !important;
            background: linear-gradient(90deg, #ff6600, #ff9500) !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            padding: 0.75rem 2rem !important;
            border-radius: 999px !important;
            text-decoration: none !important;
            box-shadow: 0 4px 10px rgba(255, 102, 0, 0.2) !important;
            transition: all 0.2s ease !important;
        }

        .cart-empty-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(255, 102, 0, 0.35) !important;
            color: #ffffff !important;
        }

        .cart-item {
            display: grid !important;
            grid-template-columns: 90px 1fr auto !important;
            gap: 1.5rem !important;
            padding: 1.5rem 0 !important;
            border-bottom: 1px solid #222222 !important;
            align-items: center !important;
        }

        .cart-item:first-child {
            padding-top: 0 !important;
        }

        .cart-item:last-child {
            border-bottom: none !important;
            padding-bottom: 0 !important;
        }

        .cart-item-image {
            width: 90px !important;
            height: 90px !important;
            background: #0a0a0c !important;
            border: 1px solid #222222 !important;
            border-radius: 12px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            overflow: hidden !important;
        }

        .cart-item-image img {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
            padding: 0.5rem !important;
        }

        .cart-item-details {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.35rem !important;
        }

        .cart-item-name {
            font-size: 1.1rem !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            margin: 0 !important;
            line-height: 1.4 !important;
        }

        .cart-item-sku {
            font-size: 0.85rem !important;
            color: #666666 !important;
        }

        .cart-item-sku strong {
            color: #888888 !important;
        }

        .cart-item-price {
            font-size: 1.2rem !important;
            font-weight: 800 !important;
            color: var(--theme-accent, #ff7f00) !important;
        }

        .cart-item-actions {
            display: flex !important;
            align-items: center !important;
            gap: 0.75rem !important;
            flex-wrap: nowrap !important;
        }

        .qty-control {
            display: flex !important;
            align-items: center !important;
            border: 1px solid #2a2a30 !important;
            border-radius: 8px !important;
            background: #0d0d0d !important;
            overflow: hidden;
        }

        .qty-btn {
            width: 36px !important;
            height: 36px !important;
            border: none !important;
            background: transparent !important;
            color: #ffffff !important;
            cursor: pointer !important;
            font-weight: bold !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: background 0.2s ease !important;
            font-size: 1.1rem !important;
        }

        .qty-btn:hover {
            background: #222222 !important;
        }

        .qty-input {
            width: 45px !important;
            border: none !important;
            background: transparent !important;
            text-align: center !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
        }

        .remove-btn {
            width: 36px !important;
            height: 36px !important;
            border: none !important;
            background: rgba(239, 68, 68, 0.1) !important;
            border: 1px solid rgba(239, 68, 68, 0.2) !important;
            color: #ef4444 !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            font-size: 1rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.2s ease !important;
        }

        .remove-btn:hover {
            background: #ef4444 !important;
            border-color: #ef4444 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2) !important;
        }

        .cart-summary {
            background: #111111 !important;
            border: 1px solid #222222 !important;
            border-radius: 20px !important;
            padding: 1.75rem !important;
            position: sticky !important;
            top: 24px !important;
            height: fit-content !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
        }

        .summary-title {
            font-size: 1.35rem !important;
            font-weight: 800 !important;
            margin-bottom: 1.5rem !important;
            color: #ffffff !important;
            border-left: 4px solid var(--theme-accent, #ff7f00) !important;
            padding-left: 0.75rem !important;
            line-height: 1.2 !important;
        }

        .summary-row {
            display: flex !important;
            justify-content: space-between !important;
            margin-bottom: 1rem !important;
            color: #aaaaaa !important;
            font-size: 0.95rem !important;
        }

        .summary-row span:last-child {
            color: #ffffff !important;
            font-weight: 600 !important;
        }

        .summary-total {
            border-top: 1px solid #222222 !important;
            padding-top: 1.25rem !important;
            margin-top: 1.25rem !important;
            display: flex !important;
            justify-content: space-between !important;
            font-weight: 800 !important;
            font-size: 1.4rem !important;
            color: var(--theme-accent, #ff7f00) !important;
        }

        .summary-actions {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.8rem !important;
            margin-top: 1.75rem !important;
        }

        .summary-actions .btn-primary {
            background: linear-gradient(90deg, #ff6600, #ff9500) !important;
            border: none !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            padding: 0.75rem 1.25rem !important;
            border-radius: 999px !important;
            font-size: 0.95rem !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 4px 10px rgba(255, 102, 0, 0.2) !important;
            text-align: center;
        }

        .summary-actions .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(255, 102, 0, 0.35) !important;
            background: linear-gradient(90deg, #ff7711, #ffa522) !important;
        }

        .summary-actions .btn-secondary {
            background: #1a1a1a !important;
            border: 1px solid #2a2a2a !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            padding: 0.75rem 1.25rem !important;
            border-radius: 999px !important;
            font-size: 0.95rem !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            text-align: center;
        }

        .summary-actions .btn-secondary:hover {
            background: var(--theme-accent, #ff7f00) !important;
            border-color: var(--theme-accent, #ff7f00) !important;
            box-shadow: 0 4px 12px rgba(255, 127, 0, 0.3) !important;
        }

        .summary-actions .btn-ghost {
            background: transparent !important;
            border: 1px solid transparent !important;
            color: #888888 !important;
            font-weight: 600 !important;
            padding: 0.75rem 1.25rem !important;
            border-radius: 999px !important;
            font-size: 0.95rem !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            text-align: center;
        }

        .summary-actions .btn-ghost:hover {
            background: rgba(239, 68, 68, 0.1) !important;
            border-color: rgba(239, 68, 68, 0.2) !important;
            color: #ef4444 !important;
        }

        @media (max-width: 768px) {
            .cart-container { grid-template-columns: 1fr !important; }
            .cart-summary { position: static !important; }
            .cart-item {
                grid-template-columns: 70px 1fr !important;
                gap: 1rem !important;
            }
            .cart-item-actions {
                grid-column: 1 / span 2;
                justify-content: flex-end;
                margin-top: 0.5rem;
            }
            .cart-item-image { width: 70px !important; height: 70px !important; }
        }
    </style>
</head>
<body class="catalog-minimal">
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="img/logo_truper.1.1.png" alt="Truper" style="height: 40px; width: auto; object-fit: contain;"></a>
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <?php if ($isLogged): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="dashboard.php">Dashboard</a>
                            <a href="orders.php">Pedidos</a>
                            <a href="wholesale.php">Mayoreo</a>
                            <a href="account.php#historyTab">Historial</a>
                            <a href="profile.php">Perfil</a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($is_admin): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="cashier.php">Caja</a>
                            <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                            <a href="tickets.php">Tickets</a>
                            <a href="tasks.php">Tareas</a>
                            <a href="analytics.php">Estadísticas</a>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
            <div class="header-actions">

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
    <script src="js/main.js?v=2.6"></script>
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

        document.getElementById('shareWhatsApp')?.addEventListener('click', function() {
            const items = getStoredCart();
            if (items.length === 0) {
                alert('El carrito está vacío');
                return;
            }

            const total = items.reduce((sum, item) => sum + (Number(item.unit_price || 0) * Number(item.quantity || 0)), 0);
            const now = new Date();
            const ticketCode = `TCK-${String(now.getTime()).slice(-8)}`;
            const issueDate = now.toLocaleString('es-MX');
            const issueDateIso = now.toISOString();
            const clientCode = '<?php echo htmlspecialchars($clientTicketCode, ENT_QUOTES, 'UTF-8'); ?>';
            const companyWhatsApp = '<?php echo htmlspecialchars(whatsapp_phone_digits(), ENT_QUOTES, 'UTF-8'); ?>';

            const safeItems = items.map(item => ({
                name: decodeCartText(item.name),
                sku: String(item.sku || '').replace(/^XLS-/i, ''),
                quantity: Number(item.quantity || 0),
                price: Number(item.unit_price || 0)
            }));
            const encodedItems = encodeURIComponent(btoa(unescape(encodeURIComponent(JSON.stringify(safeItems)))));
            const ticketUrl = `${window.location.origin}/ticket_quote.php?folio=${encodeURIComponent(ticketCode)}&issued_at=${encodeURIComponent(issueDateIso)}&client=${encodeURIComponent(clientCode)}&total=${encodeURIComponent(total.toFixed(2))}&items=${encodedItems}&format=thermal&auto_pdf=1`;

            let message = 'TRUPER - COTIZACION\n';
            message += '===========================\n';
            message += `Folio: ${ticketCode}\n`;
            message += `Fecha: ${issueDate}\n`;
            message += `Cliente: ${clientCode}\n`;
            message += '---------------------------\n';
            message += 'PRODUCTOS:\n';
            items.forEach((item, idx) => {
                const code = String(item.sku || '').replace(/^XLS-/i, '') || 'N/A';
                const lineTotal = (Number(item.unit_price || 0) * Number(item.quantity || 0));
                message += `- ${decodeCartText(item.name)}\n`;
                message += `  Codigo: ${code}\n`;
                message += `  ${item.quantity} x $${Number(item.unit_price).toFixed(2)} = $${lineTotal.toFixed(2)}\n`;
                if (idx < (items.length - 1)) {
                    message += '---------------------------\n';
                }
            });
            message += '---------------------------\n';
            message += `TOTAL: $${total.toFixed(2)}\n`;
            message += `PDF/Ticket: ${ticketUrl}\n\n`;
            message += 'Quedo atento(a) a disponibilidad y tiempo de entrega.';

            const encodedMsg = encodeURIComponent(message);
            const whatsappUrl = `https://wa.me/${companyWhatsApp}?text=${encodedMsg}`;
            window.open(whatsappUrl, '_blank');
        });

        document.addEventListener('DOMContentLoaded', async function() {
            const cart = await hydrateCartImages();
            renderCartPage(cart);
        });
    </script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
