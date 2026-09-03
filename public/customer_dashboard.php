<?php
/**
 * Dashboard del Cliente
 * Historial de pedidos, direcciones, métodos de pago, puntos de lealtad
 */

require_once '../config/config.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');

// Obtener datos del cliente
$stmt = $pdo->prepare("
    SELECT u.*, c.company_name, c.rfc, c.email, c.phone
    FROM users u
    LEFT JOIN clients c ON u.client_id = c.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener estadísticas del cliente
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(o.total_amount), 0) as total_spent,
        COALESCE(AVG(o.total_amount), 0) as avg_order_value
    FROM orders o
    WHERE o.user_id = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener pedidos recientes
$stmt = $pdo->prepare("
    SELECT o.*, st.folio, st.order_status
    FROM orders o
    LEFT JOIN sales_tickets st ON o.id = st.order_id
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Dashboard - Ferretería FOX</title>
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
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #222;
        }
        
        .dashboard-title {
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
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #ff7f00, #ffaa00);
        }
        
        .stat-label {
            color: #888;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
        }
        
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #222;
            padding-bottom: 0.5rem;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            color: #888;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .tab:hover {
            color: #fff;
            background: #1a1a1a;
        }
        
        .tab.active {
            color: #fff;
            background: #ff7f00;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
            font-size: 0.85rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .orders-table td {
            padding: 1rem;
            border-bottom: 1px solid #222;
        }
        
        .orders-table tr:hover {
            background: #151515;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-pending { background: #f59e0b; color: #000; }
        .status-confirmed { background: #3b82f6; color: #fff; }
        .status-processing { background: #8b5cf6; color: #fff; }
        .status-shipped { background: #06b6d4; color: #fff; }
        .status-delivered { background: #22c55e; color: #fff; }
        .status-cancelled { background: #ef4444; color: #fff; }
        
        .action-btn {
            padding: 0.5rem 1rem;
            background: #222;
            border: 1px solid #333;
            color: #fff;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: #333;
            border-color: #444;
        }
        
        .action-btn.primary {
            background: #ff7f00;
            border-color: #ff7f00;
        }
        
        .action-btn.primary:hover {
            background: #ff9900;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <a href="tienda.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px;"></a>
            <nav class="nav-menu">
                <a href="tienda.php">Tienda en Línea</a>
                <a href="customer_dashboard.php" class="active">Mi Dashboard</a>
                <a href="order_tracking.php">Seguimiento de Pedidos</a>
                <a href="wishlist.php">Favoritos</a>
            </nav>
            <div class="user-menu">
                <span><?php echo $user_name; ?></span>
                <button onclick="logout()" class="btn-logout">Cerrar Sesión</button>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">👤 Mi Dashboard</h1>
            <div style="color: #888;">Bienvenido, <?php echo $user_name; ?></div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Pedidos Totales</div>
                <div class="stat-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Gastado</div>
                <div class="stat-value">$<?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Ticket Promedio</div>
                <div class="stat-value">$<?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('orders')">Mis Pedidos</button>
            <button class="tab" onclick="switchTab('profile')">Mi Perfil</button>
            <button class="tab" onclick="switchTab('addresses')">Direcciones</button>
            <button class="tab" onclick="switchTab('wishlist')">Favoritos</button>
        </div>

        <!-- Orders Tab -->
        <div id="orders-tab" class="tab-content active">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td style="font-family: monospace; color: #ff7f00;">
                            <?php echo htmlspecialchars($order['folio'] ?? 'N/A'); ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td>
                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($order['order_status'] ?? 'pending'); ?>">
                                <?php echo htmlspecialchars($order['order_status'] ?? 'Pendiente'); ?>
                            </span>
                        </td>
                        <td>
                            <a href="order_tracking.php?folio=<?php echo urlencode($order['folio'] ?? ''); ?>" class="action-btn primary">Ver</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentOrders)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem; color: #888;">
                            No tienes pedidos aún
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Profile Tab -->
        <div id="profile-tab" class="tab-content">
            <div style="background: #111; border: 1px solid #222; border-radius: 12px; padding: 2rem;">
                <h2 style="margin-top: 0;">Información del Perfil</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: #888;">Nombre</label>
                        <input type="text" value="<?php echo htmlspecialchars($user_name); ?>" readonly 
                               style="width: 100%; background: #1a1a1a; border: 1px solid #333; color: #fff; padding: 0.75rem; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: #888;">Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly
                               style="width: 100%; background: #1a1a1a; border: 1px solid #333; color: #fff; padding: 0.75rem; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: #888;">Teléfono</label>
                        <input type="tel" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" readonly
                               style="width: 100%; background: #1a1a1a; border: 1px solid #333; color: #fff; padding: 0.75rem; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: #888;">RFC</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['rfc'] ?? ''); ?>" readonly
                               style="width: 100%; background: #1a1a1a; border: 1px solid #333; color: #fff; padding: 0.75rem; border-radius: 6px;">
                    </div>
                </div>
                <div style="margin-top: 1.5rem;">
                    <button class="action-btn primary" onclick="alert('Función de edición de perfil próximamente disponible')">
                        Editar Perfil
                    </button>
                </div>
            </div>
        </div>

        <!-- Addresses Tab -->
        <div id="addresses-tab" class="tab-content">
            <div style="background: #111; border: 1px solid #222; border-radius: 12px; padding: 2rem;">
                <h2 style="margin-top: 0;">Mis Direcciones</h2>
                <p style="color: #888;">Función de direcciones próximamente disponible</p>
            </div>
        </div>

        <!-- Wishlist Tab -->
        <div id="wishlist-tab" class="tab-content">
            <div style="background: #111; border: 1px solid #222; border-radius: 12px; padding: 2rem;">
                <h2 style="margin-top: 0;">Mis Favoritos</h2>
                <p style="color: #888;">Cargando wishlist...</p>
            </div>
        </div>
    </div>

    <script src="js/main.js?v=2.6"></script>
    <script>
        function switchTab(tabName) {
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Add active class to selected tab
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Load wishlist if selected
            if (tabName === 'wishlist') {
                loadWishlist();
            }
        }
        
        async function loadWishlist() {
            try {
                const response = await fetch('api/wishlist.php?action=list');
                const data = await response.json();
                
                if (data.success && data.wishlist) {
                    const wishlistHtml = data.wishlist.map(item => `
                        <div style="display: flex; gap: 1rem; padding: 1rem; border-bottom: 1px solid #222; align-items: center;">
                            <img src="${item.product_image || '/img/no-image.png'}" alt="${item.product_name}" 
                                 style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                            <div style="flex: 1;">
                                <div style="font-weight: 700; margin-bottom: 0.25rem;">${item.product_name}</div>
                                <div style="color: #888; font-size: 0.85rem;">SKU: ${item.product_sku}</div>
                                <div style="color: #ff7f00; font-weight: 700; margin-top: 0.25rem;">$${parseFloat(item.product_price).toFixed(2)}</div>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button onclick="addToCart(${item.product_id})" class="action-btn primary">Agregar al Carrito</button>
                                <button onclick="removeFromWishlist(${item.product_id})" class="action-btn">Eliminar</button>
                            </div>
                        </div>
                    `).join('');
                    
                    document.querySelector('#wishlist-tab div').innerHTML = `
                        <h2 style="margin-top: 0;">Mis Favoritos (${data.wishlist.length})</h2>
                        ${data.wishlist.length > 0 ? wishlistHtml : '<p style="color: #888;">No tienes productos en favoritos</p>'}
                    `;
                }
            } catch (error) {
                console.error('Error loading wishlist:', error);
            }
        }
        
        async function addToCart(productId) {
            // Implementar lógica de agregar al carrito
            alert('Producto agregado al carrito');
        }
        
        async function removeFromWishlist(productId) {
            if (!confirm('¿Eliminar este producto de favoritos?')) return;
            
            try {
                const response = await fetch('api/wishlist.php?action=remove', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId })
                });
                const data = await response.json();
                
                if (data.success) {
                    loadWishlist();
                }
            } catch (error) {
                console.error('Error removing from wishlist:', error);
            }
        }
        
        function logout() {
            window.location.href = 'api/auth.php?action=logout';
        }
    </script>
</body>
</html>
