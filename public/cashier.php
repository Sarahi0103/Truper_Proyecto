<?php
require_once '../config/config.php';
require_admin();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cajon de Dinero - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
      .cash-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; }
      .metric { border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px; background: #fff; }
      .metric-label { font-size: 12px; color: #6b7280; }
      .metric-value { font-size: 18px; font-weight: 700; color: #111827; }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
        <nav class="nav-menu">
            <a href="index.php">Productos</a>
            <a href="orders.php">Pedidos</a>
            <a href="wholesale.php">Mayoreo</a>
            <a href="cashier.php" class="active">Caja</a>
          <a href="admin_supply.php">Abastecimiento</a>
            <a href="analytics.php">Estadisticas</a>
          <a href="profile.php">Perfil</a>
        </nav>
    </div>
    <div class="user-menu">
        <div class="user-info"><div class="user-name"><?php echo $user_name; ?></div></div>
      <a href="index.php" class="btn btn-small btn-ghost">Ver portada</a>
        <button class="btn-logout" onclick="window.location.href='api/auth.php?action=logout'">Cerrar Sesion</button>
    </div>
</header>
<main>
    <div class="container">
        <h1>Control de Cajon de Dinero</h1>
        <p class="text-muted">Apertura, movimientos y cierre diario para evitar perdidas.</p>

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

        <div class="card mt-3"><div class="card-body">
            <h3>Estatus actual</h3>
            <div id="cashierStatus" class="text-muted">Consultando...</div>
            <div id="cashierMetrics" class="cash-metrics mt-2"></div>
        </div></div>
    </div>
</main>
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
    { label: 'Ganancia real estimada', value: formatMoney(summary.real_profit) },
    { label: 'Margen de ganancia', value: `${Number(summary.profit_margin_pct || 0).toFixed(2)}%` }
  ].map((m) => `<div class="metric"><div class="metric-label">${m.label}</div><div class="metric-value">${m.value}</div></div>`).join('');
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

document.addEventListener('DOMContentLoaded', refreshStatus);
</script>
</body>
</html>
