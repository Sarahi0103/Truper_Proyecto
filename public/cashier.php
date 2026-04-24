<?php
require_once '../config/config.php';
require_admin();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cajon de Dinero - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
      .cash-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; }
      .metric { border: 1px solid var(--ui-border); border-radius: 10px; padding: 10px; background: var(--ui-surface); }
      .metric-label { font-size: 12px; color: var(--ui-text-muted); }
      .metric-value { font-size: 18px; font-weight: 700; color: var(--ui-text); }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
        <nav class="nav-menu">
            <a href="/" >Inicio</a>
            <a href="cart.php">Carrito</a>
            <a href="orders.php">Pedidos</a>
            <a href="wholesale.php">Mayoreo</a>
            <a href="cashier.php" class="active">Caja</a>
          <a href="admin_supply.php">Abastecimiento</a>
            <a href="analytics.php">Estadisticas</a>
            <a href="marketplace_ce.php">Marketplace CE</a>
          <a href="profile.php">Perfil</a>
        </nav>
    </div>
    <div class="user-menu">
        <div class="theme-toggle"><button type="button" data-theme-toggle-btn><span data-theme-toggle-label>Modo obscuro</span></button></div>
        <div class="user-info"><div class="user-name"><?php echo $user_name; ?></div></div>
      <a href="/" class="btn btn-small btn-ghost">Ver portada</a>
        <button class="btn-logout" onclick="window.location.href='api/auth.php?action=logout'">Cerrar Sesion</button>
    </div>
