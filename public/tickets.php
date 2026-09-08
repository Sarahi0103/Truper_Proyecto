<?php
require_once '../config/config.php';
require_admin();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'admin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Historial de Tickets - Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.2">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
        .tickets-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .grid-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            align-items: flex-end;
        }

        .stat-grid-tickets {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .tickets-stacked {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .client-tickets-table-wrap {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 540px;
            -webkit-overflow-scrolling: touch;
        }

        .client-tickets-table-wrap::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .client-tickets-table-wrap::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }

        .client-tickets-table-wrap::-webkit-scrollbar-thumb {
            background: rgba(255, 127, 0, 0.5);
            border-radius: 4px;
        }

        .client-tickets-table-wrap::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 127, 0, 0.75);
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: rgba(255, 127, 0, 0.5);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 127, 0, 0.7);
        }

        .ticket-search-bar {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 0.45rem 0.85rem;
            margin-bottom: 1rem;
            transition: border-color 0.2s;
        }

        .ticket-search-bar:focus-within {
            border-color: var(--color-naranja);
            box-shadow: 0 0 0 3px rgba(255,127,0,0.12);
        }

        .ticket-search-bar input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: #fff;
            font-size: 0.95rem;
        }

        .ticket-search-bar input::placeholder {
            color: rgba(255,255,255,0.35);
        }

        .ticket-search-bar span {
            color: rgba(255,255,255,0.35);
            font-size: 1.1rem;
        }

        /* ── Pestañas Fijas de Canal (Online vs Tienda Local) ── */
        .channel-tabs-wrapper {
            margin-top: 1.5rem;
            margin-bottom: 1.25rem;
            position: sticky;
            top: 75px;
            z-index: 15;
        }

        .channel-tabs-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(22, 22, 26, 0.95);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 0.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
        }

        .channel-tab-btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            background: transparent;
            border: 1px solid transparent;
            color: #9ca3af;
            padding: 0.8rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
        }

        .channel-tab-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
        }

        .channel-tab-btn.active#tabChannelAll {
            background: rgba(255, 127, 0, 0.16);
            border-color: rgba(255, 127, 0, 0.6);
            color: #ff9f43;
            box-shadow: 0 4px 20px rgba(255, 127, 0, 0.15);
        }

        .channel-tab-btn.active#tabChannelOnline {
            background: rgba(59, 130, 246, 0.16);
            border-color: rgba(59, 130, 246, 0.6);
            color: #60a5fa;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.2);
        }

        .channel-tab-btn.active#tabChannelLocal {
            background: rgba(16, 185, 129, 0.16);
            border-color: rgba(16, 185, 129, 0.6);
            color: #34d399;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.2);
        }

        .channel-tab-counter {
            font-size: 0.78rem;
            font-weight: 800;
            padding: 2px 9px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            color: inherit;
            transition: all 0.2s ease;
        }

        .channel-tab-btn.active .channel-tab-counter {
            background: currentColor;
            color: #000;
        }

        .badge-channel-online {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.35);
        }

        .badge-channel-local {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.35);
        }

        @media (max-width: 768px) {
            .channel-tabs-container {
                flex-direction: column;
                gap: 0.4rem;
            }
            .channel-tab-btn {
                width: 100%;
                padding: 0.65rem 1rem;
            }
            .channel-tabs-wrapper {
                position: static;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px; width: auto; object-fit: contain;"></a>
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
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="account.php#historyTab">Historial</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
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
                    <button class="nav-dropdown-btn active">Admin Local <span class="arrow">▼</span></button>
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
                </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="user-role"><?php echo ($_SESSION['role'] ?? 'admin') === 'employee' ? 'PERSONAL' : 'ADMIN'; ?></div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="tickets-container">
            <!-- ── Back Button ── -->
            <div class="back-header">
                <button onclick="history.back()" class="btn-back">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Regresar
                </button>
            </div>

            <div class="page-hero">
                <div class="module-badge module-admin"><span class="module-glyph">TK</span> Historial centralizado</div>
                <h1>Panel de Historial de Tickets</h1>
                <p class="text-muted">Consulta, filtra y descarga el histórico de tickets de clientes y órdenes de proveedores.</p>
            </div>

            <!-- Filtros -->
            <div class="card mt-3">
                <div class="card-body">
                    <h3>Filtros y Búsqueda</h3>
                    <div class="grid-filters mt-2">
                        <div class="form-group">
                            <label for="ticketYearFilter">Año</label>
                            <select id="ticketYearFilter" onchange="loadTicketHistory()"></select>
                        </div>
                        <div class="form-group">
                            <label for="ticketMonthFilter">Mes</label>
                            <select id="ticketMonthFilter" onchange="loadTicketHistory()">
                                <option value="1">Enero</option>
                                <option value="2">Febrero</option>
                                <option value="3">Marzo</option>
                                <option value="4">Abril</option>
                                <option value="5">Mayo</option>
                                <option value="6">Junio</option>
                                <option value="7">Julio</option>
                                <option value="8">Agosto</option>
                                <option value="9">Septiembre</option>
                                <option value="10">Octubre</option>
                                <option value="11">Noviembre</option>
                                <option value="12">Diciembre</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="exportTypeFilter">Exportar como</label>
                            <select id="exportTypeFilter">
                                <option value="client">Tickets de Clientes</option>
                                <option value="supplier">Órdenes a Proveedores</option>
                                <option value="both">Ambos (Libro Completo)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fulfillmentFilter">Modalidad de Entrega</label>
                            <select id="fulfillmentFilter" onchange="loadTicketHistory()">
                                <option value="">Todas las Modalidades</option>
                                <option value="delivery">🚚 Envío a Domicilio</option>
                                <option value="pickup">🏬 Retiro en Tienda</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button class="btn btn-primary" onclick="downloadTicketsExcel()" data-download-tickets-excel style="flex: 1;">📥 Exportar Excel</button>
                            <button class="btn btn-secondary" onclick="archiveCurrentMonth()" style="flex: 1;">🔒 Archivar Mes</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertas / Resumen -->
            <div class="stat-grid-tickets" id="ticketsSummary">
                <div class="card"><div class="card-body text-center"><p class="text-muted">Cargando estadísticas...</p></div></div>
            </div>

            <!-- Reportes PDF Archivados -->
            <div class="card mt-3" style="background: rgba(255, 255, 255, 0.01); border: 1px solid rgba(255, 255, 255, 0.06); margin-bottom: 1.5rem;">
                <div class="card-body">
                    <h3 style="display: flex; align-items: center; gap: 0.5rem;">📄 Reportes Mensuales Archivados</h3>
                    <p class="text-muted mb-2">Historial de PDFs consolidados acumulados al realizar cierres mensuales de caja.</p>
                    <div id="archivedPdfsContainer" style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem;">
                        <p class="text-muted" style="font-style: italic;">Cargando archivos...</p>
                    </div>
                </div>
            </div>

            <!-- Pestañas Fijas de Selección de Canal (Online vs Tienda Local) -->
            <div class="channel-tabs-wrapper">
                <div class="channel-tabs-container">
                    <button type="button" class="channel-tab-btn active" id="tabChannelAll" onclick="setChannelFilter('all')" title="Ver todo el historial mensual">
                        <span>📋 Todos</span>
                        <span class="channel-tab-counter" id="counterAll">0</span>
                    </button>
                    <button type="button" class="channel-tab-btn" id="tabChannelOnline" onclick="setChannelFilter('online')" title="Filtrar pedidos de la Tienda en Línea con envío o paquetería">
                        <span>🌐 En Línea (Envíos & Guías)</span>
                        <span class="channel-tab-counter" id="counterOnline">0</span>
                    </button>
                    <button type="button" class="channel-tab-btn" id="tabChannelLocal" onclick="setChannelFilter('local')" title="Filtrar tickets de mostrador y recolección física">
                        <span>🏬 Tienda Local (Mostrador & POS)</span>
                        <span class="channel-tab-counter" id="counterLocal">0</span>
                    </button>
                </div>
            </div>

            <!-- Tablas de Historial -->
            <div class="tickets-stacked">
                <!-- CLIENTES (full-width, scrollable) -->
                <div class="card">
                    <div class="card-body">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <div>
                                <h3 style="margin:0;">Tickets de Clientes</h3>
                                <p class="text-muted" style="margin: 0.2rem 0 0;">Historial de ventas y movimientos de caja en este período.</p>
                            </div>
                            <span id="clientTicketsCount" style="font-size: 0.82rem; color: var(--color-naranja); font-weight: 600;"></span>
                        </div>
                        <!-- Buscador -->
                        <div class="ticket-search-bar">
                            <span>🔍</span>
                            <input type="text" id="clientTicketSearch" placeholder="Buscar por folio, cliente o email..." oninput="filterClientTickets(this.value)">
                            <button onclick="document.getElementById('clientTicketSearch').value=''; filterClientTickets('');" style="background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer;font-size:1rem;padding:0;" title="Limpiar">✕</button>
                        </div>
                        <div class="client-tickets-table-wrap">
                            <table class="table" style="width:100%; min-width: 820px; border-collapse: collapse;">
                                <thead style="position: sticky; top: 0; background: var(--theme-card-bg, #1a1a1a); z-index: 2;">
                                    <tr>
                                        <th>Folio</th>
                                        <th>Cliente</th>
                                        <th>Tipo</th>
                                        <th>Total</th>
                                        <th>Pago</th>
                                        <th>Entrega</th>
                                        <th>Fecha</th>
                                        <th style="text-align: center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="ticketsTableBody">
                                    <tr><td colspan="8" style="text-align: center; padding: 2rem;">Cargando...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- PROVEEDORES (below, full-width) -->
                <div id="supplierTicketsSection">
                    <div class="card">
                        <div class="card-body">
                            <h3>Órdenes de Proveedores</h3>
                            <p class="text-muted mb-2">Historial de ingresos de mercancía y cotizaciones.</p>
                            <div class="table-responsive">
                                <table class="table" style="width:100%; border-collapse: collapse;">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Proveedor</th>
                                            <th>Fecha</th>
                                            <th>Total</th>
                                            <th style="text-align: center;">Artículos</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="supplierTicketsBody">
                                        <tr><td colspan="6" style="text-align: center; padding: 2rem;">Cargando...</td></tr>
                                    </tbody>
                                </table>
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
                <h4>Ferretería FOX</h4>
                <p>Módulo de Historial y Auditoría de Ventas</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Ferretería FOX. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/jspdf.umd.min.js"></script>
    <script src="<?php echo asset_url('js/main.js'); ?>"></script>
    <script src="<?php echo asset_url('js/modals.js'); ?>"></script>
    <script>
        if (typeof safeNewDate !== 'function') {
            window.safeNewDate = function(dateString) {
                if (!dateString) return new Date();
                if (dateString instanceof Date) return dateString;
                let str = String(dateString).trim();
                if (!str.includes('T')) {
                    str = str.replace(/-/g, '/');
                }
                return new Date(str);
            };
        }
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';

        function logout() {
            confirmLogout('api/auth.php?action=logout');
        }

        function escapeHtml(v) {
            return String(v || '').replace(/[&<>"']/g, function(m) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
            });
        }

        function formatAdminMoney(value) {
            const amount = Number(value || 0);
            return `$${amount.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }

        async function loadTicketHistory() {
            const yearSelect = document.getElementById('ticketYearFilter');
            const monthSelect = document.getElementById('ticketMonthFilter');
            const year = yearSelect ? yearSelect.value : new Date().getFullYear();
            const month = monthSelect ? monthSelect.value : (new Date().getMonth() + 1);

            const response = await apiCall(`/analytics.php?action=ticket-history&year=${year}&month=${month}`);
            
            if (response && response.success) {
                displayTicketHistory(response.data);
            } else {
                showAlert('No se pudo cargar el historial de tickets', 'error');
            }
        }

        window.currentChannelFilter = 'all';

        function isOnlineTicket(t) {
            if (!t) return false;
            const folioStr = String(t.folio || '');

            // 1. Folios generados por checkout de Tienda en Línea (TCK- o FOX-)
            if (folioStr.startsWith('TCK-') || folioStr.startsWith('FOX-')) {
                return true;
            }

            // 2. Si tiene paquetería externa asignada con número de guía
            if ((t.carrier && t.carrier.trim() !== '') || (t.tracking_number && t.tracking_number.trim() !== '') || (t.tracking_folio && t.tracking_folio.trim() !== '')) {
                return true;
            }

            // 3. Si tiene dirección real de envío con calle especificada (no vacía)
            if (t.shipping_address_json && t.shipping_address_json !== '{}' && t.shipping_address_json !== '[]' && t.shipping_address_json !== '""') {
                try {
                    const parsed = typeof t.shipping_address_json === 'string' ? JSON.parse(t.shipping_address_json) : t.shipping_address_json;
                    if (parsed && parsed.address && parsed.address.trim() !== '') {
                        const cust = String(t.customer_name || '');
                        if (!cust.includes('Cotización') || t.order_id) {
                            return true;
                        }
                    }
                } catch(e) {}
            }

            // Todo lo demás (mostrador, cotizaciones presenciales, caja local) pertenece a Tienda Local
            return false;
        }

        function setChannelFilter(channel) {
            window.currentChannelFilter = channel;

            // Actualizar botones visuales
            document.querySelectorAll('.channel-tab-btn').forEach(btn => btn.classList.remove('active'));
            if (channel === 'online') {
                document.getElementById('tabChannelOnline')?.classList.add('active');
            } else if (channel === 'local') {
                document.getElementById('tabChannelLocal')?.classList.add('active');
            } else {
                document.getElementById('tabChannelAll')?.classList.add('active');
            }

            // Ocultar sección de órdenes a proveedores si estamos en pestaña online para mantener foco 100% en envíos
            const supplierSection = document.getElementById('supplierTicketsSection');
            if (supplierSection) {
                supplierSection.style.display = (channel === 'online') ? 'none' : 'block';
            }

            applyAllFilters();
        }

        function displayTicketHistory(data) {
            const tableBody = document.getElementById('ticketsTableBody');
            const summaryContainer = document.getElementById('ticketsSummary');
            const supplierSection = document.getElementById('supplierTicketsSection');
            
            if (!tableBody) return;

            const tickets = data.tickets || [];
            const supplierTickets = data.supplier_tickets || [];
            const stats = data.stats || {};

            currentPaymentFilter = 'all';

            // Actualizar contadores de las pestañas de canal
            const visibleForCounts = tickets.filter(t => {
                if (t.ticket_type === 'sale') {
                    const ps = t.pickup_status || 'pending';
                    return ps !== 'cancelled' && ps !== 'expired';
                }
                return true;
            });
            const onlineCount = visibleForCounts.filter(isOnlineTicket).length;
            const localCount = visibleForCounts.filter(t => !isOnlineTicket(t)).length;
            const totalCount = visibleForCounts.length;

            const cAll = document.getElementById('counterAll');
            const cOnline = document.getElementById('counterOnline');
            const cLocal = document.getElementById('counterLocal');
            if (cAll) cAll.textContent = totalCount;
            if (cOnline) cOnline.textContent = onlineCount;
            if (cLocal) cLocal.textContent = localCount;

            // Render stats summary cards
            if (summaryContainer) {
                let statHTML = `
                    <div class="card" style="border: 1px solid rgba(255,255,255,0.05);">
                        <div class="card-body">
                            <span class="text-muted text-uppercase" style="font-size:0.75rem; font-weight:700; display:block; letter-spacing: 0.04em;">Tickets Este Mes</span>
                            <strong style="display:block; font-size:1.5rem; margin-top:0.25rem; color:var(--color-naranja);">${stats.total_tickets || 0}</strong>
                        </div>
                    </div>
                    <div class="card" style="border: 1px solid rgba(255,255,255,0.05);">
                        <div class="card-body">
                            <span class="text-muted text-uppercase" style="font-size:0.75rem; font-weight:700; display:block; letter-spacing: 0.04em;">Total Ventas</span>
                            <strong style="display:block; font-size:1.5rem; margin-top:0.25rem; color:var(--color-naranja);">${formatAdminMoney(stats.total_sales || 0)}</strong>
                            <div style="font-size: 0.72rem; color: var(--theme-text-muted); margin-top: 0.2rem;">Promedio: ${formatAdminMoney(stats.avg_ticket || 0)}</div>
                        </div>
                    </div>
                `;

                // Add supplier summary cards if present
                if (typeof stats.supplier_count !== 'undefined') {
                    statHTML += `
                        <div class="card" style="grid-column: 1 / -1;">
                            <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                                <div>
                                    <span class="text-muted text-uppercase" style="font-size:0.75rem; font-weight:700; display:block; letter-spacing: 0.04em;">Órdenes de Compra a Proveedor</span>
                                    <strong style="display:block; font-size:1.5rem; margin-top:0.25rem; color:var(--color-naranja);">${stats.supplier_count || 0}</strong>
                                </div>
                                <div style="text-align: right;">
                                    <span class="text-muted text-uppercase" style="font-size:0.75rem; font-weight:700; display:block; letter-spacing: 0.04em;">Monto total estimado</span>
                                    <strong style="display:block; font-size:1.3rem; margin-top:0.25rem; color:#fff;">${formatAdminMoney(stats.supplier_total || 0)}</strong>
                                </div>
                            </div>
                        </div>
                    `;
                }

                summaryContainer.innerHTML = statHTML;
            }

            // Client tickets rows
            // Store tickets globally for search filter
            window._allClientTickets = tickets;

            renderSupplierTicketsRows(supplierTickets);
            applyAllFilters();
        }

        function renderClientTicketsRows(tickets) {
            const tableBody = document.getElementById('ticketsTableBody');
            if (!tableBody) return;

            // Excluir tickets cancelados/eliminados
            const visible = tickets.filter(t => {
                if (t.ticket_type === 'sale') {
                    const ps = t.pickup_status || 'pending';
                    return ps !== 'cancelled' && ps !== 'expired';
                }
                return true;
            });

            // Actualizar contador con los visibles filtrados
            const countEl = document.getElementById('clientTicketsCount');
            if (countEl) countEl.textContent = visible.length > 0 ? `${visible.length} ticket${visible.length !== 1 ? 's' : ''}` : '';

            if (visible.length === 0) {
                const emptyMsg = window.currentChannelFilter === 'online'
                    ? 'No hay pedidos de la Tienda en Línea en este período'
                    : (window.currentChannelFilter === 'local' ? 'No hay tickets de Tienda Local en este período' : 'No hay tickets en este período');
                tableBody.innerHTML = `<tr><td colspan="8" style="padding: 2.5rem; text-align: center; color: var(--theme-text-muted); font-size: 0.95rem;">${emptyMsg}</td></tr>`;
                return;
            }

            let html = '';
            visible.forEach(ticket => {
                const isOnline = isOnlineTicket(ticket);
                const pStatus = ticket.ticket_type === 'sale' ? (ticket.pickup_status || 'pending') : null;

                // Pago: pendiente si no está validado, pagado si ya fue entregado
                const isPaymentDone = pStatus === 'picked_up' || (ticket.ticket_type !== 'sale' && ticket.payment_status === 'completed') || (isOnline && ticket.payment_status === 'completed');
                const paymentColor = isPaymentDone ? 'var(--color-exito)' : 'var(--color-advertencia)';
                const paymentText = isPaymentDone ? '✓ Pagado' : '⏳ Pendiente';

                const typeLabel = {
                    'sale': '💰 Venta',
                    'return': '🔄 Devolución',
                    'adjustment': '⚙️ Ajuste',
                    'credit': '💳 Crédito'
                }[ticket.ticket_type] || ticket.ticket_type;

                // Insignia de canal
                const channelBadge = isOnline
                    ? `<span class="badge-channel-online">🌐 En Línea</span>`
                    : `<span class="badge-channel-local">🏬 Tienda Local</span>`;

                // Modalidad display y datos de paquetería / destino
                let deliveryDetailHtml = '';
                if (isOnline) {
                    let carrierTag = '';
                    if (ticket.carrier || ticket.tracking_number) {
                        carrierTag = `<div style="margin-top:3px; font-size:0.75rem; color:#60a5fa; display:flex; align-items:center; gap:4px;">🚚 <strong>${escapeHtml(ticket.carrier || 'Paquetería')}:</strong> <span style="font-family:monospace; background:rgba(0,0,0,0.3); padding:1px 5px; border-radius:4px;">${escapeHtml(ticket.tracking_number || 'S/N')}</span></div>`;
                    }
                    let cityTag = '';
                    try {
                        const sObj = typeof ticket.shipping_address_json === 'string' ? JSON.parse(ticket.shipping_address_json) : ticket.shipping_address_json;
                        if (sObj && (sObj.city || sObj.address)) {
                            cityTag = `<div style="font-size:0.73rem; color:#9ca3af; margin-top:2px;">📍 ${escapeHtml((sObj.city || '') + (sObj.postalCode ? ' (' + sObj.postalCode + ')' : ''))}</div>`;
                        }
                    } catch(e) {}

                    deliveryDetailHtml = `
                        <div>
                            <span style="display:inline-block; padding: 2px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 700; background: rgba(255,127,0,0.15); color: #ff7f00; border: 1px solid rgba(255,127,0,0.3);">🚚 Envío a Domicilio</span>
                            ${carrierTag}
                            ${cityTag}
                        </div>
                    `;
                } else {
                    deliveryDetailHtml = `
                        <div>
                            <span style="display:inline-block; padding: 2px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 700; background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3);">🏬 Recolección Mostrador</span>
                        </div>
                    `;
                }

                // Estado de Entrega / Envío
                let statusBadge = '';
                if (isOnline) {
                    const st = ticket.order_status || 'in_preparation';
                    const stMap = {
                        'in_preparation': { text: '⏳ En Preparación', color: 'rgba(245, 158, 11, 0.2)', border: '#f59e0b', textColor: '#fbbf24' },
                        'shipped': { text: '🚚 En Camino', color: 'rgba(59, 130, 246, 0.2)', border: '#3b82f6', textColor: '#60a5fa' },
                        'delivered': { text: '✓ Entregado', color: 'rgba(16, 185, 129, 0.2)', border: '#10b981', textColor: '#34d399' }
                    };
                    const sConf = stMap[st] || stMap['in_preparation'];
                    statusBadge = `<span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; background: ${sConf.color}; border: 1px solid ${sConf.border}; color: ${sConf.textColor};">${sConf.text}</span>`;
                } else {
                    if (ticket.ticket_type === 'sale') {
                        const pickupColor = {
                            'pending': 'var(--color-advertencia)',
                            'picked_up': 'var(--color-exito)',
                            'cancelled': 'var(--color-peligro, #dc3545)',
                            'expired': '#6c757d'
                        }[pStatus];
                        const pickupText = {
                            'pending': '⏳ Por Recoger',
                            'picked_up': '✓ Entregado',
                            'cancelled': '✗ Cancelado',
                            'expired': '⌛ Expirado'
                        }[pStatus];
                        statusBadge = `<span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; background: ${pickupColor}; color: white;">${pickupText}</span>`;
                    } else {
                        statusBadge = '<span style="color:var(--theme-text-muted);">—</span>';
                    }
                }

                // Botones para Ticket oficial, etiqueta ciega, rastreo y validación
                let actionButton = `<div style="display:flex; gap:4px; justify-content:center; flex-wrap:wrap;">`;
                actionButton += `<a href="ticket_client.php?folio=${encodeURIComponent(ticket.folio)}" target="_blank" class="btn" style="padding: 0.35rem 0.55rem; font-size: 0.75rem; text-decoration: none; border-radius: 6px; background: rgba(16,185,129,0.15); border: 1px solid #10b981; color: #34d399; font-weight: 700;" title="Ver e Imprimir Ticket Oficial">🖨️ Ticket</a>`;

                if (isOnline) {
                    actionButton += `<a href="/api/print_blind_label.php?folio=${escapeHtml(ticket.folio)}" target="_blank" class="btn" style="padding: 0.35rem 0.55rem; font-size: 0.75rem; text-decoration: none; border-radius: 6px; background: #1c1917; border: 1px solid #444; color: #ff7f00; font-weight: 600;" title="Etiqueta Ciega 4x6''">🏷️ 4x6"</a>`;
                    actionButton += `<a href="order_tracking.php?order=${encodeURIComponent(ticket.folio)}" target="_blank" class="btn" style="padding: 0.35rem 0.55rem; font-size: 0.75rem; text-decoration: none; border-radius: 6px; background: rgba(59,130,246,0.18); border: 1px solid #3b82f6; color: #93c5fd; font-weight: 600;" title="Rastrear paquete">🚚 Rastreo</a>`;
                    actionButton += `<a href="admin_online_orders.php" class="btn" style="padding: 0.35rem 0.55rem; font-size: 0.75rem; text-decoration: none; border-radius: 6px; background: rgba(255,127,0,0.15); border: 1px solid #ff7f00; color: #ff7f00; font-weight: 600;" title="Asignar o editar guía en panel online">📦 Guía</a>`;
                } else {
                    if (ticket.ticket_type === 'sale') {
                        if (pStatus === 'picked_up') {
                            actionButton += `<button onclick="deleteTicketFromHistory('${escapeHtml(ticket.folio)}')" class="btn" style="padding: 0.35rem 0.55rem; font-size: 0.75rem; border-radius: 6px; background: #dc3545; border: none; color: white; font-weight: 600; cursor: pointer;" title="Eliminar de historial">🗑</button>`;
                        } else if (pStatus === 'pending') {
                            actionButton += `<a href="ticket_validation.php?folio=${escapeHtml(ticket.folio)}" class="btn" style="padding: 0.35rem 0.55rem; font-size: 0.75rem; text-decoration: none; border-radius: 6px; background: var(--color-naranja); border: none; color: white; font-weight: 600;" title="Validar entrega física">✓ Validar</a>`;
                        }
                    }
                }
                actionButton += `</div>`;

                html += `
                    <tr style="border-bottom: 1px solid var(--theme-border); transition: opacity 0.3s, transform 0.3s;" data-folio="${escapeHtml(ticket.folio)}" data-channel="${isOnline ? 'online' : 'local'}" data-fulfillment="${isOnline ? 'delivery' : 'pickup'}" data-search="${escapeHtml((ticket.folio + ' ' + (ticket.customer_name || '') + ' ' + (ticket.email || '') + ' ' + (ticket.carrier || '') + ' ' + (ticket.tracking_number || '')).toLowerCase())}">
                        <td style="padding: 1rem; font-family: monospace; font-weight: 700; color: var(--color-naranja);">
                            ${escapeHtml(ticket.folio)}
                            <div style="margin-top: 4px;">${channelBadge}</div>
                        </td>
                        <td style="padding: 1rem;">
                            <div style="font-weight: 600;">${escapeHtml(ticket.customer_name || 'Mostrador')}</div>
                            <div style="font-size: 0.8rem; color: var(--theme-text-muted);">${escapeHtml(ticket.email || '')}</div>
                            <div style="margin-top: 4px;">${deliveryDetailHtml}</div>
                        </td>
                        <td style="padding: 1rem;">${typeLabel}</td>
                        <td style="padding: 1rem; font-weight: 700; color: var(--color-naranja);">${formatAdminMoney(ticket.total_amount || 0)}</td>
                        <td style="padding: 1rem;">
                            <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; background: ${paymentColor}; color: white;">
                                ${paymentText}
                            </span>
                        </td>
                        <td style="padding: 1rem;">${statusBadge}</td>
                        <td style="padding: 1rem; font-size: 0.9rem; color: var(--theme-text-muted);">
                            ${safeNewDate(ticket.issued_date).toLocaleDateString('es-MX')}
                        </td>
                        <td style="padding: 1rem; text-align: center;">${actionButton}</td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        }

        function renderSupplierTicketsRows(supplierTickets) {
            const supplierSection = document.getElementById('supplierTicketsSection');
            if (!supplierSection) return;

            if (!Array.isArray(supplierTickets) || supplierTickets.length === 0) {
                supplierSection.innerHTML = `
                    <div class="card">
                        <div class="card-body">
                            <h3>Órdenes de Proveedores</h3>
                            <p class="text-muted" style="padding: 2rem; text-align: center;">No hay órdenes o tickets de proveedor en este período.</p>
                        </div>
                    </div>
                `;
                return;
            }

            let shtml = `
                <div class="card">
                    <div class="card-body">
                        <h3>Órdenes de Proveedores</h3>
                        <p class="text-muted mb-2">Historial de ingresos de mercancía y cotizaciones.</p>
                        <div class="table-responsive">
                            <table class="table" style="width:100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th>Proveedor</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                        <th style="text-align: center;">Artículos</th>
                                        <th>Estado</th>
                                        <th style="text-align: center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
            `;

            supplierTickets.forEach(st => {
                const status = st.payment_status || st.status || 'pending';
                let statusColor = 'var(--color-advertencia)';
                let statusText = '⏳ Pendiente';
                if (status === 'completed' || status === 'received') {
                    statusColor = 'var(--color-exito)';
                    statusText = '✓ Recibido';
                } else if (status === 'cancelled') {
                    statusColor = 'var(--color-peligro, #dc3545)';
                    statusText = '✗ Cancelado';
                }

                shtml += `
                    <tr style="border-bottom: 1px solid var(--theme-border);">
                        <td style="padding: 1rem; font-family: monospace; font-weight:700; color:var(--color-naranja);">${escapeHtml(st.folio)}</td>
                        <td style="padding: 1rem; font-weight: 600;">${escapeHtml(st.customer_name || '—')}</td>
                        <td style="padding: 1rem; color:var(--theme-text-muted);">${safeNewDate(st.issued_date).toLocaleDateString('es-MX')}</td>
                        <td style="padding: 1rem; font-weight:700; color:var(--color-naranja);">${formatAdminMoney(st.total_amount || 0)}</td>
                        <td style="padding: 1rem; text-align:center; font-weight:600;">${st.item_count || 0}</td>
                        <td style="padding: 1rem;">
                            <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; background: ${statusColor}; color: white;">
                                ${statusText}
                            </span>
                        </td>
                        <td style="padding: 1rem; text-align: center;">
                            <a href="/ticket_supplier.php?id=${st.id}" target="_blank" class="btn" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; text-decoration: none; border-radius: 6px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); color: white; display: inline-block; font-weight: 600; cursor: pointer;">🖨️ Imprimir</a>
                        </td>
                    </tr>
                `;
            });

            shtml += `</tbody></table></div></div></div>`;
            supplierSection.innerHTML = shtml;
        }

        let currentPaymentFilter = 'all';

        function filterByPaymentStatus(status) {
            currentPaymentFilter = status;
            applyAllFilters();
            
            // Highlight active card visual feedback
            const cards = document.querySelectorAll('#ticketsSummary .card');
            cards.forEach(card => {
                card.style.boxShadow = '';
                card.style.borderColor = 'rgba(255,255,255,0.05)';
            });
            
            // Highlight clicked card
            if (status === 'pending') {
                const pendingCard = document.querySelector('[data-card-type="pending"]');
                if (pendingCard) {
                    pendingCard.style.borderColor = 'var(--color-naranja)';
                    pendingCard.style.boxShadow = '0 0 10px rgba(255,102,0,0.1)';
                }
            } else {
                const allCard = document.querySelector('[data-card-type="all"]');
                if (allCard) {
                    allCard.style.borderColor = 'var(--color-naranja)';
                    allCard.style.boxShadow = '0 0 10px rgba(255,102,0,0.1)';
                }
            }
        }

        function applyAllFilters() {
            const all = window._allClientTickets || [];
            const query = document.getElementById('clientTicketSearch')?.value.toLowerCase().trim() || '';
            
            let filtered = all;

            // 1. Filtro de Canal (Online vs Local vs Todos)
            if (window.currentChannelFilter === 'online') {
                filtered = filtered.filter(t => isOnlineTicket(t));
            } else if (window.currentChannelFilter === 'local') {
                filtered = filtered.filter(t => !isOnlineTicket(t));
            }
            
            // 2. Búsqueda por texto (folio, cliente, email, paquetería, guía)
            if (query !== '') {
                filtered = filtered.filter(t => {
                    const haystack = [
                        t.folio || '',
                        t.customer_name || '',
                        t.email || '',
                        t.carrier || '',
                        t.tracking_number || ''
                    ].join(' ').toLowerCase();
                    return haystack.includes(query);
                });
            }
            
            // 3. Filtro de estado de pago
            if (currentPaymentFilter === 'pending') {
                filtered = filtered.filter(t => {
                    const isOnline = isOnlineTicket(t);
                    const pStatus = t.ticket_type === 'sale' ? (t.pickup_status || 'pending') : null;
                    const isPaymentDone = pStatus === 'picked_up' || (t.ticket_type !== 'sale' && t.payment_status === 'completed') || (isOnline && t.payment_status === 'completed');
                    return !isPaymentDone;
                });
            }

            // 4. Modalidad de Entrega (dropdown)
            const fulFilter = document.getElementById('fulfillmentFilter')?.value || '';
            if (fulFilter !== '') {
                filtered = filtered.filter(t => {
                    const isDelivery = isOnlineTicket(t);
                    return fulFilter === 'delivery' ? isDelivery : !isDelivery;
                });
            }
            
            renderClientTicketsRows(filtered);
        }

        function filterClientTickets(query) {
            applyAllFilters();
        }

        function generateMonthlyReportPdf(year, month, data) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'letter' });
            
            const monthNames = [
                'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
            ];
            const monthStr = monthNames[month - 1] || 'Mes';
            
            const primaryColor = [255, 102, 0]; 
            const darkColor = [30, 30, 30]; 
            const lightBg = [245, 245, 245];
            
            // Header
            doc.setFillColor(...primaryColor);
            doc.rect(0, 0, 215.9, 35, 'F');
            
            doc.setTextColor(255, 255, 255);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(22);
            doc.text('FERRETERÍA FOX', 15, 18);
            
            doc.setFontSize(11);
            doc.setFont('helvetica', 'normal');
            doc.text(`REPORTE MENSUAL DE CIERRE Y AUDITORÍA - ${monthStr.toUpperCase()} ${year}`, 15, 26);
            
            doc.setFontSize(10);
            doc.text(`Generado: ${new Date().toLocaleDateString('es-MX')}`, 150, 18);
            doc.text(`Firma digital: MD5-${Math.random().toString(36).substring(2, 10).toUpperCase()}`, 150, 24);
            
            let y = 50;
            
            // Sección Estadísticas
            doc.setTextColor(...darkColor);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.text('RESUMEN DE ESTADÍSTICAS DEL MES', 15, y);
            
            doc.setDrawColor(220, 220, 220);
            doc.line(15, y + 2, 200, y + 2);
            y += 10;
            
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            
            const stats = data.stats || {};
            doc.text(`Total Tickets: ${stats.total_tickets || 0}`, 15, y);
            doc.text(`Total Ventas: $${Number(stats.total_sales || 0).toLocaleString('es-MX', {minimumFractionDigits:2})}`, 15, y + 6);
            doc.text(`Tickets Devolución: ${stats.return_count || 0}`, 110, y);
            doc.text(`Pagos Pendientes: ${stats.payment_pending || 0}`, 110, y + 6);
            y += 18;
            
            // Listado de Tickets Clientes
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.text('HISTORIAL DE TICKETS DE CLIENTES', 15, y);
            doc.line(15, y + 2, 200, y + 2);
            y += 10;
            
            // Header Tabla Clientes
            doc.setFillColor(...lightBg);
            doc.rect(15, y, 185, 8, 'F');
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(9);
            doc.setTextColor(50, 50, 50);
            doc.text('Folio', 18, y + 5.5);
            doc.text('Cliente', 50, y + 5.5);
            doc.text('Tipo', 105, y + 5.5);
            doc.text('Estado', 130, y + 5.5);
            doc.text('Fecha', 155, y + 5.5);
            doc.text('Total', 180, y + 5.5);
            
            y += 8;
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(...darkColor);
            
            const tickets = data.tickets || [];
            tickets.forEach((t, idx) => {
                if (y > 260) {
                    doc.addPage();
                    y = 20;
                    
                    // Redibuja header en página nueva
                    doc.setFillColor(...lightBg);
                    doc.rect(15, y, 185, 8, 'F');
                    doc.setFont('helvetica', 'bold');
                    doc.text('Folio', 18, y + 5.5);
                    doc.text('Cliente', 50, y + 5.5);
                    doc.text('Tipo', 105, y + 5.5);
                    doc.text('Estado', 130, y + 5.5);
                    doc.text('Fecha', 155, y + 5.5);
                    doc.text('Total', 180, y + 5.5);
                    y += 8;
                    doc.setFont('helvetica', 'normal');
                }
                
                if (idx % 2 === 1) {
                    doc.setFillColor(250, 250, 250);
                    doc.rect(15, y, 185, 7, 'F');
                }
                
                doc.text(t.folio || '-', 18, y + 5);
                
                let name = t.customer_name || 'Sin nombre';
                if (name.length > 25) name = name.substring(0, 23) + '...';
                doc.text(name, 50, y + 5);
                
                const typeLabel = {
                    'sale': 'Venta',
                    'return': 'Devolución',
                    'adjustment': 'Ajuste',
                    'credit': 'Crédito'
                }[t.ticket_type] || t.ticket_type;
                doc.text(typeLabel, 105, y + 5);
                
                const statusLabel = t.payment_status === 'completed' ? 'Pagado' : 'Pendiente';
                doc.text(statusLabel, 130, y + 5);
                doc.text(safeNewDate(t.issued_date).toLocaleDateString('es-MX'), 155, y + 5);
                doc.text(`$${Number(t.total_amount || 0).toLocaleString('es-MX', {minimumFractionDigits:2})}`, 180, y + 5);
                
                y += 7;
            });
            
            y += 10;
            
            // Órdenes de Proveedores (si existen)
            const supplierTickets = data.supplier_tickets || [];
            if (supplierTickets.length > 0) {
                if (y > 230) {
                    doc.addPage();
                    y = 20;
                }
                
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(13);
                doc.text('ÓRDENES DE PROVEEDORES', 15, y);
                doc.line(15, y + 2, 200, y + 2);
                y += 10;
                
                doc.setFillColor(...lightBg);
                doc.rect(15, y, 185, 8, 'F');
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(9);
                doc.setTextColor(50, 50, 50);
                doc.text('Folio', 18, y + 5.5);
                doc.text('Proveedor', 50, y + 5.5);
                doc.text('Estado', 130, y + 5.5);
                doc.text('Fecha', 155, y + 5.5);
                doc.text('Total', 180, y + 5.5);
                
                y += 8;
                doc.setFont('helvetica', 'normal');
                doc.setTextColor(...darkColor);
                
                supplierTickets.forEach((st, idx) => {
                    if (y > 260) {
                        doc.addPage();
                        y = 20;
                        doc.setFillColor(...lightBg);
                        doc.rect(15, y, 185, 8, 'F');
                        doc.setFont('helvetica', 'bold');
                        doc.text('Folio', 18, y + 5.5);
                        doc.text('Proveedor', 50, y + 5.5);
                        doc.text('Estado', 130, y + 5.5);
                        doc.text('Fecha', 155, y + 5.5);
                        doc.text('Total', 180, y + 5.5);
                        y += 8;
                        doc.setFont('helvetica', 'normal');
                    }
                    
                    if (idx % 2 === 1) {
                        doc.setFillColor(250, 250, 250);
                        doc.rect(15, y, 185, 7, 'F');
                    }
                    
                    doc.text(st.folio || '-', 18, y + 5);
                    
                    let name = st.customer_name || 'Sin nombre';
                    if (name.length > 25) name = name.substring(0, 23) + '...';
                    doc.text(name, 50, y + 5);
                    doc.text(st.payment_status || 'Recibido', 130, y + 5);
                    doc.text(safeNewDate(st.issued_date).toLocaleDateString('es-MX'), 155, y + 5);
                    doc.text(`$${Number(st.total_amount || 0).toLocaleString('es-MX', {minimumFractionDigits:2})}`, 180, y + 5);
                    
                    y += 7;
                });
            }
            
            return doc;
        }

        async function archiveCurrentMonth() {
            showPremiumModal(
                'Archivar Mes',
                '¿Estás seguro de que quieres archivar los tickets de este mes? Se generará y guardará un PDF de cierre mensual. Esta acción no se puede deshacer.',
                '🔒',
                async () => {
                    const yearSelect = document.getElementById('ticketYearFilter');
                    const monthSelect = document.getElementById('ticketMonthFilter');
                    const year = yearSelect ? yearSelect.value : new Date().getFullYear();
                    const month = monthSelect ? monthSelect.value : (new Date().getMonth() + 1);

                    try {
                        // 1. Obtener datos históricos de este mes para el PDF
                        showAlert('Obteniendo datos del mes...', 'info');
                        const historyRes = await apiCall(`/analytics.php?action=ticket-history&year=${year}&month=${month}`);
                        if (!historyRes || !historyRes.success) {
                            showAlert('No se pudo obtener el historial para generar el PDF', 'error');
                            return;
                        }

                        // 2. Crear el PDF
                        showAlert('Generando PDF del mes...', 'info');
                        const doc = generateMonthlyReportPdf(year, month, historyRes.data);
                        const pdfBase64 = doc.output('datauristring').split(',')[1];

                        // 3. Subir el PDF al servidor
                        showAlert('Guardando PDF en el servidor...', 'info');
                        const uploadRes = await apiCall('/analytics.php?action=save-monthly-pdf', 'POST', {
                            year: parseInt(year, 10),
                            month: parseInt(month, 10),
                            pdf_data: pdfBase64
                        });

                        if (!uploadRes || !uploadRes.success) {
                            showAlert('Error al subir el reporte PDF: ' + (uploadRes?.message || 'Error desconocido'), 'error');
                            return;
                        }

                        // 4. Archivar en base de datos
                        showAlert('Archivando registros en la base de datos...', 'info');
                        const data = await apiCall('/analytics.php?action=archive-tickets', 'POST', {
                            year: parseInt(year, 10),
                            month: parseInt(month, 10)
                        });

                        if (data && data.success) {
                            showAlert(`Se archivaron ${data.archived_count} tickets y se guardó el PDF de cierre.`, 'success');
                            loadTicketHistory();
                            loadArchivedPdfs();
                        } else {
                            showAlert((data && data.message) || 'Error al archivar', 'error');
                        }
                    } catch (error) {
                        console.error('Error archivando tickets:', error);
                        showAlert('Error en la solicitud', 'error');
                    }
                }
            );
        }

        async function loadArchivedPdfs() {
            const container = document.getElementById('archivedPdfsContainer');
            if (!container) return;

            try {
                const res = await apiCall('/analytics.php?action=list-monthly-pdfs');
                if (res && res.success) {
                    const files = res.files || [];
                    if (files.length === 0) {
                        container.innerHTML = '<p class="text-muted" style="font-style: italic;">No hay reportes PDF archivados en el servidor.</p>';
                        return;
                    }

                    container.innerHTML = files.map(f => {
                        const sizeKb = (f.size / 1024).toFixed(1);
                        // f.url already points to api/analytics.php?action=get-monthly-pdf&year=...&month=...
                        return `
                            <a href="${f.url}" target="_blank" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; padding: 0.6rem 1rem; border-radius: 8px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); color: #fff; transition: background 0.2s;">
                                <span style="font-size: 1.25rem;">📄</span>
                                <div style="text-align: left;">
                                    <div style="font-weight: 600; font-size: 0.85rem;">Reporte ${f.readable_name}</div>
                                    <div style="font-size: 0.7rem; color: #aaa;">PDF (${sizeKb} KB) • Descargar</div>
                                </div>
                            </a>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = '<p class="text-muted" style="color: var(--color-error) !important;">Error al cargar lista de PDFs.</p>';
                }
            } catch (error) {
                console.error('Error loading archived PDFs:', error);
                container.innerHTML = '<p class="text-muted">Error al cargar reportes acumulados.</p>';
            }
        }

        function downloadTicketsExcel() {
            const yearSelect = document.getElementById('ticketYearFilter');
            const monthSelect = document.getElementById('ticketMonthFilter');
            const year = yearSelect ? yearSelect.value : new Date().getFullYear();
            const month = monthSelect ? monthSelect.value : (new Date().getMonth() + 1);
            const exportType = document.getElementById('exportTypeFilter')?.value || 'client';
            const button = document.querySelector('[data-download-tickets-excel]');

            if (button) {
                button.disabled = true;
                const originalText = button.innerHTML;
                button.dataset.originalText = originalText;
                button.innerHTML = '⏳ Generando...';
                setTimeout(() => {
                    button.disabled = false;
                    if (button.dataset.originalText) {
                        button.innerHTML = button.dataset.originalText;
                        delete button.dataset.originalText;
                    }
                }, 1500);
            }

            const url = `api/analytics.php?action=ticket-export&year=${encodeURIComponent(year)}&month=${encodeURIComponent(month)}&type=${encodeURIComponent(exportType)}`;
            const link = document.createElement('a');
            link.href = url;
            link.rel = 'noopener';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function initTicketFilters() {
            const yearSelect = document.getElementById('ticketYearFilter');
            const monthSelect = document.getElementById('ticketMonthFilter');

            if (!yearSelect) return;

            const populateYearFallback = () => {
                const currentYear = new Date().getFullYear();
                const years = [currentYear, currentYear - 1, currentYear - 2];
                yearSelect.innerHTML = '';
                years.forEach(year => {
                    const option = document.createElement('option');
                    option.value = year;
                    option.textContent = year;
                    if (year === currentYear) option.selected = true;
                    yearSelect.appendChild(option);
                });

                if (monthSelect) {
                    monthSelect.value = new Date().getMonth() + 1;
                }

                loadTicketHistory();
                loadArchivedPdfs();
            };

            apiCall('/analytics.php?action=ticket-years')
                .then(response => {
                    const currentYear = new Date().getFullYear();
                    const years = Array.isArray(response?.years) && response.years.length > 0
                        ? response.years.map(year => parseInt(year, 10)).filter(year => !isNaN(year))
                        : [currentYear, currentYear - 1, currentYear - 2];

                    yearSelect.innerHTML = '';
                    years.forEach(year => {
                        const option = document.createElement('option');
                        option.value = year;
                        option.textContent = year;
                        if (year === currentYear) option.selected = true;
                        yearSelect.appendChild(option);
                    });

                    if (yearSelect.options.length > 0 && !yearSelect.value) {
                        yearSelect.selectedIndex = 0;
                    }

                    if (monthSelect) {
                        monthSelect.value = new Date().getMonth() + 1;
                    }

                    loadTicketHistory();
                    loadArchivedPdfs();
                })
                .catch(() => {
                    populateYearFallback();
                });
        }

        // Auto-run filters initialization
        document.addEventListener('DOMContentLoaded', initTicketFilters);

        async function deleteTicketFromHistory(folio) {
            confirmDelete(`el ticket ${folio}`, async () => {
                // Fade out inmediato para feedback visual
                const row = document.querySelector(`tr[data-folio="${folio}"]`);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(30px)';
                    row.style.pointerEvents = 'none';
                }

                try {
                    const response = await fetch('api/ticket_validation.php?action=delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': window.csrfToken || ''
                        },
                        body: JSON.stringify({
                            folio: folio,
                            reason: 'Eliminado desde historial de tickets',
                            csrf_token: window.csrfToken || ''
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        // Quitar del DOM definitivamente
                        setTimeout(() => { if (row) row.remove(); }, 310);

                        // Actualizar array global y contador
                        if (window._allClientTickets) {
                            window._allClientTickets = window._allClientTickets.filter(t => t.folio !== folio);
                            const visibleCount = window._allClientTickets.filter(t => {
                                const ps = t.pickup_status || 'pending';
                                return ps !== 'cancelled' && ps !== 'expired';
                            }).length;
                            const countEl = document.getElementById('clientTicketsCount');
                            if (countEl) countEl.textContent = visibleCount > 0 ? `${visibleCount} ticket${visibleCount !== 1 ? 's' : ''}` : '';
                        }

                        showAlert('Ticket eliminado correctamente', 'success');
                    } else {
                        // Revertir fade si hubo error
                        if (row) {
                            row.style.opacity = '1';
                            row.style.transform = '';
                            row.style.pointerEvents = '';
                        }
                        showAlert(data.message || 'Error al eliminar ticket', 'error');
                    }
                } catch (err) {
                    if (row) {
                        row.style.opacity = '1';
                        row.style.transform = '';
                        row.style.pointerEvents = '';
                    }
                    showAlert('Error al eliminar ticket', 'error');
                }
            });
        }
    </script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>

