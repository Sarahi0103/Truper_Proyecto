<?php
require_once '../config/config.php';
require_login();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'client', ENT_QUOTES, 'UTF-8');
$is_admin = (($_SESSION['role'] ?? '') === 'admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Truper Platform</title>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <link rel="stylesheet" href="css/styles.css">    <link rel="stylesheet" href="css/theme.css">    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .orders-page main {
            padding-top: 2rem;
        }

        .orders-page .tabs {
            border: 1px solid var(--ui-border);
            border-radius: 12px;
            padding: 0.35rem;
            background: var(--ui-surface);
            gap: 0.4rem;
        }

        .orders-page .tab-button {
            border-radius: 9px;
            border-bottom: 0;
        }

        .orders-page .tab-button.active {
            color: #fff;
        }

        .orders-page .header-actions {
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .orders-page .nav-menu {
            flex-wrap: nowrap;
            overflow-x: auto;
            scrollbar-width: thin;
            gap: 0.5rem;
        }

        .orders-page .nav-menu a {
            white-space: nowrap;
        }

        .orders-page .header-actions .user-menu {
            margin-left: 0;
        }

        .orders-page .form-section h3 {
            color: var(--ui-text);
        }

        .orders-page #orderSearch,
        .orders-page #orderFilter,
        .orders-page #productSearch,
        .orders-page #productCategoryFilter,
        .orders-page #qty {
            background: var(--ui-surface);
            color: var(--ui-text);
            border: 1px solid var(--ui-border);
        }

        .orders-page .order-summary {
            background: var(--ui-surface);
            border: 1px solid var(--ui-border);
            border-radius: 12px;
        }

        .orders-page .order-layout {
            --orders-panel-height: auto;
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
            align-items: start;
        }

        .orders-page .order-panel {
            min-width: 0;
            min-height: 0;
            height: auto;
            display: flex;
            flex-direction: column;
        }

        .orders-page .order-panel.sticky-panel {
            position: static;
            overflow: hidden;
        }

        .orders-page .form-section {
            margin-bottom: 0;
        }

        .orders-page .form-section + .form-section {
            margin-top: 1.25rem;
        }

        .orders-page .summary-row {
            color: var(--ui-text);
        }

        .orders-page table {
            background: var(--ui-surface);
            border: 1px solid var(--ui-border);
            border-radius: 12px;
            overflow: hidden;
        }

        .orders-page thead,
        .orders-page thead th {
            background: var(--ui-surface-soft);
            color: var(--ui-text);
            border-color: var(--ui-border);
        }

        .orders-page tbody td {
            color: var(--ui-text);
            border-color: var(--ui-border);
            background: transparent;
        }

        .orders-page tbody tr {
            background: var(--ui-surface);
        }

        .orders-page tbody tr:nth-child(2n) {
            background: var(--ui-surface-soft);
        }

        .orders-page tbody tr:hover {
            background: var(--theme-accent-soft);
        }

        .orders-page .products-scroll {
            flex: 1 1 auto;
            min-height: 0;
            max-height: clamp(340px, calc(100vh - 360px), 620px);
            overflow: auto;
            border: 1px solid var(--ui-border);
            border-radius: 12px;
            background: var(--ui-surface);
        }

        .orders-page .products-scroll table {
            margin: 0;
            border: 0;
            border-radius: 0;
        }

        .orders-page .products-scroll thead th {
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .orders-page .cart-scroll {
            flex: 1 1 auto;
            min-height: 180px;
            max-height: clamp(280px, calc(100vh - 430px), 460px);
            overflow: auto;
            border: 1px solid var(--ui-border);
            border-radius: 12px;
            background: var(--ui-surface);
        }

        .orders-page .cart-scroll table {
            margin: 0;
            border: 0;
            border-radius: 0;
        }

        .orders-page .cart-scroll thead th {
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .orders-page #productsList td,
        .orders-page #productsList th {
            padding-top: 0.55rem;
            padding-bottom: 0.55rem;
            vertical-align: middle;
        }

        .orders-page #cartItems td,
        .orders-page #cartItems th {
            vertical-align: middle;
        }

        .orders-page #cartItems input[type="number"] {
            max-width: 82px;
            min-height: 36px;
            text-align: center;
            margin: 0;
        }

        .orders-page #productsList input[type="number"] {
            max-width: 70px;
            min-height: 36px;
            text-align: center;
            margin: 0;
        }

        .orders-page #productsList .btn {
            min-height: 36px;
            padding: 0.4rem 0.85rem;
            border-radius: 10px;
        }

        .orders-page .order-status-select {
            min-width: 150px;
            padding: 0.35rem 0.55rem;
            border-radius: 8px;
            border: 1px solid var(--ui-border);
            background: var(--ui-surface);
            color: var(--ui-text);
            font-weight: 700;
        }

        .orders-page .order-status-readonly {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 110px;
            padding: 0.28rem 0.6rem;
            border-radius: 999px;
            border: 1px solid transparent;
            font-weight: 600;
            line-height: 1.2;
        }

        .orders-page .status-pending {
            background: rgba(245, 158, 11, 0.16);
            border-color: rgba(245, 158, 11, 0.45);
            color: #b45309;
        }

        .orders-page .status-confirmed {
            background: rgba(59, 130, 246, 0.14);
            border-color: rgba(59, 130, 246, 0.45);
            color: #1d4ed8;
        }

        .orders-page .status-processing {
            background: rgba(124, 58, 237, 0.16);
            border-color: rgba(124, 58, 237, 0.45);
            color: #6d28d9;
        }

        .orders-page .status-shipped {
            background: rgba(14, 165, 233, 0.16);
            border-color: rgba(14, 165, 233, 0.45);
            color: #0369a1;
        }

        .orders-page .status-delivered {
            background: rgba(34, 197, 94, 0.15);
            border-color: rgba(34, 197, 94, 0.45);
            color: #15803d;
        }

        .orders-page .status-cancelled {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.45);
            color: #b91c1c;
        }

        :root[data-theme="dark"] .orders-page .status-pending {
            color: #fbbf24;
        }

        :root[data-theme="dark"] .orders-page .status-confirmed {
            color: #93c5fd;
        }

        :root[data-theme="dark"] .orders-page .status-processing {
            color: #c4b5fd;
        }

        :root[data-theme="dark"] .orders-page .status-shipped {
            color: #7dd3fc;
        }

        :root[data-theme="dark"] .orders-page .status-delivered {
            color: #86efac;
        }

        :root[data-theme="dark"] .orders-page .status-cancelled {
            color: #fca5a5;
        }

        @media (max-width: 900px) {
            .orders-page main {
                padding-top: 1.2rem;
            }

            .orders-page .order-layout {
                grid-template-columns: 1fr;
                --orders-panel-height: auto;
            }

            .orders-page .order-panel.sticky-panel {
                position: static;
            }

            .orders-page .order-panel {
                min-height: auto;
                height: auto;
            }

            .orders-page .products-scroll {
                max-height: 340px;
            }

            .orders-page .cart-scroll {
                max-height: 340px;
            }
        }
    </style>