</header>
<main>
    <div class="container">
    <div class="page-hero">
      <div class="module-badge module-finance"><span class="module-glyph">CJ</span> Control financiero</div>
      <h1>Control de Cajon de Dinero</h1>
      <p class="text-muted">Apertura/cierre, metas mensuales, plazos de pago y control semanal del flujo.</p>
    </div>

        <div class="grid grid-2 mt-3">
            <div class="card"><div class="card-body">
                <h3>Abrir Caja</h3>
                <input id="openAmount" type="number" step="0.01" placeholder="Monto inicial">
                <button class="btn btn-primary mt-2" onclick="openDrawer()">Abrir</button>
            </div></div>
            <div class="card"><div class="card-body">
                <h3>Cerrar Caja</h3>
                <input id="closeAmount" type="number" step="0.01" placeholder="Monto contado">
                <input id="closeNote" type="text" placeholder="Observaciones" class="mt-1">
                <button class="btn btn-danger mt-2" onclick="closeDrawer()">Cerrar</button>
            </div></div>
        </div>

        <div class="card mt-3"><div class="card-body">
            <h3>Estatus actual</h3>
            <div id="cashierStatus" class="text-muted">Consultando...</div>
            <div id="cashierMetrics" class="cash-metrics mt-2"></div>
        </div></div>

            <div class="tabs mt-3">
              <button class="tab-button active" data-tab="cashMovementsTab">Movimientos</button>
              <button class="tab-button" data-tab="cashGoalsTab">Objetivo mensual</button>
              <button class="tab-button" data-tab="cashNotesTab">Notas y plazos</button>
              <button class="tab-button" data-tab="cashWeeklyTab">Flujo semanal</button>
            </div>

            <section id="cashMovementsTab" class="tab-content active">
              <div class="card mt-3"><div class="card-body">
                <h3>Movimiento de Caja</h3>
                <select id="moveType">
                  <option value="in">Entrada</option>
                  <option value="out">Salida</option>
                  <option value="sale">Venta en efectivo</option>
                </select>
                <input id="moveAmount" type="number" step="0.01" placeholder="Monto" class="mt-1">
                <input id="moveDesc" type="text" placeholder="Descripcion" class="mt-1">
                <button class="btn btn-primary mt-2" onclick="addMovement()">Registrar movimiento</button>
              </div></div>
            </section>

            <section id="cashGoalsTab" class="tab-content">
              <div class="card mt-3"><div class="card-body">
                <h3>Objetivo mensual</h3>
                <div class="grid grid-3">
                  <div class="form-group"><label>Mes (YYYY-MM)</label><input id="goalMonth" type="text" placeholder="2026-04"></div>
                  <div class="form-group"><label>Meta ($)</label><input id="goalAmount" type="number" step="0.01" min="0" placeholder="0.00"></div>
                  <div class="form-group d-flex align-center"><button class="btn btn-primary" onclick="saveMonthlyGoal()">Guardar meta</button></div>
                </div>
                <div id="goalSummary" class="mt-2 text-muted">Sin resumen de meta</div>
                <div id="goalWeekly" class="mt-2 text-muted">Sin desglose semanal</div>
              </div></div>
            </section>

            <section id="cashNotesTab" class="tab-content">
              <div class="card mt-3"><div class="card-body">
                <h3>Registrar nota (cliente/proveedor)</h3>
                <div class="grid grid-3">
                  <div class="form-group">
                    <label>Tipo</label>
                    <select id="noteType">
                      <option value="customer">Cliente</option>
                      <option value="supplier">Proveedor</option>
                    </select>
                  </div>
                  <div class="form-group"><label>Monto total</label><input id="noteAmount" type="number" step="0.01" min="0"></div>
                  <div class="form-group">
                    <label>Plazo</label>
                    <select id="noteTerm">
                      <option value="contado">Contado</option>
                      <option value="15dias">15 días</option>
                      <option value="30dias">30 días</option>
                    </select>
                  </div>
                </div>
                <input id="noteRef" type="text" placeholder="Referencia ticket/folio" class="mt-1">
                <input id="noteDesc" type="text" placeholder="Descripción" class="mt-1">
                <button class="btn btn-primary mt-2" onclick="createControlNote()">Registrar nota</button>
              </div></div>

              <div class="card mt-3"><div class="card-body">
                <h3>Abonar a nota</h3>
                <div class="grid grid-3">
                  <div class="form-group"><label>ID nota</label><input id="payNoteId" type="number" min="1"></div>
                  <div class="form-group"><label>Monto abono</label><input id="payAmount" type="number" step="0.01" min="0.01"></div>
                  <div class="form-group"><label>Método</label><input id="payMethod" type="text" placeholder="cash"></div>
                </div>
                <input id="payNotes" type="text" placeholder="Observaciones" class="mt-1">
                <button class="btn btn-secondary mt-2" onclick="registerNotePayment()">Registrar abono</button>
              </div></div>

              <div class="card mt-3"><div class="card-body">
                <h3>Notas registradas</h3>
                <div id="notesList" class="text-muted">Cargando notas...</div>
              </div></div>
            </section>

            <section id="cashWeeklyTab" class="tab-content">
              <div class="card mt-3"><div class="card-body">
                <h3>Flujo semanal</h3>
                <div class="grid grid-3">
                  <div class="form-group"><label>Semanas a consultar</label><input id="flowWeeks" type="number" min="1" max="24" value="8"></div>
                  <div class="form-group d-flex align-center"><button class="btn btn-primary" onclick="loadWeeklyCashflow()">Consultar flujo</button></div>
                </div>
                <div id="weeklyFlow" class="text-muted mt-2">Sin datos de flujo.</div>
              </div></div>
            </section>
    </div>
</main>
<script>
window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
</script>
<script src="js/main.js"></script>
<script>
function formatMoney(value) {
  return `$${Number(value || 0).toFixed(2)}`;
}

function renderMetrics(summary) {
  const box = document.getElementById('cashierMetrics');
  if (!box) return;
  if (!summary) {
    box.innerHTML = '<div class="text-muted">Sin datos de resumen</div>';
    return;
  }

  box.innerHTML = [
    { label: 'Efectivo esperado en caja', value: formatMoney(summary.cash_expected) },
    { label: 'Ventas del dia', value: formatMoney(summary.sales_today) },
    { label: 'Cobros pendientes', value: formatMoney(summary.pending_collections) },
    { label: 'Pagos a proveedor pendientes', value: formatMoney(summary.pending_supplier_payments) },
    { label: 'Notas pendientes', value: formatMoney(summary.pending_notes) },
    { label: 'Notas vencidas', value: formatMoney(summary.overdue_notes) },
    { label: 'Ganancia real estimada', value: formatMoney(summary.real_profit) },
    { label: 'Margen de ganancia', value: `${Number(summary.profit_margin_pct || 0).toFixed(2)}%` }
  ].map((m) => `<div class="metric"><div class="metric-label">${m.label}</div><div class="metric-value">${m.value}</div></div>`).join('');
}

