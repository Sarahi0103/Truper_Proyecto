<?php
require_once '../config/config.php';
require_login();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Cliente', ENT_QUOTES, 'UTF-8');
$user_id = (int)$_SESSION['user_id'];
$company_whatsapp = htmlspecialchars(whatsapp_phone_digits(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mi Cuenta - Truper Platform</title>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <link rel="stylesheet" href="css/styles.css?v=2.2">
    <link rel="stylesheet" href="css/theme.css?v=2.5">
    <link rel="stylesheet" href="css/responsive-complete.css?v=2.2">
    <style>
        /* =============================================
           MÉTRICAS Y CAJAS DE RESUMEN
        ============================================= */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .metric-box {
            background: var(--ui-surface);
            border: 1px solid var(--ui-border);
            border-radius: 14px;
            padding: 18px 15px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .metric-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }
        .metric-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--color-naranja);
        }
        .metric-label {
            font-size: 12px;
            color: var(--ui-text-muted);
            margin-top: 5px;
        }

        /* =============================================
           FILAS SEMANALES
        ============================================= */
        .week-row {
            background: var(--ui-surface);
            border: 1px solid var(--ui-border);
            border-radius: 14px;
            padding: 14px 16px;
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        .week-row:hover { background: var(--ui-surface-soft); }
        .week-period { font-weight: 600; color: var(--ui-text); }
        .week-amount { color: var(--color-naranja); font-weight: 700; }
        .week-status-pending { color: var(--ui-text-muted); }
        .week-status-partial { color: var(--color-naranja); }
        .week-status-paid   { color: var(--ui-text); }

        /* =============================================
           ITEMS DE COTIZACIÓN
        ============================================= */
        .quote-item {
            background: var(--ui-surface);
            border-left: 4px solid var(--color-naranja);
            padding: 12px;
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 12px;
        }
        .quote-info { flex: 1; }
        .quote-qty  { font-weight: 600; }
        .quote-remove {
            background: var(--color-negro);
            color: white;
            border: none;
            padding: 4px 9px;
            border-radius: 999px;
            cursor: pointer;
            font-size: 13px;
            line-height: 1;
        }

        /* =============================================
           TABLAS GENERALES
        ============================================= */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th {
            background: var(--ui-surface-soft);
            padding: 11px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid var(--ui-border);
        }
        td {
            padding: 10px;
            border-bottom: 1px solid var(--ui-border);
            font-size: 13px;
        }
        tr:hover { background: var(--ui-surface-soft); }

        /* =============================================
           ALERTAS
        ============================================= */
        .alert {
            padding: 12px 14px;
            border-radius: 12px;
            margin: 10px 0;
            background: var(--ui-surface);
            color: var(--ui-text);
            border-left: 4px solid var(--color-naranja);
        }
        .alert-info    { background: rgba(255,127,0,0.08); border-left-color: var(--color-naranja); }
        .alert-warning { background: rgba(17,17,17,0.06);  border-left-color: var(--color-negro); }
        .alert-success { background: rgba(255,127,0,0.08); border-left-color: var(--color-naranja); }

        /* =============================================
           HISTORIAL — estilos propios
        ============================================= */
        .history-filter-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 18px;
        }
        .history-filter-bar select,
        .history-filter-bar input[type="text"] {
            padding: 8px 12px;
            border: 1px solid var(--ui-border);
            border-radius: 10px;
            background: var(--ui-surface);
            color: var(--ui-text);
            font-size: 13px;
            min-width: 140px;
        }
        .history-filter-bar select:focus,
        .history-filter-bar input:focus {
            outline: none;
            border-color: var(--color-naranja);
            box-shadow: 0 0 0 3px rgba(255,127,0,0.15);
        }
        .history-summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .history-card {
            background: linear-gradient(135deg, var(--ui-surface), var(--ui-surface-soft));
            border: 1px solid var(--ui-border);
            border-radius: 14px;
            padding: 16px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .history-card .hc-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--color-naranja);
        }
        .history-card .hc-label {
            font-size: 11px;
            color: var(--ui-text-muted);
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-order   { background: rgba(255,127,0,0.15); color: var(--color-naranja); }
        .badge-payment { background: rgba(34,197,94,0.15);  color: #16a34a; }
        .badge-supply  { background: rgba(99,102,241,0.15); color: #6366f1; }
        .badge-empty   { background: var(--ui-surface-soft); color: var(--ui-text-muted); }

        /* Paginación */
        .history-pagination {
            display: flex;
            gap: 6px;
            justify-content: center;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .page-btn {
            padding: 6px 14px;
            border: 1px solid var(--ui-border);
            border-radius: 8px;
            background: var(--ui-surface);
            color: var(--ui-text);
            cursor: pointer;
            font-size: 13px;
            transition: all 0.15s;
        }
        .page-btn:hover,
        .page-btn.active {
            background: var(--color-naranja);
            color: #fff;
            border-color: var(--color-naranja);
        }
        .page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <a href="index.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
        <nav class="nav-menu">
            <a href="index.php">Catálogo</a>
            <a href="marketplace_ce.php">Marketplace CE</a>
            <div class="nav-dropdown">
                <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                <div class="nav-dropdown-content">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="orders.php">Pedidos</a>
                    <a href="wholesale.php">Mayoreo</a>
                    <a href="account.php" class="active">Mi Cuenta</a>
                    <a href="profile.php">Perfil</a>
                </div>
            </div>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
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
    </div>
    <div class="user-menu">
        <div class="user-info"><div class="user-name"><?php echo $user_name; ?></div></div>
        <button class="btn-logout" onclick="window.location.href='api/auth.php?action=logout'">Cerrar Sesión</button>
    </div>
</header>

<main>
    <div class="container">
        <div class="page-hero">
            <div class="module-badge module-client"><span class="module-glyph">CL</span> Portal de Cliente</div>
            <h1>Mi Cuenta</h1>
            <p class="text-muted">Administra tu crédito, control semanal, pagos, cotizaciones e historial completo.</p>
        </div>

        <!-- TABS -->
        <div class="tabs mt-3">
            <button class="tab-button active" data-tab="creditTab">Estado de Crédito</button>
            <button class="tab-button" data-tab="weeklyTab">Control Semanal</button>
            <button class="tab-button" data-tab="paymentsTab">Pagos</button>
            <button class="tab-button" data-tab="quotesTab">Cotizaciones</button>
            <button class="tab-button" data-tab="historyTab">Historial</button>
        </div>

        <!-- ================================================
             PESTAÑA: Estado de Crédito
        ================================================ -->
        <section id="creditTab" class="tab-content active">
            <div class="card"><div class="card-body">
                <div class="section-header"><span class="section-dot"></span><h2>Estado de Tu Crédito</h2></div>
                <div id="creditStatus" class="text-muted">Cargando...</div>
                <div class="metrics-grid">
                    <div class="metric-box">
                        <div class="metric-label">Límite de Crédito</div>
                        <div class="metric-value" id="creditLimit">$0.00</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Crédito Disponible</div>
                        <div class="metric-value" id="creditAvailable">$0.00</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Crédito Utilizado</div>
                        <div class="metric-value" id="creditUsed">$0.00</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Total Adeudado</div>
                        <div class="metric-value" id="totalOwed">$0.00</div>
                    </div>
                </div>
                <div id="creditAlert"></div>
            </div></div>
        </section>

        <!-- ================================================
             PESTAÑA: Control Semanal
        ================================================ -->
        <section id="weeklyTab" class="tab-content">
            <div class="card"><div class="card-body">
                <div class="section-header"><span class="section-dot"></span><h2>Control Semanal de Consumo</h2></div>
                <div class="alert alert-info">
                    <strong>Semana actual:</strong> El sistema registra automáticamente tu consumo y deuda semanal.
                </div>
                <div id="weeklySummary" class="text-muted">Cargando...</div>
                <h3 class="mt-4">Historial de Últimas 12 Semanas</h3>
                <div id="weeklyHistory" class="text-muted">Cargando...</div>
            </div></div>
        </section>

        <!-- ================================================
             PESTAÑA: Pagos
        ================================================ -->
        <section id="paymentsTab" class="tab-content">
            <div class="card mb-3"><div class="card-body">
                <div class="section-header"><span class="section-dot"></span><h2>Historial de Pagos</h2></div>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsList">
                        <tr><td colspan="5" class="text-muted">Cargando...</td></tr>
                    </tbody>
                </table>
            </div></div>
        </section>

        <!-- ================================================
             PESTAÑA: Cotizaciones
        ================================================ -->
        <section id="quotesTab" class="tab-content">
            <div class="grid grid-2">
                <div class="card"><div class="card-body">
                    <div class="section-header"><span class="section-dot"></span><h2>Crear Cotización para WhatsApp</h2></div>
                    <p class="text-muted">Selecciona productos del catálogo y comparte el carrito vía WhatsApp.</p>

                    <div class="alert alert-info">
                        Las cotizaciones y dudas se envían por WhatsApp al <strong><?php echo $company_whatsapp; ?></strong>.
                    </div>

                    <div id="cartItems"></div>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--ui-border);">
                        <div style="display: flex; justify-content: space-between; font-weight: 600; margin-bottom: 15px;">
                            <span>Total:</span>
                            <span id="cartTotal">$0.00</span>
                        </div>
                        <button class="btn btn-primary" style="width: 100%; margin-bottom: 10px;" onclick="shareViaWhatsApp()">
                            📱 Compartir por WhatsApp
                        </button>
                        <button class="btn btn-secondary" style="width: 100%;" onclick="clearCart()">
                            🗑️ Limpiar carrito
                        </button>
                    </div>
                </div></div>

                <div class="card"><div class="card-body">
                    <div class="section-header"><span class="section-dot"></span><h2>Cotizaciones Anteriores</h2></div>
                    <div id="previousQuotes" class="text-muted">Cargando...</div>
                </div></div>
            </div>
        </section>

        <!-- ================================================
             PESTAÑA: Historial de Transacciones
        ================================================ -->
        <section id="historyTab" class="tab-content">
            <div class="card"><div class="card-body">
                <div class="section-header"><span class="section-dot"></span><h2>Historial General de Transacciones</h2></div>
                <p class="text-muted" style="margin-bottom: 20px;">
                    Revisa en un solo lugar todas tus compras, abonos y movimientos registrados.
                </p>

                <!-- Resumen rápido -->
                <div class="history-summary-cards" id="historySummaryCards">
                    <div class="history-card">
                        <div class="hc-value" id="historyOrdersCount">—</div>
                        <div class="hc-label">Pedidos</div>
                    </div>
                    <div class="history-card">
                        <div class="hc-value" id="historyPaymentsCount">—</div>
                        <div class="hc-label">Pagos</div>
                    </div>
                    <div class="history-card">
                        <div class="hc-value" id="historySupplyCount">—</div>
                        <div class="hc-label">Órdenes Prov.</div>
                    </div>
                    <div class="history-card">
                        <div class="hc-value" id="historyTotalCount">—</div>
                        <div class="hc-label">Total Registros</div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="history-filter-bar">
                    <select id="historyTypeFilter" onchange="applyHistoryFilters()">
                        <option value="">Todos los tipos</option>
                        <option value="client_order">Pedidos</option>
                        <option value="payment">Pagos</option>
                        <option value="supplier_order">Órdenes Proveedor</option>
                    </select>
                    <input type="text" id="historySearchFilter" placeholder="Buscar folio o referencia…" oninput="applyHistoryFilters()">
                </div>

                <!-- Tabla de historial -->
                <table>
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Folio / Ref.</th>
                            <th>Fecha</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody id="historyList">
                        <tr><td colspan="4" class="text-muted">Cargando historial...</td></tr>
                    </tbody>
                </table>

                <!-- Paginación -->
                <div class="history-pagination" id="historyPagination"></div>
            </div></div>
        </section>

    </div><!-- /.container -->
</main>

<script>
window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
</script>
<script src="js/main.js?v=2.6"></script>
<script>
/* ============================================================
   TABS
============================================================ */
function setupTabs() {
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            this.classList.add('active');
        });
    });
}

