<?php
/**
 * Historial de Compras del Cliente
 * Vista de pedidos anteriores con opción de reordenamiento
 */

require_once '../config/config.php';
require_login();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$userId = $_SESSION['user_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Compras - Ferretería FOX</title>
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
        
        .history-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #222;
        }
        
        .history-title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(90deg, #fff, #ff7f00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .order-card {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.2s ease;
        }
        
        .order-card:hover {
            border-color: #ff7f00;
            box-shadow: 0 4px 20px rgba(255, 127, 0, 0.1);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #222;
        }
        
        .order-folio {
            font-size: 1.25rem;
            font-weight: 700;
            color: #ff7f00;
        }
        
        .order-date {
            color: #888;
            font-size: 0.9rem;
        }
        
        .order-status {
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
        
        .order-items {
            margin: 1rem 0;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #1a1a1a;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-name {
            flex: 1;
        }
        
        .item-qty {
            color: #888;
            margin: 0 1rem;
        }
        
        .item-price {
            font-weight: 600;
            color: #fff;
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #222;
        }
        
        .order-total {
            font-size: 1.25rem;
            font-weight: 700;
            color: #ff7f00;
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            margin-left: 0.5rem;
        }
        
        .btn-view { background: #3b82f6; color: #fff; }
        .btn-reorder { background: #ff7f00; color: #fff; }
        .btn-track { background: #8b5cf6; color: #fff; }
        
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .filter-bar select, .filter-bar input {
            background: #111;
            border: 1px solid #333;
            color: #fff;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            min-width: 200px;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #888;
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .order-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .btn-action {
                width: 100%;
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px;"></a>
            <nav class="nav-menu">
                <a href="tienda.php">Tienda en Línea</a>
                <a href="order_history.php" class="active">Mis Pedidos</a>
                <a href="profile.php">Perfil</a>
            </nav>
            <div class="user-menu">
                <span><?php echo $user_name; ?></span>
                <button onclick="logout()" class="btn-logout">Cerrar Sesión</button>
            </div>
        </div>
    </header>

    <div class="history-container">
        <div class="history-header">
            <h1 class="history-title">📦 Historial de Compras</h1>
            <a href="tienda.php" class="btn-action btn-reorder">Nueva Compra</a>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <select id="filterStatus" onchange="filterOrders()">
                <option value="">Todos los estados</option>
                <option value="pending">Pendiente</option>
                <option value="confirmed">Confirmado</option>
                <option value="processing">En Proceso</option>
                <option value="shipped">Enviado</option>
                <option value="delivered">Entregado</option>
                <option value="cancelled">Cancelado</option>
            </select>
            <input type="text" id="searchFolio" placeholder="Buscar por folio..." onkeyup="filterOrders()">
            <select id="filterDate" onchange="filterOrders()">
                <option value="">Todas las fechas</option>
                <option value="30">Últimos 30 días</option>
                <option value="90">Últimos 90 días</option>
                <option value="180">Últimos 6 meses</option>
                <option value="365">Último año</option>
            </select>
        </div>

        <!-- Orders List -->
        <div id="ordersList">
            <div style="text-align: center; padding: 2rem;">Cargando historial...</div>
        </div>
    </div>

    <script>
        let allOrders = [];

        async function loadOrders() {
            try {
                const response = await fetch('api/orders.php?action=list_client');
                const data = await response.json();
                
                if (data.success && data.orders) {
                    allOrders = data.orders;
                    renderOrders(allOrders);
                } else {
                    renderEmptyState();
                }
            } catch (error) {
                console.error('Error loading orders:', error);
                renderEmptyState();
            }
        }

        function renderOrders(orders) {
            const container = document.getElementById('ordersList');
            
            if (orders.length === 0) {
                renderEmptyState();
                return;
            }

            container.innerHTML = orders.map(order => `
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-folio">${order.folio || order.order_number}</div>
                            <div class="order-date">${new Date(order.issued_date).toLocaleDateString('es-MX', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric' 
                            })}</div>
                        </div>
                        <span class="order-status status-${order.order_status}">${getStatusLabel(order.order_status)}</span>
                    </div>
                    
                    <div class="order-items" id="items-${order.id}">
                        <div style="text-align: center; padding: 1rem; color: #888;">Cargando items...</div>
                    </div>
                    
                    <div class="order-footer">
                        <div class="order-total">Total: $${parseFloat(order.total_amount).toFixed(2)} MXN</div>
                        <div>
                            <button onclick="viewOrder(${order.id})" class="btn-action btn-view">Ver Detalle</button>
                            <button onclick="trackOrder('${order.folio}')" class="btn-action btn-track">Rastrear</button>
                            ${order.order_status === 'delivered' ? `<button onclick="reorder(${order.id})" class="btn-action btn-reorder">Volver a Pedir</button>` : ''}
                        </div>
                    </div>
                </div>
            `).join('');

            // Cargar items de cada pedido
            orders.forEach(order => loadOrderItems(order.id));
        }

        async function loadOrderItems(orderId) {
            try {
                const response = await fetch(`api/orders.php?action=get&id=${orderId}`);
                const data = await response.json();
                
                const itemsContainer = document.getElementById(`items-${orderId}`);
                if (!itemsContainer) return;
                
                if (data.success && data.order && data.order.items) {
                    const items = JSON.parse(data.order.items);
                    itemsContainer.innerHTML = items.map(item => `
                        <div class="order-item">
                            <div class="item-name">${item.product_name || 'Producto'}</div>
                            <div class="item-qty">x${item.quantity}</div>
                            <div class="item-price">$${parseFloat(item.unit_price).toFixed(2)}</div>
                        </div>
                    `).join('');
                } else {
                    itemsContainer.innerHTML = '<div style="text-align: center; padding: 1rem; color: #888;">No hay items disponibles</div>';
                }
            } catch (error) {
                console.error('Error loading order items:', error);
            }
        }

        function getStatusLabel(status) {
            const labels = {
                'pending': 'Pendiente',
                'confirmed': 'Confirmado',
                'processing': 'En Proceso',
                'shipped': 'Enviado',
                'delivered': 'Entregado',
                'cancelled': 'Cancelado'
            };
            return labels[status] || status;
        }

        function renderEmptyState() {
            const container = document.getElementById('ordersList');
            container.innerHTML = `
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                    <h3>No tienes pedidos aún</h3>
                    <p>Comienza a comprar para ver tu historial aquí</p>
                    <a href="tienda.php" class="btn-action btn-reorder" style="margin-top: 1rem;">Ir a la Tienda</a>
                </div>
            `;
        }

        function filterOrders() {
            const status = document.getElementById('filterStatus').value;
            const search = document.getElementById('searchFolio').value.toLowerCase();
            const dateRange = document.getElementById('filterDate').value;
            
            let filtered = allOrders;
            
            if (status) {
                filtered = filtered.filter(o => o.order_status === status);
            }
            
            if (search) {
                filtered = filtered.filter(o => (o.folio || '').toLowerCase().includes(search));
            }
            
            if (dateRange) {
                const days = parseInt(dateRange);
                const cutoffDate = new Date();
                cutoffDate.setDate(cutoffDate.getDate() - days);
                
                filtered = filtered.filter(o => new Date(o.issued_date) >= cutoffDate);
            }
            
            renderOrders(filtered);
        }

        function viewOrder(orderId) {
            window.open(`order_detail.php?id=${orderId}`, '_blank');
        }

        function trackOrder(folio) {
            window.open(`order_tracking.php?folio=${folio}`, '_blank');
        }

        async function reorder(orderId) {
            if (!confirm('¿Deseas agregar todos los productos de este pedido a tu carrito?')) return;
            
            try {
                const response = await fetch(`api/orders.php?action=reorder&order_id=${orderId}`, {
                    method: 'POST'
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('Productos agregados al carrito');
                    window.location.href = 'cart.php';
                } else {
                    alert('Error al agregar productos: ' + (data.message || 'Error desconocido'));
                }
            } catch (error) {
                console.error('Error reordering:', error);
                alert('Error al procesar la solicitud');
            }
        }

        function logout() {
            window.location.href = 'api/auth.php?action=logout';
        }

        // Load on page load
        document.addEventListener('DOMContentLoaded', loadOrders);
    </script>
</body>
</html>
