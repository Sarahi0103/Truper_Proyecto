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
            <a href="analytics.php">Estadisticas</a>
        </nav>
    </div>
    <div class="user-menu">
        <div class="user-info"><div class="user-name"><?php echo $user_name; ?></div></div>
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
        </div></div>
    </div>
</main>
<script src="js/main.js"></script>
<script>
async function refreshStatus() {
  const res = await apiCall('/cashier.php?action=status');
  const box = document.getElementById('cashierStatus');
  if (!res || !res.success) {
    box.textContent = 'No fue posible consultar estatus';
    return;
  }
  if (!res.open_session) {
    box.textContent = 'Caja cerrada';
    return;
  }
  box.textContent = `Caja abierta desde ${res.open_session.opened_at} con monto inicial ${res.open_session.opening_amount}`;
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
