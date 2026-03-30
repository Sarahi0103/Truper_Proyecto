<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo">🏪 Truper</a>
            <nav class="nav-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="orders.php" class="active">Pedidos</a>
                <a href="tasks.php">Tareas</a>
                <a href="analytics.php">Estadísticas</a>
                <a href="profile.php">Perfil</a>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name">Usuario</div>
                <div class="user-role">Cliente</div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="container-fluid">
            <div class="d-flex justify-between align-center">
                <h1>Gestión de Pedidos</h1>
                <a href="#newOrderModal" onclick="openModal('newOrderModal')" class="btn btn-primary">
                    ➕ Nuevo Pedido
                </a>
            </div>

            <!-- TABS -->
            <div class="tabs">
                <button class="tab-button active" data-tab="myOrders">Mis Pedidos</button>
                <button class="tab-button" data-tab="newOrder">Crear Pedido</button>
            </div>

            <!-- MIS ÓRDENES -->
            <div id="myOrders" class="tab-content active">
                <div class="card">
                    <div class="card-header">Mi Historial de Pedidos</div>
                    <div class="card-body">
                        <div style="margin-bottom: 1rem;">
                            <input type="text" id="orderSearch" placeholder="Buscar orden..." onkeyup="searchOrders()" style="padding: 0.5rem; width: 200px;">
                            <select id="orderFilter" onchange="filterOrders()" style="padding: 0.5rem; margin-left: 1rem;">
                                <option value="">Todos los estados</option>
                                <option value="pending">Pendiente</option>
                                <option value="confirmed">Confirmado</option>
                                <option value="processing">En Proceso</option>
                                <option value="shipped">Enviado</option>
                                <option value="delivered">Entregado</option>
                                <option value="cancelled">Cancelado</option>
                            </select>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Número de Orden</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Estado Pago</th>
                                    <th>Estado Orden</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="ordersList">
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Cargando órdenes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- CREAR NUEVO PEDIDO -->
            <div id="newOrder" class="tab-content">
                <div class="card">
                    <div class="card-header">Crear Nuevo Pedido</div>
                    <div class="card-body">
                        <div class="form-section">
                            <h3>Seleccionar Productos</h3>
                            <input type="text" id="productSearch" placeholder="Buscar productos..." onkeyup="searchProducts()" style="padding: 0.5rem; margin-bottom: 1rem; width: 100%;">
                            
                            <table>
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>SKU</th>
                                        <th>Precio Unitario</th>
                                        <th>Cantidad</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="productsList">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Cargando productos...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="form-section">
                            <h3>Resumen del Pedido</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Precio Unit.</th>
                                        <th>Subtotal</th>
                                        <th>Descuento</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="cartItems">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Tu carrito está vacío</td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="order-summary">
                                <div class="summary-row">
                                    <span>Subtotal:</span>
                                    <span>$0</span>
                                </div>
                                <div class="summary-row">
                                    <span>Descuentos:</span>
                                    <span>$0</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total a Pagar:</span>
                                    <span id="cartTotal">$0</span>
                                </div>
                            </div>

                            <div class="form-group mt-3">
                                <label>
                                    <input type="checkbox" id="isWholesale"> Este es un pedido al por mayor
                                </label>
                            </div>

                            <div class="form-group mt-3">
                                <label for="orderNotes">Notas Adicionales</label>
                                <textarea id="orderNotes" placeholder="Agrega notas sobre tu pedido..." style="width: 100%;"></textarea>
                            </div>

                            <div class="btn-group mt-4">
                                <button type="button" class="btn btn-secondary" onclick="clearCart()">Limpiar Carrito</button>
                                <button type="button" class="btn btn-primary" onclick="createOrder()">Confirmar Pedido</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- MODAL DE PAGO -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Registrar Pago</h2>
                <button class="modal-close" onclick="closeModal('paymentModal')">×</button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <div class="form-group">
                        <label>Monto a Pagar</label>
                        <input type="number" id="paymentAmount" placeholder="0.00" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Método de Pago</label>
                        <select id="paymentMethod" required>
                            <option value="cash">Efectivo</option>
                            <option value="card">Tarjeta</option>
                            <option value="transfer">Transferencia</option>
                            <option value="check">Cheque</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Número de Referencia (Opcional)</label>
                        <input type="text" id="paymentReference" placeholder="Ej: número de cheque o transferencia">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('paymentModal')">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="recordPayment()">Registrar Pago</button>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h4>Truper</h4>
                <p>Plataforma de Gestión Empresarial</p>
            </div>
            <div class="footer-section">
                <h4>Contacto</h4>
                <p>Email: soporte@truper.com</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Truper Platform. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script src="js/orders.js"></script>
    <script>
        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }

        function searchOrders() {
            // Implementar búsqueda
        }
    </script>
</body>
</html>
