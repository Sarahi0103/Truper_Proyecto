<?php
require_once '../config/config.php';
require_login();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'client', ENT_QUOTES, 'UTF-8');
$is_admin = (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'employee');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Pedidos - Ferretería FOX</title>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <link rel="stylesheet" href="css/styles.css?v=4.1">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
    /* Order Management Premium Styles */
    .order-layout {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .order-panel {
        background: #1e1e1e;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }
    
    .order-panel h3 {
        font-size: 1.25rem;
        color: #fff;
        margin-bottom: 1.25rem;
        border-left: 3px solid #ff6600;
        padding-left: 0.5rem;
    }
    
    /* Scrollable areas for products list and cart */
    .products-scroll {
        max-height: 480px;
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 8px;
        background: rgba(0, 0, 0, 0.2);
        padding: 0.25rem;
        margin-top: 1rem;
    }
    
    .cart-scroll {
        max-height: 280px;
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 8px;
        background: rgba(0, 0, 0, 0.2);
        padding: 0.25rem;
        margin-top: 1rem;
    }
    
    /* Scrollbar styling */
    .products-scroll::-webkit-scrollbar, .cart-scroll::-webkit-scrollbar {
        width: 6px;
    }
    .products-scroll::-webkit-scrollbar-track, .cart-scroll::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.1);
    }
    .products-scroll::-webkit-scrollbar-thumb, .cart-scroll::-webkit-scrollbar-thumb {
        background: rgba(255, 102, 0, 0.3);
        border-radius: 4px;
    }
    .products-scroll::-webkit-scrollbar-thumb:hover, .cart-scroll::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 102, 0, 0.6);
    }
    
    /* Table inside scrolls adjustment */
    .products-scroll table, .cart-scroll table {
        margin-top: 0;
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }
    
    .products-scroll th, .cart-scroll th {
        position: sticky;
        top: 0;
        background: #252525;
        z-index: 5;
        box-shadow: 0 1px 0 rgba(255, 255, 255, 0.08);
    }
    
    /* Form elements */
    .order-panel .form-control, 
    .order-panel input[type="text"],
    .order-panel select,
    .order-panel textarea {
        background: #121212;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        color: #fff;
        padding: 0.6rem 0.8rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .order-panel .form-control:focus, 
    .order-panel input[type="text"]:focus,
    .order-panel select:focus,
    .order-panel textarea:focus {
        outline: none;
        border-color: #ff6600;
        box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.15);
    }
    
    .order-summary {
        background: rgba(255, 102, 0, 0.05);
        border: 1px solid rgba(255, 102, 0, 0.15);
        border-radius: 8px;
        padding: 1rem;
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }
    
    .summary-row.total {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px dashed rgba(255, 102, 0, 0.3);
        font-size: 1.15rem;
        font-weight: 700;
        color: #ff6600;
    }
    
    .btn-group {
        display: flex;
        gap: 0.75rem;
    }
    .btn-group .btn {
        flex: 1;
    }
    
    .btn-save-order {
        background-color: #28a745;
        color: #fff;
        border: 1px solid transparent;
        transition: all 0.3s ease;
    }
    
    .btn-save-order:hover {
        background-color: #218838;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }
    </style>
</head>
<body class="orders-page">
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px; width: auto; object-fit: contain;"></a>
                                    <button class="hamburger-btn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php" class="active">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="account.php#historyTab">Historial</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <?php if ($is_admin): ?>
                    <!-- Dropdowns de Administración Separados -->
                <!-- Dropdowns de Administración Separados -->
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Admin Tienda <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="admin_online_orders.php">🌐 Pedidos Online</a>
                        <a href="order_tracking.php">🚚 Seguimiento y Guías</a>
                        <a href="admin_online_billing.php">🏛️ Facturación & Pagos SAT</a>
                        <a href="rma_manager.php">🔄 Devoluciones RMA</a>
                    </div>
                </div>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Admin Local <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="ticket_validation.php">🏬 Validación Mostrador</a>
                        <a href="tickets.php">🎫 Historial de Tickets</a>
                        <a href="cashier.php">💵 Caja / Punto de Venta</a>
                        <a href="orders.php">📋 Ventas / Pedidos Mostrador</a>
                        <a href="tasks.php">👥 Tareas de Empleados</a>
                        <a href="b2b_approval.php">🤝 Aprobación B2B</a>
                    </div>
                </div>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Solo Admin <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="admin_supply.php?nocache=true">Abastecimiento / Precios</a>
                        <a href="accounting_reports.php">Reportes Contables</a>
                        <a href="gastos.php">Egresos / Gastos</a>
                        <a href="analytics.php">Estadísticas</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
            </nav>
            <div class="header-actions">

                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-name"><?php echo $user_name; ?></div>
                        <div class="user-role"><?php echo strtoupper($user_role); ?></div>
                    </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="container-fluid admin-supply-shell">
            <!-- ── Back Button ── -->
            <div class="back-header">
                <button onclick="history.back()" class="btn-back btn-back-dark btn-back-as-button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Regresar
                </button>
            </div>

            <div class="page-hero">
                <div class="module-badge module-admin"><span class="module-glyph">PD</span> Módulo de Pedidos</div>
                <h1>Gestión de Pedidos</h1>
                <p class="text-muted">Administra cotizaciones, ventas y seguimiento de órdenes en tiempo real.</p>
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
                        <div style="margin-bottom: 1rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                            <div>
                                <input type="text" id="orderSearch" placeholder="Buscar orden..." onkeyup="searchOrders()" style="padding: 0.5rem; width: 200px;">
                                <select id="orderFilter" onchange="filterOrders()" style="padding: 0.5rem; margin-left: 0.5rem;">
                                    <option value="">Todos los estados</option>
                                    <option value="pending">Pendiente</option>
                                    <option value="confirmed">Confirmado</option>
                                    <option value="processing">En Proceso</option>
                                    <option value="shipped">Enviado</option>
                                    <option value="delivered">Completado</option>
                                    <option value="cancelled">Cancelado</option>
                                </select>
                            </div>
                        </div>

                        <!-- Card de Descarga Masiva ZIP para Contabilidad -->
                        <div class="card" style="margin-bottom: 1.25rem; background: #121217; border: 1px solid #282836; border-radius: 10px;">
                            <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding: 1rem;">
                                <div>
                                    <h4 style="margin: 0 0 0.2rem; color: #fff; font-size: 0.98rem; font-weight:700;">📦 Paquete Contable Mensual (XML + PDF)</h4>
                                    <p style="margin: 0; color: #888899; font-size: 0.82rem;">Descarga en un solo archivo ZIP todos tus comprobantes del mes para entregar a tu contador.</p>
                                </div>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <select id="zipYear" style="background: #171720; color: #fff; border: 1px solid #333; padding: 6px 10px; border-radius: 6px; font-size:0.85rem;">
                                        <option value="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></option>
                                        <option value="<?php echo date('Y') - 1; ?>"><?php echo date('Y') - 1; ?></option>
                                    </select>
                                    <select id="zipMonth" style="background: #171720; color: #fff; border: 1px solid #333; padding: 6px 10px; border-radius: 6px; font-size:0.85rem;">
                                        <?php
                                        $mList = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
                                        foreach ($mList as $mn => $mName):
                                        ?>
                                            <option value="<?php echo $mn; ?>" <?php echo $mn == date('m') ? 'selected' : ''; ?>><?php echo $mName; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-primary" onclick="window.location.href='/api/download_monthly_invoices.php?year=' + document.getElementById('zipYear').value + '&month=' + document.getElementById('zipMonth').value" style="padding: 6px 14px; font-weight: 700; font-size:0.85rem;">
                                        Descargar ZIP
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
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
                                        <td colspan="6" class="text-center text-muted">Cargando ordenes...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
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
                                                <th>Stock</th>
                                                <th>Cantidad</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="productsList">
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">Cargando productos...</td>
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
                                    <button type="button" class="btn btn-save-order" onclick="saveOrderOnly(this)">Guardar y Descargar PDF</button>
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
            <p>&copy; 2026 Ferretería FOX. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/main.js?v=2.6"></script>
<script src="js/modals.js"></script>
    <script>
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
        window.TRUPER_COMPANY_WHATSAPP = '<?php echo htmlspecialchars(whatsapp_phone_digits(), ENT_QUOTES, 'UTF-8'); ?>';
        window.TRUPER_ORDERS_ROLE = '<?php echo htmlspecialchars($_SESSION['role'] ?? 'client', ENT_QUOTES, 'UTF-8'); ?>';
    </script>
    <script src="js/orders.js?v=20260606_v2" charset="UTF-8"></script>
    <script src="js/barcode-scanner.js"></script>
    <script>
        function logout() {
            confirmLogout('api/auth.php?action=logout');
        }
    </script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
