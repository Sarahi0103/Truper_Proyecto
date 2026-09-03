<?php
/**
 * Panel de Administración de Pedidos en Línea
 * Gestión separada de pedidos online vs locales
 */

require_once '../config/config.php';
require_login();

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'employee'])) {
    header('Location: index.php');
    exit;
}

$is_admin = $userRole === 'admin';
$is_staff = $is_admin || $userRole === 'employee';
$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Pedidos en Línea - Ferretería FOX</title>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <link rel="stylesheet" href="css/styles.css?v=4.1">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        body {
            background: #08080a;
            color: #fff;
            margin: 0;
            padding: 0;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #222;
        }
        
        .admin-title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(90deg, #fff, #ff7f00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #ff7f00;
            margin: 0.5rem 0;
        }
        
        .stat-label {
            color: #888;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            background: #111;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .orders-table th {
            background: #1a1a1a;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #fff;
            border-bottom: 2px solid #ff7f00;
        }
        
        .orders-table td {
            padding: 1rem;
            border-bottom: 1px solid #222;
        }
        
        .orders-table tr:hover {
            background: #1a1a1a;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-pending { background: #ff9f43; color: #fff; }
        .status-confirmed { background: #22c55e; color: #fff; }
        .status-processing { background: #3b82f6; color: #fff; }
        .status-shipped { background: #8b5cf6; color: #fff; }
        .status-delivered { background: #10b981; color: #fff; }
        .status-cancelled { background: #ef4444; color: #fff; }
        
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view { background: #3b82f6; color: #fff; }
        .btn-print { background: #8b5cf6; color: #fff; }
        .btn-edit { background: #ff7f00; color: #fff; }
        
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .filter-bar input, .filter-bar select {
            background: #111;
            border: 1px solid #333;
            color: #fff;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            min-width: 200px;
        }
        
        .tabs-header {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #222;
            padding-bottom: 0.5rem;
        }
        
        .tab-btn {
            background: transparent;
            border: none;
            color: #888;
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .tab-btn:hover {
            background: #1a1a1a;
            color: #fff;
        }
        
        .tab-btn.active {
            background: #ff7f00;
            color: #fff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px;"></a>
            <nav class="nav-menu">
                <a href="tienda.php">Tienda en Línea</a>
                <a href="admin_online_orders.php" class="active">Pedidos Online</a>
                <a href="orders.php">Pedidos Local</a>
                <a href="admin_online_billing.php">Facturación SAT</a>
                <a href="rma_manager.php">Devoluciones RMA</a>
            </nav>
            <div class="user-menu">
                <span><?php echo $user_name; ?></span>
                <button onclick="logout()" class="btn-logout">Cerrar Sesión</button>
            </div>
        </div>
    </header>

    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title">📦 Pedidos en Línea</h1>
            <button onclick="refreshOrders()" class="btn-action btn-edit">🔄 Actualizar</button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Pedidos Hoy</div>
                <div class="stat-value" id="todayCount">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pendientes</div>
                <div class="stat-value" id="pendingCount">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Ventas del Mes</div>
                <div class="stat-value" id="monthSales">$0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Por Entregar</div>
                <div class="stat-value" id="toDeliver">0</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab('all')">Todos</button>
            <button class="tab-btn" onclick="switchTab('pending')">Pendientes</button>
            <button class="tab-btn" onclick="switchTab('processing')">En Proceso</button>
            <button class="tab-btn" onclick="switchTab('shipped')">Enviados</button>
            <button class="tab-btn" onclick="switchTab('delivered')">Entregados</button>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <input type="text" id="searchFolio" placeholder="Buscar por folio..." onkeyup="filterOrders()">
            <select id="filterStatus" onchange="filterOrders()">
                <option value="">Todos los estados</option>
                <option value="pending">Pendiente</option>
                <option value="confirmed">Confirmado</option>
                <option value="processing">En Proceso</option>
                <option value="shipped">Enviado</option>
                <option value="delivered">Entregado</option>
                <option value="cancelled">Cancelado</option>
            </select>
            <input type="date" id="filterDate" onchange="filterOrders()">
        </div>

        <!-- Orders Table -->
        <div class="tab-content active" id="allTab">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Pago</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <tr><td colspan="7" style="text-align:center; padding:2rem;">Cargando pedidos...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        let currentOrders = [];
        let currentFilter = 'all';

        async function loadOrders() {
            try {
                const response = await fetch('api/admin_online_orders_api.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    currentOrders = data.orders || [];
                    renderOrders(currentOrders);
                    updateStats(data.stats || {});
                }
            } catch (error) {
                console.error('Error loading orders:', error);
            }
        }

        function renderOrders(orders) {
            const tbody = document.getElementById('ordersTableBody');
            
            if (orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:2rem;">No hay pedidos</td></tr>';
                return;
            }

            tbody.innerHTML = orders.map(order => `
                <tr>
                    <td><strong style="color:#ff7f00;">${order.folio}</strong></td>
                    <td>${order.customer_name || 'N/A'}</td>
                    <td>${new Date(order.issued_date).toLocaleDateString('es-MX')}</td>
                    <td>$${parseFloat(order.total_amount).toFixed(2)}</td>
                    <td><span class="status-badge status-${order.order_status}">${order.order_status}</span></td>
                    <td><span class="status-badge" style="background:#333; color:#fff;">${order.payment_status}</span></td>
                    <td>
                        <button onclick="viewOrder(${order.id})" class="btn-action btn-view">Ver</button>
                        <button onclick="printLabel('${order.folio}')" class="btn-action btn-print">Etiqueta</button>
                        ${is_admin ? `<button onclick="addTracking(${order.id})" class="btn-action btn-edit">Tracking</button>` : ''}
                        ${is_admin ? `<button onclick="updateStatus(${order.id})" class="btn-action btn-edit">Estado</button>` : ''}
                    </td>
                </tr>
            `).join('');
        }

        function updateStats(stats) {
            document.getElementById('todayCount').textContent = stats.today_count || 0;
            document.getElementById('pendingCount').textContent = stats.pending_count || 0;
            document.getElementById('monthSales').textContent = '$' + (stats.month_sales || 0).toFixed(2);
            document.getElementById('toDeliver').textContent = stats.to_deliver || 0;
        }

        function switchTab(tab) {
            currentFilter = tab;
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            let filtered = currentOrders;
            if (tab !== 'all') {
                filtered = currentOrders.filter(o => o.order_status === tab);
            }
            renderOrders(filtered);
        }

        function filterOrders() {
            const search = document.getElementById('searchFolio').value.toLowerCase();
            const status = document.getElementById('filterStatus').value;
            const date = document.getElementById('filterDate').value;
            
            let filtered = currentOrders;
            
            if (search) {
                filtered = filtered.filter(o => o.folio.toLowerCase().includes(search));
            }
            
            if (status) {
                filtered = filtered.filter(o => o.order_status === status);
            }
            
            if (date) {
                filtered = filtered.filter(o => o.issued_date.startsWith(date));
            }
            
            renderOrders(filtered);
        }

        function refreshOrders() {
            loadOrders();
        }

        function viewOrder(orderId) {
            window.open(`order_detail.php?id=${orderId}`, '_blank');
        }

        function printLabel(folio) {
            window.open(`api/print_blind_label.php?folio=${folio}`, '_blank');
        }

        function updateStatus(orderId) {
            showPrompt(
                'Actualizar Estatus de Pedido',
                'Ingresa el nuevo estatus (pending, confirmed, processing, shipped, delivered, cancelled):',
                'processing',
                (newStatus) => {
                    if (!newStatus || !newStatus.trim()) return;
                    fetch('api/admin_online_orders_api.php?action=update_status', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ order_id: orderId, status: newStatus.trim() })
                    }).then(() => {
                        showAlert('Estatus actualizado correctamente', 'success');
                        loadOrders();
                    });
                }
            );
        }

        function addTracking(orderId) {
            document.getElementById('trackingOrderId').value = orderId;
            document.getElementById('trackingModal').style.display = 'flex';
        }

        function closeTrackingModal() {
            document.getElementById('trackingModal').style.display = 'none';
        }

        async function submitTracking() {
            const orderId = document.getElementById('trackingOrderId').value;
            const carrier = document.getElementById('trackingCarrier').value;
            const trackingNumber = document.getElementById('trackingNumber').value;
            const estimatedDelivery = document.getElementById('trackingEstimatedDelivery').value;

            if (!carrier || !trackingNumber) {
                showAlert('Por favor completa la paquetería y número de tracking', 'warning');
                return;
            }

            try {
                const response = await fetch('api/shipping_tracking.php?action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        order_id: orderId,
                        carrier: carrier,
                        tracking_number: trackingNumber,
                        estimated_delivery: estimatedDelivery || null
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Tracking agregado correctamente', 'success');
                    closeTrackingModal();
                    loadOrders();
                } else {
                    showAlert('Error: ' + (result.message || 'No se pudo agregar el tracking'), 'error');
                }
            } catch (error) {
                showAlert('Error de conexión', 'error');
            }
        }

        function logout() {
            window.location.href = 'api/auth.php?action=logout';
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadOrders();
        });
    </script>

    <!-- Modal de Tracking -->
    <div id="trackingModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); z-index:9999; justify-content:center; align-items:center; padding:1rem;">
        <div style="background:#14141a; border:1px solid #333342; border-radius:14px; width:100%; max-width:500px; padding:1.75rem; box-shadow:0 20px 50px rgba(0,0,0,0.6);">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #282834; padding-bottom:1rem; margin-bottom:1.5rem;">
                <h3 style="margin:0; color:#ff7f00; font-weight:800; font-size:1.15rem;">📦 Agregar Tracking de Envío</h3>
                <button onclick="closeTrackingModal()" style="background:none; border:none; color:#aaa; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            
            <input type="hidden" id="trackingOrderId">
            
            <div style="margin-bottom:1rem;">
                <label style="display:block; margin-bottom:0.4rem; font-size:0.82rem; font-weight:700; color:#aaaab8; text-transform:uppercase;">Paquetería</label>
                <select id="trackingCarrier" style="width:100%; background:#181820; border:1px solid #2e2e3a; color:#fff; padding:0.75rem 1rem; border-radius:8px; font-size:0.92rem; outline:none;">
                    <option value="">Seleccionar paquetería...</option>
                    <option value="FedEx">FedEx</option>
                    <option value="DHL">DHL</option>
                    <option value="Estafeta">Estafeta</option>
                    <option value="Redpack">Redpack</option>
                    <option value="UPS">UPS</option>
                    <option value="Propia">Envío Propio</option>
                </select>
            </div>
            
            <div style="margin-bottom:1rem;">
                <label style="display:block; margin-bottom:0.4rem; font-size:0.82rem; font-weight:700; color:#aaaab8; text-transform:uppercase;">Número de Guía</label>
                <input type="text" id="trackingNumber" placeholder="Ej: 1234567890" style="width:100%; background:#181820; border:1px solid #2e2e3a; color:#fff; padding:0.75rem 1rem; border-radius:8px; font-size:0.92rem; outline:none;">
            </div>
            
            <div style="margin-bottom:1.5rem;">
                <label style="display:block; margin-bottom:0.4rem; font-size:0.82rem; font-weight:700; color:#aaaab8; text-transform:uppercase;">Fecha Estimada de Entrega (Opcional)</label>
                <input type="date" id="trackingEstimatedDelivery" style="width:100%; background:#181820; border:1px solid #2e2e3a; color:#fff; padding:0.75rem 1rem; border-radius:8px; font-size:0.92rem; outline:none;">
            </div>
            
            <button onclick="submitTracking()" style="width:100%; background:#ff7f00; color:#fff; border:none; padding:12px; border-radius:8px; font-weight:700; font-size:0.92rem; cursor:pointer; transition:background 0.2s;">
                Agregar Tracking y Marcar como Enviado
            </button>
        </div>
    </div>
</body>
</html>