/* ============================================================
   HELPERS
============================================================ */
function formatMoney(value) {
    return `$${Number(value || 0).toFixed(2)}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/* ============================================================
   CRÉDITO
============================================================ */
async function loadCreditSummary() {
    const res = await apiCall('/client_account.php?action=credit-summary');
    if (!res || !res.success) {
        document.getElementById('creditStatus').innerHTML = '<div class="alert alert-warning">No se pudo cargar el estado del crédito</div>';
        return;
    }

    const c = res.credit;
    document.getElementById('creditLimit').textContent     = formatMoney(c.credit_limit);
    document.getElementById('creditAvailable').textContent = formatMoney(c.credit_available);
    document.getElementById('creditUsed').textContent      = formatMoney(c.credit_used);
    document.getElementById('totalOwed').textContent       = formatMoney(c.total_owed);

    let alertHtml = '';
    if (c.days_overdue > 0) {
        alertHtml = `<div class="alert alert-warning"><strong>⚠️ Cuenta vencida:</strong> Tienes ${c.days_overdue} días de atraso. Por favor contacta para ponerte al corriente.</div>`;
    } else if (c.total_owed > 0) {
        alertHtml = `<div class="alert alert-info"><strong>ℹ️ Nota activa:</strong> Tienes ${formatMoney(c.total_owed)} por pagar.</div>`;
    } else {
        alertHtml = `<div class="alert alert-success"><strong>✅ Cuenta al corriente:</strong> No tienes deudas pendientes.</div>`;
    }
    document.getElementById('creditAlert').innerHTML = alertHtml;
}

/* ============================================================
   CONTROL SEMANAL
============================================================ */
async function loadWeeklySummary() {
    const res = await apiCall('/client_account.php?action=weekly-summary');
    if (!res || !res.success) {
        document.getElementById('weeklySummary').innerHTML = '<p class="text-muted">Sin datos</p>';
        return;
    }

    const w = res.weekly;
    const html = `
        <div class="week-row">
            <div>
                <div class="week-period">${w.week_start} a ${w.week_end}</div>
                <div class="text-muted">Semana actual</div>
            </div>
            <div style="text-align: right;">
                <div>Consumido: <span style="color: var(--color-naranja);">${formatMoney(w.total_consumed)}</span></div>
                <div>Adeudado: <span class="week-status-${w.payment_status}">${formatMoney(w.total_owed)}</span></div>
                <div style="font-size: 11px; color: var(--ui-text-muted);">Estado: ${w.payment_status}</div>
            </div>
        </div>
    `;
    document.getElementById('weeklySummary').innerHTML = html;

    const histRes = await apiCall('/client_account.php?action=weekly-history');
    if (!histRes || !histRes.success) {
        document.getElementById('weeklyHistory').innerHTML = '<p class="text-muted">Sin histórico</p>';
        return;
    }

    const weeks = histRes.weeks || [];
    if (weeks.length === 0) {
        document.getElementById('weeklyHistory').innerHTML = '<p class="text-muted">Sin histórico</p>';
        return;
    }

    const histHtml = weeks.map(w => `
        <div class="week-row">
            <div>
                <div class="week-period">${w.week_start} a ${w.week_end}</div>
            </div>
            <div style="text-align: right;">
                <div>Consumido: ${formatMoney(w.total_consumed)}</div>
                <div>Adeudado: ${formatMoney(w.total_owed)}</div>
                <div style="font-size: 11px; color: var(--ui-text-muted);">Estado: <span class="week-status-${w.payment_status}">${w.payment_status}</span></div>
            </div>
        </div>
    `).join('');
    document.getElementById('weeklyHistory').innerHTML = histHtml;
}

/* ============================================================
   HISTORIAL DE PAGOS
============================================================ */
async function loadPaymentHistory() {
    const res = await apiCall('/client_account.php?action=payment-history');
    if (!res || !res.success || !res.payments || res.payments.length === 0) {
        document.getElementById('paymentsList').innerHTML = '<tr><td colspan="5" class="text-muted">Sin pagos registrados</td></tr>';
        return;
    }

    const html = res.payments.map(p => `
        <tr>
            <td>${escapeHtml(p.payment_date)}</td>
            <td><strong>${formatMoney(p.payment_amount)}</strong></td>
            <td>${escapeHtml(p.payment_method)}</td>
            <td>${escapeHtml(p.reference_number || '-')}</td>
            <td><small>${escapeHtml(p.notes || '-')}</small></td>
        </tr>
    `).join('');
    document.getElementById('paymentsList').innerHTML = html;
}

/* ============================================================
   CARRITO / COTIZACIÓN
============================================================ */
function addProductToCart(name, price, productId) {
    let cart = JSON.parse(localStorage.getItem('whatsapp_cart') || '[]');
    const existing = cart.find(item => item.product_id === productId);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({ product_id: productId, name, price: Number(price), quantity: 1 });
    }
    localStorage.setItem('whatsapp_cart', JSON.stringify(cart));
    renderCart();
    showAlert('Producto agregado', 'success');
}

function renderCart() {
    const cart = JSON.parse(localStorage.getItem('whatsapp_cart') || '[]');
    let html = '';
    let total = 0;

    if (cart.length === 0) {
        html = '<p class="text-muted">Carrito vacío. Agrega productos desde el catálogo.</p>';
    } else {
        html = cart.map((item) => {
            const subtotal = item.price * item.quantity;
            total += subtotal;
            return `
                <div class="quote-item">
                    <div class="quote-info">
                        <div class="quote-qty">${item.quantity}x ${escapeHtml(item.name)}</div>
                        <div style="font-size: 12px; color: var(--ui-text-muted);">$${item.price.toFixed(2)} c/u</div>
                    </div>
                    <div style="text-align: right; margin-right: 10px;">${formatMoney(subtotal)}</div>
                    <button class="quote-remove" onclick="removeFromCart(${item.product_id})">×</button>
                </div>
            `;
        }).join('');
    }

    document.getElementById('cartItems').innerHTML = html;
    document.getElementById('cartTotal').textContent = formatMoney(total);
}

function removeFromCart(productId) {
    let cart = JSON.parse(localStorage.getItem('whatsapp_cart') || '[]');
    cart = cart.filter(item => item.product_id !== productId);
    localStorage.setItem('whatsapp_cart', JSON.stringify(cart));
    renderCart();
}

function clearCart() {
    localStorage.removeItem('whatsapp_cart');
    renderCart();
}

async function shareViaWhatsApp() {
    const cart = JSON.parse(localStorage.getItem('whatsapp_cart') || '[]');
    if (cart.length === 0) { showAlert('El carrito está vacío', 'warning'); return; }

    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

    const res = await apiCall('/client_account.php?action=whatsapp-quote', 'POST', {
        items: cart,
        total: total,
        whatsapp_phone: '<?php echo $company_whatsapp; ?>'
    });

    if (!res || !res.success) { showAlert('Error al crear cotización', 'error'); return; }

    showAlert('Abriendo WhatsApp...', 'success');
    setTimeout(() => { window.open(res.whatsapp_url, '_blank'); loadPreviousQuotes(); }, 500);
}

async function loadPreviousQuotes() {
    const res = await apiCall('/client_account.php?action=pending-quotes');
    if (!res || !res.success || !res.quotes || res.quotes.length === 0) {
        document.getElementById('previousQuotes').innerHTML = '<p class="text-muted">Sin cotizaciones</p>';
        return;
    }

    const html = res.quotes.map(q => `
        <div class="week-row">
            <div>
                <div style="font-weight: 600;">${escapeHtml(q.created_at)}</div>
                <div style="font-size: 12px; color: var(--ui-text-muted);">${q.items_count} producto(s)</div>
            </div>
            <div style="text-align: right;">
                <div>${formatMoney(q.total_amount)}</div>
                <div style="font-size: 11px;"><span style="background: var(--color-naranja); color: white; padding: 2px 8px; border-radius: 999px;">${escapeHtml(q.status)}</span></div>
            </div>
        </div>
    `).join('');
    document.getElementById('previousQuotes').innerHTML = html;
}

/* ============================================================
   HISTORIAL GENERAL DE TRANSACCIONES
============================================================ */
let _allHistoryItems = [];
let _historyPage    = 1;
const HISTORY_PER_PAGE = 20;

async function loadHistory() {
    const res = await apiCall('/client_account.php?action=history');
    const body = document.getElementById('historyList');

    if (!res || !res.success || !res.items || res.items.length === 0) {
        body.innerHTML = '<tr><td colspan="4" class="text-muted">Sin registros de historial</td></tr>';
        return;
    }

    _allHistoryItems = res.items;
    _historyPage = 1;

    // Resumen
    let ordersCount  = 0;
    let paymentsCount = 0;
    let supplyCount  = 0;
    _allHistoryItems.forEach(i => {
        if (i.transaction_type === 'client_order')   ordersCount++;
        if (i.transaction_type === 'payment')         paymentsCount++;
        if (i.transaction_type === 'supplier_order')  supplyCount++;
    });
    const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    setEl('historyOrdersCount',   ordersCount);
    setEl('historyPaymentsCount', paymentsCount);
    setEl('historySupplyCount',   supplyCount);
    setEl('historyTotalCount',    _allHistoryItems.length);

    renderHistoryTable(_allHistoryItems);
}

function applyHistoryFilters() {
    const typeFilter   = document.getElementById('historyTypeFilter').value;
    const searchFilter = document.getElementById('historySearchFilter').value.trim().toLowerCase();

    let filtered = _allHistoryItems.filter(i => {
        const matchType   = !typeFilter || i.transaction_type === typeFilter;
        const matchSearch = !searchFilter || (i.reference_folio || '').toLowerCase().includes(searchFilter);
        return matchType && matchSearch;
    });

    _historyPage = 1;
    renderHistoryTable(filtered);
}

function renderHistoryTable(items) {
    const body       = document.getElementById('historyList');
    const pagination = document.getElementById('historyPagination');

    if (items.length === 0) {
        body.innerHTML = '<tr><td colspan="4" class="text-muted">Sin registros</td></tr>';
        pagination.innerHTML = '';
        return;
    }

    const totalPages = Math.ceil(items.length / HISTORY_PER_PAGE);
    const start      = (_historyPage - 1) * HISTORY_PER_PAGE;
    const pageItems  = items.slice(start, start + HISTORY_PER_PAGE);

    const html = pageItems.map(i => {
        let badgeClass = 'badge-order';
        let typeLabel  = 'Pedido';
        if (i.transaction_type === 'payment') {
            badgeClass = 'badge-payment';
            typeLabel  = 'Pago';
        } else if (i.transaction_type === 'supplier_order') {
            badgeClass = 'badge-supply';
            typeLabel  = 'Orden Prov.';
        }

        let parsedData = {};
        if (i.data_json) {
            try { parsedData = JSON.parse(i.data_json); } catch (e) {}
        }

        let detailHtml = '';
        if (i.transaction_type === 'client_order') {
            detailHtml = `Monto total: <strong>${formatMoney(parsedData.total || 0)}</strong>`;
        } else if (i.transaction_type === 'payment') {
            detailHtml = `Abono: <strong>${formatMoney(parsedData.amount || 0)}</strong> (${escapeHtml(parsedData.method || 'efectivo')})`;
        } else {
            detailHtml = `<small>${escapeHtml(i.data_json || '')}</small>`;
        }

        return `
            <tr>
                <td><span class="badge ${badgeClass}">${typeLabel}</span></td>
                <td><strong>${escapeHtml(i.reference_folio || '—')}</strong></td>
                <td>${escapeHtml(i.created_at || '—')}</td>
                <td>${detailHtml}</td>
            </tr>
        `;
    }).join('');

    body.innerHTML = html;

    // Paginación
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }

    let pageHtml = `
        <button class="page-btn" onclick="changeHistoryPage(${_historyPage - 1})" ${_historyPage === 1 ? 'disabled' : ''}>‹</button>
    `;
    for (let p = 1; p <= totalPages; p++) {
        pageHtml += `<button class="page-btn ${p === _historyPage ? 'active' : ''}" onclick="changeHistoryPage(${p})">${p}</button>`;
    }
    pageHtml += `<button class="page-btn" onclick="changeHistoryPage(${_historyPage + 1})" ${_historyPage === totalPages ? 'disabled' : ''}>›</button>`;
    pagination.innerHTML = pageHtml;
}

function changeHistoryPage(page) {
    const typeFilter   = document.getElementById('historyTypeFilter').value;
    const searchFilter = document.getElementById('historySearchFilter').value.trim().toLowerCase();
    let filtered = _allHistoryItems.filter(i => {
        const matchType   = !typeFilter || i.transaction_type === typeFilter;
        const matchSearch = !searchFilter || (i.reference_folio || '').toLowerCase().includes(searchFilter);
        return matchType && matchSearch;
    });
    const totalPages = Math.ceil(filtered.length / HISTORY_PER_PAGE);
    if (page < 1 || page > totalPages) return;
    _historyPage = page;
    renderHistoryTable(filtered);
    document.getElementById('historyTab').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ============================================================
   INICIALIZACIÓN
============================================================ */
document.addEventListener('DOMContentLoaded', function() {
    setupTabs();
    loadCreditSummary();
    loadWeeklySummary();
    loadPaymentHistory();
    renderCart();
    loadPreviousQuotes();
    loadHistory();
});
</script>
<script src="js/mobile-optimize.js"></script>
</body>
</html>
