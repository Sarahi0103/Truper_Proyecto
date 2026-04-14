<?php
require_once '../config/config.php';
require_login();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Cliente', ENT_QUOTES, 'UTF-8');
$user_id = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 15px 0; }
        .metric-box { background: var(--ui-surface); border: 1px solid var(--ui-border); border-radius: 14px; padding: 15px; text-align: center; box-shadow: 0 8px 20px rgba(17, 24, 39, 0.05); }
        .metric-value { font-size: 20px; font-weight: 700; color: var(--color-naranja); }
        .metric-label { font-size: 12px; color: var(--ui-text-muted); margin-top: 5px; }

        .week-row { background: var(--ui-surface); border: 1px solid var(--ui-border); border-radius: 14px; padding: 12px; margin: 8px 0; display: flex; justify-content: space-between; align-items: center; }
        .week-period { font-weight: 600; color: var(--ui-text); }
        .week-amount { color: var(--color-naranja); font-weight: 700; }
        .week-status-pending { color: var(--ui-text-muted); }
        .week-status-partial { color: var(--color-naranja); }
        .week-status-paid { color: var(--ui-text); }

        .quote-item { background: var(--ui-surface); border-left: 4px solid var(--color-naranja); padding: 10px; margin: 8px 0; display: flex; justify-content: space-between; align-items: center; border-radius: 12px; }
        .quote-info { flex: 1; }
        .quote-qty { font-weight: 600; }
        .quote-remove { background: var(--color-negro); color: white; border: none; padding: 4px 8px; border-radius: 999px; cursor: pointer; font-size: 11px; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: var(--ui-surface-soft); padding: 10px; text-align: left; font-weight: 600; border-bottom: 2px solid var(--ui-border); }
        td { padding: 10px; border-bottom: 1px solid var(--ui-border); }
        tr:hover { background: var(--ui-surface-soft); }

        .alert { padding: 12px; border-radius: 12px; margin: 10px 0; background: var(--ui-surface); color: var(--ui-text); border-left: 4px solid var(--color-naranja); }
        .alert-info { background: rgba(255, 127, 0, 0.08); color: var(--ui-text); border-left: 4px solid var(--color-naranja); }
        .alert-warning { background: rgba(17, 17, 17, 0.06); color: var(--ui-text); border-left: 4px solid var(--color-negro); }
        .alert-success { background: rgba(255, 127, 0, 0.08); color: var(--ui-text); border-left: 4px solid var(--color-naranja); }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <a href="index.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
        <nav class="nav-menu">
            <a href="index.php">Productos</a>
            <a href="orders.php">Mis Pedidos</a>
            <a href="account.php" class="active">Mi Cuenta</a>
            <a href="profile.php">Perfil</a>
        </nav>
    </div>
    <div class="user-menu">
        <div class="theme-toggle">
            <button type="button" data-theme-toggle-btn><span data-theme-toggle-label>Modo claro</span></button>
        </div>
        <div class="user-info"><div class="user-name"><?php echo $user_name; ?></div></div>
        <button class="btn-logout" onclick="window.location.href='api/auth.php?action=logout'">Cerrar Sesion</button>
    </div>
</header>

<main>
    <div class="container">
        <div class="page-hero">
            <div class="module-badge module-client"><span class="module-glyph">CL</span> Portal de cliente</div>
            <h1>Mi Cuenta</h1>
            <p class="text-muted">Administra tu crédito, control semanal y cotizaciones.</p>
        </div>

        <div class="tabs mt-3">
            <button class="tab-button active" data-tab="creditTab">Estado de Crédito</button>
            <button class="tab-button" data-tab="weeklyTab">Control Semanal</button>
            <button class="tab-button" data-tab="paymentsTab">Pagos</button>
            <button class="tab-button" data-tab="quotesTab">Compartir Cotización</button>
        </div>

        <!-- Estado de Crédito -->
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

        <!-- Control Semanal -->
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

        <!-- Pagos -->
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

        <!-- Cotización por WhatsApp -->
        <section id="quotesTab" class="tab-content">
            <div class="grid grid-2">
                <div class="card"><div class="card-body">
                    <div class="section-header"><span class="section-dot"></span><h2>Crear Cotización para WhatsApp</h2></div>
                    <p class="text-muted">Selecciona productos del catálogo y comparte el carrito vía WhatsApp.</p>
                    
                    <div class="alert alert-info">
                        Tu número de empresa será usado para enviar la cotización.
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
    </div>
</main>

<script src="js/main.js"></script>
<script>
function setupTabs() {
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(b => {
                b.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
            this.classList.add('active');
        });
    });
}

