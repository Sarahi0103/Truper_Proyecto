<?php
require_once '../config/config.php';

$isLogged = isset($_SESSION['user_id']);
$isAdmin = $isLogged && (($_SESSION['role'] ?? '') === 'admin');
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$order = null;
$orderItems = null;

if ($orderId && $isLogged) {
    // Get order details
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND client_id = ?");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        // Get order items
        $itemStmt = $pdo->prepare("
            SELECT oi.*, p.sku, p.name FROM order_items oi 
            LEFT JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $itemStmt->execute([$orderId]);
        $orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $order ? 'Orden ' . $order['order_number'] : 'Confirmación'; ?> - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .confirmation-page { padding: 2rem 1rem; }
        .confirmation-container { max-width: 800px; margin: 0 auto; }
        .success-badge {
            text-align: center;
            margin-bottom: 2rem;
        }
        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: bounce 0.6s;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        .success-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #10B981;
            margin-bottom: 0.5rem;
        }
        .success-subtitle {
            font-size: 1rem;
            color: var(--theme-text-muted);
            margin-bottom: 1rem;
        }

        .confirmation-section {
            background: var(--theme-surface);
            border: 1px solid var(--theme-border);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: var(--theme-text);
            border-bottom: 2px solid var(--theme-accent);
            padding-bottom: 0.5rem;
        }

        .info-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
            color: var(--theme-text);
        }

        .info-label {
            font-weight: 600;
            color: var(--theme-text-muted);
        }

        .info-value {
            color: var(--theme-text);
        }

        .order-item {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--theme-border);
            align-items: center;
        }

        .order-item:last-child { border-bottom: none; }

        .item-details {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .item-name {
            font-weight: 600;
            color: var(--theme-text);
        }

        .item-meta {
            font-size: 0.9rem;
            color: var(--theme-text-muted);
        }

        .item-price {
            text-align: right;
            font-weight: 600;
            color: var(--theme-accent);
        }

        .totals {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid var(--theme-border);
        }

        .total-row {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 1rem;
        }

        .total-label {
            font-weight: 600;
            color: var(--theme-text);
        }

        .total-amount {
            text-align: right;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--theme-accent);
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-pending { background: #FEF08A; color: #78350F; }
        .status-confirmed { background: #BFDBFE; color: #1E40AF; }
        .status-shipped { background: #C7D2FE; color: #3730A3; }
        .status-delivered { background: #BBEAD5; color: #065F46; }

        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .alert-info {
            background: #E0F2FE;
            border-left: 4px solid #0284C7;
            padding: 1rem;
            border-radius: 4px;
            color: #082F49;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .info-row { grid-template-columns: 1fr; }
            .actions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body data-theme="light">
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
                        <a href="orders.php" class="active">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <?php if ($is_admin): ?>
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
            <div class="header-actions">

            </div>
        </div>
    </header>

    <main class="confirmation-page">
        <div class="confirmation-container">
            <?php if ($order && $orderItems): ?>
                <!-- Success Message -->
                <div class="success-badge">
                    <div class="success-icon">✅</div>
                    <div class="success-title">¡Pedido Confirmado!</div>
                    <div class="success-subtitle">Tu pedido ha sido registrado exitosamente</div>
                </div>

                <!-- Important Info Alert -->
                <div class="alert-info">
                    <strong>📧 Confirmación enviada:</strong> Hemos enviado un email de confirmación a tu cuenta. Revisa tu bandeja de entrada y spam si no lo encuentras.
                </div>

                <!-- Order Information -->
                <div class="confirmation-section">
                    <div class="section-title">📋 Información del Pedido</div>
                    
                    <div class="info-row">
                        <div class="info-label">Número de Orden:</div>
                        <div class="info-value"><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Estado:</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                <?php 
                                $statusLabels = [
                                    'pending' => 'Pendiente',
                                    'confirmed' => 'Confirmado',
                                    'shipped' => 'Enviado',
                                    'delivered' => 'Entregado'
                                ];
                                echo $statusLabels[$order['status']] ?? ucfirst($order['status']);
                                ?>
                            </span>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Fecha de Orden:</div>
                        <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Fecha Estimada de Entrega:</div>
                        <div class="info-value"><strong><?php echo date('d/m/Y', strtotime($order['delivery_date'])); ?></strong></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Total:</div>
                        <div class="info-value"><strong style="color: var(--theme-accent); font-size: 1.2rem;">$<?php echo number_format($order['total_amount'], 2); ?></strong></div>
                    </div>
                </div>

                <!-- Items Summary -->
                <div class="confirmation-section">
                    <div class="section-title">🛍️ Artículos del Pedido</div>
                    
                    <?php foreach ($orderItems as $item): ?>
                        <div class="order-item">
                            <div class="item-details">
                                <div class="item-name"><?php echo htmlspecialchars($item['name'] ?? 'Producto'); ?></div>
                                <div class="item-meta">SKU: <?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?> | Cantidad: <?php echo intval($item['quantity']); ?></div>
                            </div>
                            <div class="item-price">$<?php echo number_format($item['subtotal'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>

                    <div class="totals">
                        <div class="total-row">
                            <div class="total-label">Subtotal:</div>
                            <div class="total-amount">$<?php echo number_format($order['total_amount'] - 0, 2); ?></div>
                        </div>
                        <div class="total-row">
                            <div class="total-label">Total:</div>
                            <div class="total-amount">$<?php echo number_format($order['total_amount'], 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Instructions -->
                <?php if (!empty($order['notes'])): ?>
                    <div class="confirmation-section">
                        <div class="section-title">📦 Instrucciones de Entrega</div>
                        <div style="white-space: pre-line; color: var(--theme-text);">
                            <?php echo htmlspecialchars($order['notes']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Next Steps -->
                <div class="confirmation-section">
                    <div class="section-title">📌 Próximos Pasos</div>
                    <ol style="color: var(--theme-text); margin-left: 1.5rem; line-height: 1.8;">
                        <li>Recibirás un email de confirmación en breve</li>
                        <li>Tu pedido será procesado y preparado para envío</li>
                        <li>Recibirás notificación cuando sea enviado</li>
                        <li>Entrega estimada el <?php echo date('d/m/Y', strtotime($order['delivery_date'])); ?></li>
                        <li>Puedes rastrear tu orden en tu perfil</li>
                    </ol>
                </div>

                <!-- Support Info -->
                <div class="confirmation-section" style="background: var(--theme-surface-soft); border-color: var(--theme-border);">
                    <div style="text-align: center; color: var(--theme-text-muted);">
                        <p style="margin-bottom: 1rem;">¿Tienes dudas sobre tu pedido?</p>
                        <a href="https://wa.me/5216144532323?text=Hola%2C+tengo+una+pregunta+sobre+mi+pedido+<?php echo $order['order_number']; ?>" target="_blank" class="btn btn-secondary">💬 Contactar por WhatsApp</a>
                    </div>
                </div>

                <!-- Actions -->
                <div class="actions">
                    <a href="orders.php" class="btn btn-primary" style="text-align: center;">👁️ Ver Mis Pedidos</a>
                    <a href="index.php" class="btn btn-secondary" style="text-align: center;">🛍️ Seguir Comprando</a>
                </div>

            <?php else: ?>
                <div class="confirmation-section" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">⚠️</div>
                    <div class="success-title" style="color: #EF4444;">Error: Pedido no encontrado</div>
                    <div class="success-subtitle">No pudimos encontrar tu pedido. Por favor intenta de nuevo.</div>
                    <div style="margin-top: 2rem;">
                        <a href="orders.php" class="btn btn-primary">← Volver a Pedidos</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer style="margin-top: 3rem; padding: 2rem; text-align: center; border-top: 1px solid var(--theme-border); color: var(--theme-text-muted);">
        <p>&copy; 2026 Truper Platform</p>
    </footer>

    <script src="js/main.js"></script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
