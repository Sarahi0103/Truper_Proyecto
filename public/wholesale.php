<?php
require_once '../config/config.php';
require_login();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'client', ENT_QUOTES, 'UTF-8');
$is_admin = (($_SESSION['role'] ?? '') === 'admin');
$column_count = $is_admin ? 7 : 5;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mayoreo - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/responsive-complete.css">
</head>
<body>
<header>
    <div class="header-content">
        <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
        <nav class="nav-menu">
            <a href="index.php">Catálogo</a>
            <a href="marketplace_ce.php">Marketplace CE</a>
            <a href="cart.php">Carrito</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="orders.php">Pedidos</a>
            <a href="wholesale.php" class="active">Mayoreo</a>
          <?php if ($is_admin): ?><a href="cashier.php">Caja</a><?php endif; ?>
            <?php if ($is_admin): ?><a href="admin_supply.php">Abastecimiento</a><?php endif; ?>
            <a href="tasks.php">Tareas</a>
            <a href="analytics.php">Estadísticas</a>
            <a href="profile.php">Perfil</a>
        </nav>
    </div>
    <div class="user-menu">
        <div class="user-info">
            <div class="user-name"><?php echo $user_name; ?></div>
            <div class="user-role"><?php echo ucfirst($user_role); ?></div>
        </div>
        <button class="btn-logout" onclick="window.location.href='api/auth.php?action=logout'">Cerrar Sesion</button>
    </div>
</header>

<main>
    <div class="container">
        <h1>Solicitud de Mayoreo</h1>
        <p class="text-muted">Solicita condiciones comerciales de volumen para tu negocio.</p>

        <form id="wholesaleForm" class="mt-3" onsubmit="submitWholesale(event)">
            <div class="form-group">
                <label>Tipo de negocio</label>
                <input type="text" id="businessType" required>
            </div>
            <div class="form-group">
                <label>Pedido minimo estimado</label>
                <input type="number" id="minOrder" min="1" value="50" required>
            </div>
            <div class="form-group">
                <label>Descuento solicitado (%)</label>
                <input type="number" id="discountPct" min="1" max="40" value="15" required>
            </div>
            <div class="form-group">
                <label>Terminos de pago</label>
                <input type="text" id="paymentTerms" value="Contado" required>
            </div>
            <button class="btn btn-primary" type="submit">Enviar solicitud</button>
        </form>

        <div class="mt-4">
            <h2><?php echo $is_admin ? 'Solicitudes recibidas' : 'Mis solicitudes'; ?></h2>
            <table>
                <thead>
                <tr>
                    <?php if ($is_admin): ?><th>Cliente</th><?php endif; ?>
                    <th>Negocio</th><th>Minimo</th><th>Descuento</th><th>Terminos</th><th>Estatus</th>
                    <?php if ($is_admin): ?><th>Accion</th><?php endif; ?>
                </tr>
                </thead>
                <tbody id="wholesaleRows"><tr><td colspan="<?php echo $column_count; ?>">Cargando...</td></tr></tbody>
            </table>
        </div>
    </div>
</main>

<script src="js/main.js"></script>
<script>
async function submitWholesale(e) {
  e.preventDefault();
  const payload = {
    business_type: document.getElementById('businessType').value,
    min_order_quantity: document.getElementById('minOrder').value,
    discount_percentage: document.getElementById('discountPct').value,
    payment_terms: document.getElementById('paymentTerms').value
  };
  const res = await apiCall('/wholesale.php?action=request', 'POST', payload);
  if (res && res.success) {
    handleSuccessResponse(res, {
      scrollTarget: '#wholesaleForm',
      successMessage: res.message || 'Solicitud enviada correctamente',
      onSuccess: () => loadWholesale()
    });
  } else if (res) {
    showAlert(res.message, 'error');
  }
}

async function approveWholesale(id) {
  const res = await apiCall('/wholesale.php?action=approve', 'POST', { id });
  if (res && res.success) {
    handleSuccessResponse(res, {
      scrollTarget: '#wholesaleRows',
      successMessage: res.message || 'Solicitud aprobada',
      onSuccess: () => loadWholesale()
    });
  } else if (res) {
    showAlert(res.message, 'error');
  }
}

async function loadWholesale() {
  const res = await apiCall('/wholesale.php?action=list');
  const tb = document.getElementById('wholesaleRows');
  const colCount = <?php echo $column_count; ?>;
  if (!res || !res.success || !Array.isArray(res.items) || res.items.length === 0) {
    tb.innerHTML = `<tr><td colspan="${colCount}">Sin registros</td></tr>`;
    return;
  }

  const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
  tb.innerHTML = res.items.map(i => `
    <tr>
      ${isAdmin ? `<td>${i.first_name || ''} ${i.last_name || ''}</td>` : ''}
      <td>${i.business_type || ''}</td>
      <td>${i.min_order_quantity || ''}</td>
      <td>${i.discount_percentage || ''}%</td>
      <td>${i.payment_terms || ''}</td>
      <td>${i.is_approved ? 'Aprobado' : 'Pendiente'}</td>
      ${isAdmin ? `<td>${i.is_approved ? '-' : `<button class='btn btn-small btn-success' onclick='approveWholesale(${i.id})'>Aprobar</button>`}</td>` : ''}
    </tr>
  `).join('');
}

document.addEventListener('DOMContentLoaded', loadWholesale);
</script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