function formatMoney(value) {
    return `$${Number(value || 0).toFixed(2)}`;
}

async function loadCreditSummary() {
    const res = await apiCall('/client_account.php?action=credit-summary');
    if (!res || !res.success) {
        document.getElementById('creditStatus').innerHTML = '<div class="alert alert-warning">No se pudo cargar el estado del crédito</div>';
        return;
    }

    const c = res.credit;
    document.getElementById('creditLimit').textContent = formatMoney(c.credit_limit);
    document.getElementById('creditAvailable').textContent = formatMoney(c.credit_available);
    document.getElementById('creditUsed').textContent = formatMoney(c.credit_used);
    document.getElementById('totalOwed').textContent = formatMoney(c.total_owed);

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

    // Load history
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
                <div style="font-size: 11px; color: var(--text-secondary);">Estado: <span class="week-status-${w.payment_status}">${w.payment_status}</span></div>
            </div>
        </div>
    `).join('');
    document.getElementById('weeklyHistory').innerHTML = histHtml;
}

async function loadPaymentHistory() {
    const res = await apiCall('/client_account.php?action=payment-history');
    if (!res || !res.success || !res.payments) {
        document.getElementById('paymentsList').innerHTML = '<tr><td colspan="5" class="text-muted">Sin pagos registrados</td></tr>';
        return;
    }

    const payments = res.payments;
    if (payments.length === 0) {
        document.getElementById('paymentsList').innerHTML = '<tr><td colspan="5" class="text-muted">Sin pagos registrados</td></tr>';
        return;
    }

    const html = payments.map(p => `
        <tr>
            <td>${p.payment_date}</td>
            <td><strong>${formatMoney(p.payment_amount)}</strong></td>
            <td>${p.payment_method}</td>
            <td>${p.reference_number || '-'}</td>
            <td><small>${p.notes || '-'}</small></td>
        </tr>
    `).join('');
    document.getElementById('paymentsList').innerHTML = html;
}

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
        html = cart.map((item, idx) => {
            const subtotal = item.price * item.quantity;
            total += subtotal;
            return `
                <div class="quote-item">
                    <div class="quote-info">
                        <div class="quote-qty">${item.quantity}x ${item.name}</div>
                        <div style="font-size: 12px; color: var(--ui-text-muted);">$${item.price.toFixed(2)} c/u</div>
                    </div>
                    <div style="text-align: right; margin-right: 10px;">
                        ${formatMoney(subtotal)}
                    </div>
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
    if (cart.length === 0) {
        showAlert('El carrito está vacío', 'warning');
        return;
    }

    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    
    const res = await apiCall('/client_account.php?action=whatsapp-quote', 'POST', {
        items: cart,
        total: total,
        whatsapp_phone: ''
    });

    if (!res || !res.success) {
        showAlert('Error al crear cotización', 'error');
        return;
    }

    showAlert('Abriendo WhatsApp...', 'success');
    setTimeout(() => {
        window.open(res.whatsapp_url, '_blank');
        loadPreviousQuotes();
    }, 500);
}

async function loadPreviousQuotes() {
    const res = await apiCall('/client_account.php?action=pending-quotes');
    if (!res || !res.success || !res.quotes) {
        document.getElementById('previousQuotes').innerHTML = '<p class="text-muted">Sin cotizaciones</p>';
        return;
    }

    const quotes = res.quotes;
    if (quotes.length === 0) {
        document.getElementById('previousQuotes').innerHTML = '<p class="text-muted">Sin cotizaciones</p>';
        return;
    }

    const html = quotes.map(q => {
        let data = q.quote_data;
        if (typeof data === 'string') {
            try {
                data = JSON.parse(data);
            } catch (e) {
                data = [];
            }
        }
        return `
            <div class="week-row">
                <div>
                    <div style="font-weight: 600;">${q.created_at}</div>
                    <div style="font-size: 12px; color: var(--ui-text-muted);">${q.items_count} producto(s)</div>
                </div>
                <div style="text-align: right;">
                    <div>${formatMoney(q.total_amount)}</div>
                    <div style="font-size: 11px;"><span style="background: var(--color-naranja); color: white; padding: 2px 6px; border-radius: 999px;">${q.status}</span></div>
                </div>
            </div>
        `;
    }).join('');
    document.getElementById('previousQuotes').innerHTML = html;
}

document.addEventListener('DOMContentLoaded', function() {
    setupTabs();
    loadCreditSummary();
    loadWeeklySummary();
    loadPaymentHistory();
    renderCart();
    loadPreviousQuotes();
});
</script>
</body>
</html>