</head>
<body class="orders-page">
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <a href="cart.php">Carrito</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="orders.php" class="active">Pedidos</a>
                <a href="wholesale.php">Mayoreo</a>
                <?php if ($is_admin): ?><a href="cashier.php">Caja</a><?php endif; ?>
                <?php if ($is_admin): ?><a href="admin_supply.php">Abastecimiento</a><?php endif; ?>
                <a href="tasks.php">Tareas</a>
                <a href="analytics.php">Estadísticas</a>
                <a href="profile.php">Perfil</a>
            </nav>
            <div class="header-actions">
                <div class="theme-toggle">
                    <button type="button" data-theme-toggle-btn><span data-theme-toggle-label>Modo obscuro</span></button>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-name"><?php echo $user_name; ?></div>
                        <div class="user-role"><?php echo ucfirst($user_role); ?></div>
                    </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="container-fluid">
            <div class="d-flex justify-between align-center">
                <h1>Gestión de Pedidos</h1>
                <a href="#newOrder" onclick="document.querySelector('[data-tab=\'newOrder\']').click(); return false;" class="btn btn-primary">
                    ➕ Nueva Cotización
                </a>
            </div>

            <!-- TABS -->
            <div class="tabs">
                <button class="tab-button" data-tab="myOrders">Mis Pedidos</button>
                <button class="tab-button active" data-tab="newOrder">Crear Pedido</button>
            </div>

            <!-- MIS ÓRDENES -->
            <div id="myOrders" class="tab-content">
                <div class="card">
                    <div class="card-header">Mi Historial de Solicitudes</div>
                    <div class="card-body">
                        <div style="margin-bottom: 1rem;">
                            <input type="text" id="orderSearch" placeholder="Buscar orden..." onkeyup="searchOrders()" style="padding: 0.5rem; width: 200px;">
                            <select id="orderFilter" onchange="filterOrders()" style="padding: 0.5rem; margin-left: 1rem;">
                                <option value="">Todos los estados</option>
                                <option value="pending">Pendiente</option>
                                <option value="confirmed">Confirmado</option>
                                <option value="processing">En Proceso</option>
                                <option value="shipped">Enviado</option>
                                <option value="delivered">Completado</option>
                                <option value="cancelled">Cancelado</option>
                            </select>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Número de Orden</th>
                                    <th>Fecha</th>
                                    <th>Total estimado</th>
                                    <th>Seguimiento</th>
                                    <th>Estado Orden</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="ordersList">
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Cargando órdenes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- CREAR NUEVO PEDIDO -->
            <div id="newOrder" class="tab-content active">
                <div class="card">
                    <div class="card-header">Crear Nueva Cotización</div>
                    <div class="card-body">
                        <div class="alert alert-info" style="margin-bottom: 1rem;">Este portal no procesa pagos en línea.</div>
                        <div class="order-layout">
                            <div class="form-section order-panel">
                                <h3>Seleccionar Productos</h3>
                                <input type="text" id="productSearch" placeholder="Buscar productos..." onkeyup="searchProducts()" style="padding: 0.5rem; margin-bottom: 1rem; width: 100%;">
                                <select id="productCategoryFilter" onchange="loadProducts()" style="padding: 0.5rem; margin-bottom: 1rem; width: 100%; max-width: 360px;">
                                    <option value="">Todas las categorías</option>
                                </select>

                                <div class="products-scroll">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>SKU</th>
                                                <th>Precio Unitario</th>
                                                <th>Cantidad</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="productsList">
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Cargando productos...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="form-section order-panel sticky-panel">
                                <h3>Resumen del Pedido</h3>
                                <div class="order-summary" style="margin-bottom: 1rem;">
                                    <div class="summary-row">
                                        <span>Subtotal:</span>
                                        <span id="cartSubtotal">$0</span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Descuentos:</span>
                                        <span id="cartDiscount">$0</span>
                                    </div>
                                    <div class="summary-row total">
                                        <span>Total estimado:</span>
                                        <span id="cartTotal">$0</span>
                                    </div>
                                </div>

                                <div class="cart-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Precio Unit.</th>
                                            <th>Subtotal</th>
                                            <th>Descuento</th>
                                            <th>Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="cartItems">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Tu carrito está vacío</td>
                                        </tr>
                                    </tbody>
                                </table>
                                </div>

                                <div class="form-group mt-3">
                                    <label>
                                        <input type="checkbox" id="isWholesale"> Este es un pedido al por mayor
                                    </label>
                                </div>

                                <div class="form-group mt-3">
                                    <label for="orderNotes">Notas Adicionales</label>
                                    <textarea id="orderNotes" placeholder="Agrega notas sobre tu pedido..." style="width: 100%;"></textarea>
                                </div>

                                <div class="grid grid-2 mt-3">
                                    <div class="form-group">
                                        <label for="specialEvent">Fecha o evento especial (opcional)</label>
                                        <input type="text" id="specialEvent" placeholder="Ej. Buen Fin, Navidad, Inicio de obra">
                                    </div>
                                </div>

                                <div class="btn-group mt-4">
                                    <button type="button" class="btn btn-secondary" onclick="clearCart()">Limpiar Carrito</button>
                                    <button type="button" class="btn btn-primary" onclick="createOrder()">Enviar Cotización por WhatsApp</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h4>Truper</h4>
                <p>Plataforma de Gestión Empresarial</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Truper Platform. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script>
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
        window.TRUPER_COMPANY_WHATSAPP = '<?php echo htmlspecialchars(whatsapp_phone_digits(), ENT_QUOTES, 'UTF-8'); ?>';
        window.TRUPER_ORDERS_ROLE = '<?php echo htmlspecialchars($_SESSION['role'] ?? 'client', ENT_QUOTES, 'UTF-8'); ?>';
    </script>
    <script src="js/orders.js?v=20260424"></script>
    <script src="js/barcode-scanner.js"></script>
    <script>
        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }
    </script>
</body>
</html>
