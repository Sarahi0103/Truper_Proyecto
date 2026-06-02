<?php
require_once '../config/config.php';

$isLogged = isset($_SESSION['user_id']);
$isAdmin = $isLogged && (($_SESSION['role'] ?? '') === 'admin');
$is_admin = $isAdmin;
$user = null;

if ($isLogged) {
    $stmt = $pdo->prepare("SELECT id, email, phone, first_name, last_name, address FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Checkout - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css?v=2.2">
    <link rel="stylesheet" href="css/theme.css?v=2.2">
    <link rel="stylesheet" href="css/responsive-complete.css?v=2.2">
    <style>
        .checkout-page { padding: 2rem 1rem; }
        .checkout-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .checkout-title { margin: 0; font-size: 2rem; color: var(--theme-text); }
        .checkout-back { display: inline-flex; align-items: center; gap: 0.5rem; color: var(--theme-accent); text-decoration: none; font-weight: 500; }
        .checkout-back:hover { text-decoration: underline; }

        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
            max-width: 1200px;
        }

        .checkout-form-section {
            background: var(--theme-surface);
            border: 1px solid var(--theme-border);
            border-radius: 8px;
            padding: 1.5rem;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--theme-border);
        }

        .form-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .form-section-title {
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            color: var(--theme-text);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--theme-text);
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--theme-border);
            border-radius: 4px;
            font-size: 1rem;
            background: var(--theme-surface-strong);
            color: var(--theme-text);
        }

        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--theme-accent);
            box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
        }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .checkout-summary {
            background: var(--theme-surface);
            border: 1px solid var(--theme-border);
            border-radius: 8px;
            padding: 1.5rem;
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        .summary-title { font-weight: 700; margin-bottom: 1rem; color: var(--theme-text); }

        .summary-items {
            border-bottom: 1px solid var(--theme-border);
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            max-height: 300px;
            overflow-y: auto;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: var(--theme-text-muted);
        }

        .summary-item-name { flex: 1; }
        .summary-item-price { text-align: right; font-weight: 600; color: var(--theme-text); }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: var(--theme-text);
        }

        .summary-total {
            border-top: 2px solid var(--theme-border);
            padding-top: 1rem;
            margin-top: 1rem;
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--theme-accent);
        }

        .summary-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .btn-full { width: 100%; }

        .error-message {
            background: #FEE2E2;
            border: 1px solid #FECACA;
            color: #991B1B;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .success-message {
            background: #DCFCE7;
            border: 1px solid #BBF7D0;
            color: #15803D;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .auth-prompt {
            background: var(--theme-surface-soft);
            border: 1px solid var(--theme-border);
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .auth-prompt-text {
            color: var(--theme-text-muted);
            margin-bottom: 0.75rem;
        }

        .auth-buttons {
            display: flex;
            gap: 0.75rem;
        }

        @media (max-width: 768px) {
            .checkout-container { grid-template-columns: 1fr; }
            .checkout-summary { position: static; }
            .form-row { grid-template-columns: 1fr; }
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
                        <a href="orders.php">Pedidos</a>
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

                <?php if (!$isLogged): ?>
                    <a href="login.php" class="btn btn-primary btn-small">Ingresar</a>
                <?php else: ?>
                    <span style="color: var(--theme-text); margin-right: 1rem;">Hola, <?php echo htmlspecialchars($user['first_name'] ?? 'Usuario'); ?></span>
                    <a href="api/auth.php?action=logout" class="btn btn-secondary btn-small">Cerrar Sesión</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="checkout-page">
        <div class="checkout-header">
            <h1 class="checkout-title">💳 Checkout</h1>
            <a href="cart.php" class="checkout-back">← Volver al Carrito</a>
        </div>

        <?php if (!$isLogged): ?>
            <div class="auth-prompt">
                <div class="auth-prompt-text">🔒 Necesitas una cuenta para completar tu pedido</div>
                <div class="auth-buttons">
                    <a href="login.php?return_to=checkout.php" class="btn btn-primary" style="flex: 1; text-align: center;">Iniciar Sesión</a>
                    <a href="register.php?return_to=checkout.php" class="btn btn-secondary" style="flex: 1; text-align: center;">Registrarse</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="checkout-container">
            <div class="checkout-form-section">
                <form id="checkoutForm">
                    <!-- Contact Information -->
                    <div class="form-section">
                        <div class="form-section-title">👤 Información de Contacto</div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName">Nombre *</label>
                                <input type="text" id="firstName" name="firstName" required value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="lastName">Apellido *</label>
                                <input type="text" id="lastName" name="lastName" required value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Teléfono *</label>
                                <input type="tel" id="phone" name="phone" required value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Address -->
                    <div class="form-section">
                        <div class="form-section-title">📦 Dirección de Entrega</div>
                        
                        <div class="form-group">
                            <label for="address">Calle y Número *</label>
                            <input type="text" id="address" name="address" required placeholder="Ej: Calle Principal 123" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">Ciudad *</label>
                                <input type="text" id="city" name="city" required placeholder="Ej: México, CDMX">
                            </div>
                            <div class="form-group">
                                <label for="postalCode">Código Postal *</label>
                                <input type="text" id="postalCode" name="postalCode" required placeholder="Ej: 28001">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="deliveryNotes">Notas de Entrega (opcional)</label>
                            <textarea id="deliveryNotes" name="deliveryNotes" placeholder="Ej: Timbre no funciona, por favor llamar al llegar"></textarea>
                        </div>
                    </div>

                    <!-- Shipping Method -->
                    <div class="form-section">
                        <div class="form-section-title">🚚 Método de Envío</div>
                        
                        <div class="form-group">
                            <label>
                                <input type="radio" name="shippingMethod" value="standard" checked> 
                                <strong>Envío Estándar</strong> - Gratis (5-7 días hábiles)
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="radio" name="shippingMethod" value="express"> 
                                <strong>Envío Express</strong> - $15.00 (2-3 días hábiles)
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="radio" name="shippingMethod" value="pickup"> 
                                <strong>Retiro en Tienda</strong> - Gratis (Dentro de 24 horas)
                            </label>
                        </div>
                    </div>

                    <!-- Promo Code -->
                    <div class="form-section">
                        <div class="form-section-title">🎟️ Código Promocional (opcional)</div>
                        
                        <div class="form-group">
                            <input type="text" id="promoCode" name="promoCode" placeholder="Ingresa tu código promocional">
                        </div>
                        <small style="color: var(--theme-text-muted);">Si tienes un código de descuento, ingresalo aquí</small>
                    </div>

                    <!-- Order Notes -->
                    <div class="form-section">
                        <div class="form-section-title">💬 Notas del Pedido (opcional)</div>
                        
                        <div class="form-group">
                            <textarea id="orderNotes" name="orderNotes" placeholder="Notas especiales o instrucciones adicionales para tu pedido"></textarea>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="form-section">
                        <div class="form-section-title">💳 Método de Pago</div>
                        
                        <div class="form-group">
                            <label>
                                <input type="radio" name="paymentMethod" value="credit_card" checked> 
                                <strong>Tarjeta de Crédito/Débito</strong>
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="radio" name="paymentMethod" value="bank_transfer"> 
                                <strong>Transferencia Bancaria</strong>
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="radio" name="paymentMethod" value="on_delivery"> 
                                <strong>Contra Entrega</strong>
                            </label>
                        </div>
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="form-section">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="termsAccepted" name="termsAccepted" required> 
                                He leído y acepto los <a href="#" target="_blank" style="color: var(--theme-accent);">términos y condiciones</a> y <a href="#" target="_blank" style="color: var(--theme-accent);">política de privacidad</a>
                            </label>
                        </div>
                    </div>

                    <div id="formMessage"></div>

                    <div class="summary-actions">
                        <button type="submit" id="submitBtn" class="btn btn-primary btn-full">✅ Confirmar Pedido</button>
                        <a href="cart.php" class="btn btn-ghost btn-full" style="text-align: center;">← Volver al Carrito</a>
                    </div>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="checkout-summary">
                <div class="summary-title">📋 Resumen del Pedido</div>

                <div class="summary-items" id="summaryItems">
                    <div style="text-align: center; padding: 2rem 0; color: var(--theme-text-muted);">
                        Cargando artículos...
                    </div>
                </div>

                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="summarySubtotal">$0.00</span>
                </div>

                <div class="summary-row">
                    <span>Envío:</span>
                    <span id="summaryShipping">$0.00</span>
                </div>

                <div class="summary-row">
                    <span>Descuento:</span>
                    <span id="summaryDiscount">-$0.00</span>
                </div>

                <div class="summary-total">
                    <span>Total:</span>
                    <span id="summaryTotal">$0.00</span>
                </div>

                <small style="color: var(--theme-text-muted); display: block; margin-top: 1rem; text-align: center;">
                    ✓ Envío seguro<br>
                    ✓ Garantía de producto<br>
                    ✓ Soporte 24/7
                </small>
            </div>
        </div>
    </main>

    <footer style="margin-top: 3rem; padding: 2rem; text-align: center; border-top: 1px solid var(--theme-border); color: var(--theme-text-muted);">
        <p>&copy; 2026 Truper Platform</p>
    </footer>

    <script src="js/main.js"></script>
    <script>
        // Load cart and populate summary
        function loadCartSummary() {
            try {
                const cart = JSON.parse(localStorage.getItem('truper_cart') || '[]');
                if (cart.length === 0) {
                    window.location.href = 'cart.php';
                    return;
                }

                let subtotal = 0;
                let html = '';

                cart.forEach(item => {
                    const itemTotal = (item.price || 0) * (item.quantity || 1);
                    subtotal += itemTotal;
                    html += `
                        <div class="summary-item">
                            <div class="summary-item-name">${item.name || 'Producto'} <strong>x${item.quantity || 1}</strong></div>
                            <div class="summary-item-price">$${itemTotal.toFixed(2)}</div>
                        </div>
                    `;
                });

                document.getElementById('summaryItems').innerHTML = html;
                updateTotals(subtotal);
            } catch (e) {
                console.error('Error loading cart:', e);
            }
        }

        function updateTotals(subtotal) {
            const shippingMethod = document.querySelector('input[name="shippingMethod"]:checked')?.value;
            let shippingCost = 0;

            if (shippingMethod === 'express') shippingCost = 15;
            if (shippingMethod === 'standard' || shippingMethod === 'pickup') shippingCost = 0;

            const total = subtotal + shippingCost;

            document.getElementById('summarySubtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('summaryShipping').textContent = '$' + shippingCost.toFixed(2);
            document.getElementById('summaryTotal').textContent = '$' + total.toFixed(2);
        }

        // Event listeners
        document.querySelectorAll('input[name="shippingMethod"]').forEach(input => {
            input.addEventListener('change', () => {
                const subtotal = parseFloat(document.getElementById('summarySubtotal').textContent.replace('$', ''));
                updateTotals(subtotal);
            });
        });

        document.getElementById('checkoutForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const formMessage = document.getElementById('formMessage');

            // Validate
            if (!document.getElementById('termsAccepted').checked) {
                formMessage.innerHTML = '<div class="error-message">❌ Debes aceptar los términos y condiciones</div>';
                return;
            }

            // Get form data
            const formData = new FormData(document.getElementById('checkoutForm'));
            const data = {
                firstName: formData.get('firstName'),
                lastName: formData.get('lastName'),
                email: formData.get('email'),
                phone: formData.get('phone'),
                address: formData.get('address'),
                city: formData.get('city'),
                postalCode: formData.get('postalCode'),
                deliveryNotes: formData.get('deliveryNotes'),
                shippingMethod: formData.get('shippingMethod'),
                promoCode: formData.get('promoCode'),
                orderNotes: formData.get('orderNotes'),
                paymentMethod: formData.get('paymentMethod'),
                cartItems: JSON.parse(localStorage.getItem('truper_cart') || '[]')
            };

            // Submit
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Procesando...';

            try {
                const response = await fetch('/api/checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    // Clear cart and redirect
                    localStorage.removeItem('truper_cart');
                    window.location.href = '/order_confirmation.php?order_id=' + result.order_id;
                } else {
                    formMessage.innerHTML = '<div class="error-message">❌ ' + (result.message || 'Error al procesar el pedido') + '</div>';
                    submitBtn.disabled = false;
                    submitBtn.textContent = '✅ Confirmar Pedido';
                }
            } catch (e) {
                console.error('Checkout error:', e);
                formMessage.innerHTML = '<div class="error-message">❌ Error de conexión. Por favor, intenta de nuevo.</div>';
                submitBtn.disabled = false;
                submitBtn.textContent = '✅ Confirmar Pedido';
            }
        });

        // Load cart on page load
        document.addEventListener('DOMContentLoaded', loadCartSummary);
    </script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