function setupTabs() {
  document.querySelectorAll('.tab-button').forEach((btn) => {
    btn.addEventListener('click', function () {
      const tab = btn.getAttribute('data-tab');
      document.querySelectorAll('.tab-button').forEach((b) => b.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach((section) => section.classList.remove('active'));
      btn.classList.add('active');
      const target = document.getElementById(tab);
      if (target) target.classList.add('active');
    });
  });
}

async function refreshStatus() {
  const res = await apiCall('/cashier.php?action=summary');
  const box = document.getElementById('cashierStatus');
  if (!res || !res.success) {
    box.textContent = 'No fue posible consultar estatus';
    renderMetrics(null);
    return;
  }
  if (!res.open_session) {
    box.textContent = 'Caja cerrada';
  } else {
    box.textContent = `Caja abierta desde ${res.open_session.opened_at} con monto inicial ${res.open_session.opening_amount}`;
  }
  renderMetrics(res.summary || null);
}

async function openDrawer() {
  const res = await apiCall('/cashier.php?action=open', 'POST', { opening_amount: document.getElementById('openAmount').value || 0 });
  if (res && res.success) showAlert(res.message, 'success'); else if (res) showAlert(res.message, 'error');
  refreshStatus();
}

async function addMovement() {
  const res = await apiCall('/cashier.php?action=movement', 'POST', {
    movement_type: document.getElementById('moveType').value,
    amount: document.getElementById('moveAmount').value,
    description: document.getElementById('moveDesc').value
  });
  if (res && res.success) showAlert(res.message, 'success'); else if (res) showAlert(res.message, 'error');
  refreshStatus();
}

async function closeDrawer() {
  const res = await apiCall('/cashier.php?action=close', 'POST', {
    closing_amount: document.getElementById('closeAmount').value,
    notes: document.getElementById('closeNote').value
  });
  if (res && res.success) {
    showAlert(`Caja cerrada. Diferencia: ${res.difference_amount}`, 'success');
  } else if (res) {
    showAlert(res.message, 'error');
  }
  refreshStatus();
}

async function saveMonthlyGoal() {
  const monthKey = document.getElementById('goalMonth').value || new Date().toISOString().slice(0, 7);
  const targetAmount = document.getElementById('goalAmount').value || 0;
  const res = await apiCall('/cashier.php?action=goal-save', 'POST', {
    month_key: monthKey,
    target_amount: targetAmount
  });
  if (res && res.success) {
    showAlert(res.message, 'success');
    loadGoalSummary();
  } else if (res) {
    showAlert(res.message, 'error');
  }
}

async function loadGoalSummary() {
  const monthKey = document.getElementById('goalMonth').value || new Date().toISOString().slice(0, 7);
  const res = await apiCall(`/cashier.php?action=goal-summary&month_key=${encodeURIComponent(monthKey)}`);
  const summary = document.getElementById('goalSummary');
  const weekly = document.getElementById('goalWeekly');

  if (!res || !res.success) {
    if (summary) summary.textContent = 'No se pudo consultar la meta mensual';
    if (weekly) weekly.textContent = 'Sin desglose semanal';
    return;
  }

  const g = res.goal || {};
  if (summary) {
    summary.innerHTML = `
      <div><strong>Meta:</strong> ${formatMoney(g.target_amount)}</div>
      <div><strong>Acumulado:</strong> ${formatMoney(g.achieved_amount)}</div>
      <div><strong>Restante:</strong> ${formatMoney(g.remaining_amount)}</div>
      <div><strong>Avance:</strong> ${Number(g.progress_pct || 0).toFixed(2)}%</div>
    `;
  }

  const rows = Array.isArray(res.weekly) ? res.weekly : [];
  if (rows.length === 0) {
    if (weekly) weekly.innerHTML = '<p class="text-muted">Sin ventas semanales para este mes.</p>';
    return;
  }

  if (weekly) {
    weekly.innerHTML = `
      <table>
        <thead><tr><th>Semana</th><th>Objetivo semanal</th><th>Acumulado semanal</th></tr></thead>
        <tbody>
          ${rows.map((r) => `<tr><td>${r.week_start}</td><td>${formatMoney(r.week_target)}</td><td>${formatMoney(r.week_total)}</td></tr>`).join('')}
        </tbody>
      </table>
    `;
  }
}

async function createControlNote() {
  const payload = {
    note_type: document.getElementById('noteType').value,
    total_amount: document.getElementById('noteAmount').value,
    payment_term: document.getElementById('noteTerm').value,
    reference_ticket: document.getElementById('noteRef').value,
    description: document.getElementById('noteDesc').value
  };
  const res = await apiCall('/cashier.php?action=note-create', 'POST', payload);
  if (res && res.success) {
    showAlert(`${res.message}: ${res.note_folio || ''}`, 'success');
    loadNotesList();
    refreshStatus();
  } else if (res) {
    showAlert(res.message, 'error');
  }
}

async function loadNotesList() {
  const res = await apiCall('/cashier.php?action=notes-list');
  const box = document.getElementById('notesList');
  if (!res || !res.success || !Array.isArray(res.items) || res.items.length === 0) {
    if (box) box.innerHTML = '<p class="text-muted">No hay notas registradas.</p>';
    return;
  }

  box.innerHTML = `
    <table>
      <thead><tr><th>ID</th><th>Folio</th><th>Tipo</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>Plazo</th><th>Vence</th><th>Estatus</th></tr></thead>
      <tbody>
        ${res.items.map((n) => {
          const total = Number(n.total_amount || 0);
          const paid = Number(n.amount_paid || 0);
          const remaining = Math.max(0, total - paid);
          return `<tr>
            <td>${n.id}</td>
            <td>${n.note_folio || ''}</td>
            <td>${n.note_type || ''}</td>
            <td>${formatMoney(total)}</td>
            <td>${formatMoney(paid)}</td>
            <td>${formatMoney(remaining)}</td>
            <td>${n.payment_term || ''}</td>
            <td>${n.due_date || '-'}</td>
            <td>${n.status || ''}</td>
          </tr>`;
        }).join('')}
      </tbody>
    </table>
  `;
}

async function registerNotePayment() {
  const payload = {
    note_id: document.getElementById('payNoteId').value,
    amount: document.getElementById('payAmount').value,
    payment_method: document.getElementById('payMethod').value || 'cash',
    notes: document.getElementById('payNotes').value
  };
  const res = await apiCall('/cashier.php?action=note-payment', 'POST', payload);
  if (res && res.success) {
    showAlert(`${res.message}. Saldo restante: ${formatMoney(res.remaining || 0)}`, 'success');
    loadNotesList();
    refreshStatus();
  } else if (res) {
    showAlert(res.message, 'error');
  }
}

async function loadWeeklyCashflow() {
  const weeks = document.getElementById('flowWeeks').value || 8;
  const res = await apiCall(`/cashier.php?action=weekly-cashflow&weeks=${encodeURIComponent(weeks)}`);
  const box = document.getElementById('weeklyFlow');
  if (!res || !res.success) {
    if (box) box.textContent = 'No fue posible consultar flujo semanal';
    return;
  }

  const movementRows = Array.isArray(res.movements) ? res.movements : [];
  if (movementRows.length === 0) {
    if (box) box.innerHTML = '<p class="text-muted">Sin movimientos para el periodo.</p>';
    return;
  }

  box.innerHTML = `
    <table>
      <thead><tr><th>Semana</th><th>Entradas</th><th>Salidas</th><th>Neto</th></tr></thead>
      <tbody>
        ${movementRows.map((r) => {
          const totalIn = Number(r.total_in || 0);
          const totalOut = Number(r.total_out || 0);
          return `<tr><td>${r.week_start}</td><td>${formatMoney(totalIn)}</td><td>${formatMoney(totalOut)}</td><td>${formatMoney(totalIn - totalOut)}</td></tr>`;
        }).join('')}
      </tbody>
    </table>
  `;
}

document.addEventListener('DOMContentLoaded', () => {
  setupTabs();
  const currentMonth = new Date().toISOString().slice(0, 7);
  const goalMonth = document.getElementById('goalMonth');
  if (goalMonth && !goalMonth.value) {
    goalMonth.value = currentMonth;
  }
  refreshStatus();
  loadGoalSummary();
  loadNotesList();
  loadWeeklyCashflow();
});
</script>
</body>
</html>
