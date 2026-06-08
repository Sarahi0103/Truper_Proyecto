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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Cajón de Dinero - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css?v=2.2">
    <link rel="stylesheet" href="css/theme.css?v=2.5">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
    /* Cashier specific styles */
    .cashier-container {
        opacity: 1;
        transition: opacity 0.3s ease;
    }
    .cashier-container.drawer-loading {
        opacity: 0.6;
        pointer-events: none;
    }

    /* Visibility rules based on state */
    .cashier-container.drawer-closed .show-only-open {
        display: none !important;
    }
    .cashier-container.drawer-open .show-only-closed {
        display: none !important;
    }

    /* Status Banner styling */
    .status-banner {
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 1.25rem 1.5rem;
        background: #1e1e1e;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        margin-bottom: 2rem;
    }
    @media (min-width: 768px) {
        .status-banner {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
    }
    .status-indicator {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.15rem;
        font-weight: 600;
    }
    .status-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #555;
        display: inline-block;
    }
    .drawer-open .status-dot {
        background: #10b981;
        box-shadow: 0 0 12px #10b981;
        animation: cashierPulse 2s infinite;
    }
    .drawer-closed .status-dot {
        background: #ef4444;
        box-shadow: 0 0 12px #ef4444;
    }
    @keyframes cashierPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    .status-subtext {
        font-size: 0.9rem;
        color: #888;
        margin-top: 0.5rem;
    }
    @media (min-width: 768px) {
        .status-subtext {
            margin-top: 0;
        }
    }

    /* Session Actions Cards */
    .cashier-session-actions {
        margin-bottom: 2rem;
    }
    .drawer-closed .cashier-session-actions {
        display: flex;
        justify-content: center;
    }
    .cashier-action-card {
        background: #1e1e1e;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        transition: transform 0.2s ease, border-color 0.2s ease;
        width: 100%;
    }
    .drawer-closed .cashier-action-card {
        max-width: 480px;
    }
    .cashier-action-card .card-body {
        padding: 2rem;
    }
    .cashier-action-card .card-icon {
        font-size: 2rem;
        margin-bottom: 1rem;
        background: rgba(255, 102, 0, 0.1);
        color: #ff6600;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
    }
    .cashier-action-card h3 {
        font-size: 1.4rem;
        margin-bottom: 0.5rem;
        color: #fff;
    }
    .cashier-action-card .card-description {
        color: #888;
        font-size: 0.9rem;
        margin-bottom: 1.5rem;
        line-height: 1.4;
    }

    /* Inputs & Form Groups */
    .form-group {
        margin-bottom: 1.25rem;
        text-align: left;
    }
    .form-group label {
        display: block;
        font-size: 0.85rem;
        color: #aaa;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        background: #121212;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        color: #fff;
        font-size: 1rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-control:focus {
        outline: none;
        border-color: #ff6600;
        box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.15);
    }
    .input-with-prefix {
        position: relative;
        display: flex;
        align-items: center;
    }
    .input-with-prefix .prefix {
        position: absolute;
        left: 1rem;
        color: #666;
        font-weight: 600;
    }
    .input-with-prefix .form-control {
        padding-left: 2rem;
    }

    /* Buttons styling */
    .btn-block {
        display: block;
        width: 100%;
    }
    .btn-danger {
        background: #dc2626 !important;
        color: white !important;
    }
    .btn-danger:hover {
        background: #b91c1c !important;
    }

    /* Metrics Dashboard */
    .cash-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }
    .metric-card {
        background: #1e1e1e;
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .metric-card:hover {
        transform: translateY(-2px);
        border-color: rgba(255, 102, 0, 0.25);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }
    .metric-card .metric-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }
    .metric-card .metric-title {
        font-size: 0.8rem;
        color: #aaa;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .metric-card .metric-icon {
        font-size: 1.25rem;
        background: rgba(255, 255, 255, 0.04);
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
    }
    .metric-card .metric-val {
        font-size: 1.5rem;
        font-weight: 700;
        color: #fff;
    }
    .metric-card.primary .metric-icon { color: #ff6600; background: rgba(255, 102, 0, 0.1); }
    .metric-card.positive .metric-val { color: #10b981; }
    .metric-card.positive .metric-icon { color: #10b981; background: rgba(16, 185, 129, 0.1); }
    .metric-card.negative .metric-val { color: #ef4444; }
    .metric-card.negative .metric-icon { color: #ef4444; background: rgba(239, 68, 68, 0.1); }
    .metric-card.warning .metric-val { color: #f59e0b; }
    .metric-card.warning .metric-icon { color: #f59e0b; background: rgba(245, 158, 11, 0.1); }

    /* Placeholder Card */
    .placeholder-card {
        background: #1e1e1e;
        border: 1px dashed rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        padding: 3rem 2rem;
        text-align: center;
        margin: 1.5rem 0;
    }
    .placeholder-card .placeholder-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    .placeholder-card h4 {
        font-size: 1.2rem;
        margin-bottom: 0.5rem;
        color: #fff;
    }
    .placeholder-card p {
        color: #888;
        max-width: 400px;
        margin: 0 auto;
        font-size: 0.95rem;
    }

    /* Tables design */
    .table-responsive {
        overflow-x: auto;
        width: 100%;
        margin-top: 1rem;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    table th {
        background: rgba(255, 255, 255, 0.03);
        color: #aaa;
        text-align: left;
        padding: 0.75rem 1rem;
        font-weight: 600;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        white-space: nowrap;
    }
    table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        color: #ddd;
        white-space: nowrap;
    }
    table tr:hover td {
        background: rgba(255, 255, 255, 0.01);
    }

    /* Badges for status */
    .badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }
    .badge-pending {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
    }
    .badge-partial {
        background: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
    }
    .badge-paid {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
    }
    .badge-overdue {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
    }

    /* Tabs styles override */
    .tabs {
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
    }
    .tab-button {
        background: none;
        border: none;
        color: #888;
        padding: 0.75rem 1.25rem;
        cursor: pointer;
        font-weight: 500;
        font-size: 0.95rem;
        border-bottom: 2px solid transparent;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .tab-button:hover {
        color: #fff;
    }
    .tab-button.active {
        color: #ff6600;
        border-bottom-color: #ff6600;
    }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <a href="dashboard.php" class="logo"><img src="img/logo_truper.1.1.png" alt="Truper" style="height: 40px; width: auto; object-fit: contain;"></a>
                <nav class="nav-menu">
            <a href="index.php">Catálogo</a>
            <a href="marketplace_ce.php">Marketplace CE</a>
            <div class="nav-dropdown">
                <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                <div class="nav-dropdown-content">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="orders.php">Pedidos</a>
                    <a href="wholesale.php">Mayoreo</a>
                    <a href="account.php#historyTab">Historial</a>
                    <a href="profile.php">Perfil</a>
                </div>
            </div>
            <div class="nav-dropdown">
                <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                <div class="nav-dropdown-content">
                    <a href="cashier.php" class="active">Caja</a>
                    <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="tickets.php">Tickets</a><?php endif; ?>
                    <a href="tasks.php">Tareas</a>
                    <a href="analytics.php">Estadísticas</a>
                </div>
            </div>
        </nav>
    </div>
    <div class="user-menu">
        <div class="user-info">
            <div class="user-name"><?php echo $user_name; ?></div>
            <div class="user-role">ADMIN</div>
        </div>
        <button class="btn-logout" onclick="window.location.href='api/auth.php?action=logout'">Cerrar Sesion</button>
    </div>
</header>
<main>
    <div class="container">
        <div class="page-hero">
            <div class="module-badge module-finance"><span class="module-glyph">CJ</span> Control financiero</div>
            <h1>Control de Cajón de Dinero</h1>
            <p class="text-muted">Apertura/cierre de caja, flujo de caja diario, plazos de pago y control semanal.</p>
        </div>

        <div id="cashierContainer" class="cashier-container drawer-loading">
            <!-- Estatus actual -->
            <div class="status-banner">
                <div class="status-indicator">
                    <span class="status-dot"></span>
                    <span id="cashierStatusText">Consultando...</span>
                </div>
                <div id="cashierStatusSubtext" class="status-subtext"></div>
            </div>

            <!-- Progreso de Meta Mensual (Cashier POS Analytics) -->
            <div class="card mt-3 show-only-open" id="monthlyGoalProgressCard" style="border: 1px solid rgba(255, 102, 0, 0.15); background: rgba(255, 102, 0, 0.02); display:none;">
                <div class="card-body" style="padding: 1.25rem;">
                    <h3 style="margin:0 0 0.5rem; font-size:1.1rem; color:#fff; display:flex; align-items:center; gap:8px;">
                        <span>🎯</span> Meta de Ventas Mensual
                    </h3>
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; color:#aaa; margin-bottom:0.4rem;">
                        <span id="monthlyGoalLabel">Cargando meta del mes...</span>
                        <span id="monthlyGoalPct" style="font-weight:700; color:var(--color-naranja, #ff6600);">0%</span>
                    </div>
                    <div style="height:10px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); border-radius:999px; overflow:hidden; position:relative; margin-bottom:0.75rem;">
                        <div id="monthlyGoalProgressFill" style="height:100%; width:0%; background:linear-gradient(90deg, #ff6600, #ff9500); border-radius:999px; transition:width 0.6s cubic-bezier(0.4,0,0.2,1); box-shadow:0 0 8px rgba(255,102,0,0.25);"></div>
                    </div>
                    <div class="grid grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; font-size:0.82rem; color:#888;">
                        <div>
                            Acumulado del mes: <strong id="monthlyGoalAchieved" style="color:#fff;">$0.00</strong>
                        </div>
                        <div style="text-align:right;">
                            Ventas del día: <strong id="dailyGoalComparison" style="color:#2ecc71;">$0.00</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Session Actions Cards -->
            <div class="cashier-session-actions">
                <!-- Center this if drawer-closed -->
                <div class="card cashier-action-card show-only-closed">
                    <div class="card-body">
                        <div class="card-icon">🔑</div>
                        <h3>Apertura de Caja</h3>
                        <p class="card-description">Inicie el turno de caja registrando el fondo inicial de efectivo disponible en el cajón.</p>
                        <div class="form-group">
                            <label for="openAmount">Monto inicial de efectivo</label>
                            <div class="input-with-prefix">
                                <span class="prefix">$</span>
                                <input id="openAmount" type="number" step="0.01" placeholder="0.00" class="form-control">
                            </div>
                        </div>
                        <button class="btn btn-primary btn-block mt-3" onclick="openDrawer()">
                            Iniciar Turno y Abrir Caja
                        </button>
                    </div>
                </div>
                
                <div class="card cashier-action-card show-only-open">
                    <div class="card-body">
                        <div class="card-icon">🔒</div>
                        <h3>Cierre de Caja</h3>
                        <p class="card-description">Finalice el turno de caja contando el efectivo físico disponible en el cajón para calcular el arqueo.</p>
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="closeAmount">Monto contado en efectivo</label>
                                <div class="input-with-prefix">
                                    <span class="prefix">$</span>
                                    <input id="closeAmount" type="number" step="0.01" placeholder="0.00" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="closeNote">Observaciones del cierre</label>
                                <input id="closeNote" type="text" placeholder="Ej. Todo cuadra, diferencia de centavos..." class="form-control">
                            </div>
                        </div>
                        <button class="btn btn-danger btn-block mt-3" onclick="closeDrawer()">
                            Finalizar Turno y Cerrar Caja
                        </button>
                    </div>
                </div>
            </div>

            <!-- Metrics Section -->
            <div class="card show-only-open mt-3">
                <div class="card-body">
                    <h3>Resumen Financiero del Día</h3>
                    <p class="text-muted">Indicadores clave de rendimiento calculados en tiempo real para el turno activo.</p>
                    <div id="cashierMetrics" class="cash-metrics"></div>
                </div>
            </div>

            <!-- Tabs Section -->
            <div class="tabs mt-4">
              <button class="tab-button active" data-tab="cashMovementsTab">Movimientos de Caja</button>
              <button class="tab-button" data-tab="cashNotesTab">Notas de Control (Plazos)</button>
              <button class="tab-button" data-tab="cashWeeklyTab">Flujo Semanal</button>
            </div>

            <!-- Tab: Movimientos -->
            <section id="cashMovementsTab" class="tab-content active">
              <div class="card mt-3 show-only-open"><div class="card-body">
                <h3>Registrar Movimiento de Caja</h3>
                <p class="text-muted mb-3">Ingrese las entradas o salidas de efectivo no asociadas a tickets de venta directos.</p>
                <div class="grid grid-3">
                  <div class="form-group">
                    <label for="moveType">Tipo de Movimiento</label>
                    <select id="moveType" class="form-control">
                      <option value="in">Entrada (Ingreso)</option>
                      <option value="out">Salida (Egreso)</option>
                      <option value="sale">Venta en efectivo manual</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="moveAmount">Monto</label>
                    <div class="input-with-prefix">
                      <span class="prefix">$</span>
                      <input id="moveAmount" type="number" step="0.01" placeholder="0.00" class="form-control">
                    </div>
                  </div>
                  <div class="form-group">
                    <label for="moveDesc">Descripción / Concepto</label>
                    <input id="moveDesc" type="text" placeholder="Ej. Pago de flete, cambio..." class="form-control">
                  </div>
                </div>
                <button class="btn btn-primary mt-2" onclick="addMovement()">Registrar movimiento</button>
              </div></div>

              <!-- Placeholder when closed -->
              <div class="placeholder-card show-only-closed">
                  <div class="placeholder-icon">📥</div>
                  <h4>Caja Cerrada</h4>
                  <p>Debe iniciar turno abriendo la caja para poder registrar movimientos de efectivo.</p>
              </div>
            </section>

            <!-- Tab: Notas y plazos -->
            <section id="cashNotesTab" class="tab-content">
              <!-- Placeholder when closed -->
              <div class="placeholder-card show-only-closed">
                  <div class="placeholder-icon">📋</div>
                  <h4>Vista de Consulta (Caja Cerrada)</h4>
                  <p>La caja está cerrada. Puede ver el estado de las notas registradas abajo, pero no se permiten nuevos registros o cobros hasta iniciar un turno.</p>
              </div>

              <div class="card mt-3 show-only-open"><div class="card-body">
                <h3>Registrar Nota de Control (Deuda / Crédito)</h3>
                <p class="text-muted mb-3">Registre plazos de pago pendientes para clientes (Cuentas por Cobrar) o proveedores (Cuentas por Pagar).</p>
                <div class="grid grid-3">
                  <div class="form-group">
                    <label for="noteType">Tipo de Nota</label>
                    <select id="noteType" class="form-control">
                      <option value="customer">Cliente (Pendiente de Cobro)</option>
                      <option value="supplier">Proveedor (Pendiente de Pago)</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="noteAmount">Monto Total</label>
                    <div class="input-with-prefix">
                      <span class="prefix">$</span>
                      <input id="noteAmount" type="number" step="0.01" min="0" placeholder="0.00" class="form-control">
                    </div>
                  </div>
                  <div class="form-group">
                    <label for="noteTerm">Plazo y Término</label>
                    <select id="noteTerm" class="form-control">
                      <option value="contado">Inmediato (Contado)</option>
                      <option value="15dias">15 días de plazo</option>
                      <option value="30dias">30 días de plazo</option>
                    </select>
                  </div>
                </div>
                <div class="grid grid-2 mt-2">
                  <div class="form-group">
                    <label for="noteRef">Referencia (Ticket / Folio de Compra)</label>
                    <input id="noteRef" type="text" placeholder="Ej. TKT-7482" class="form-control">
                  </div>
                  <div class="form-group">
                    <label for="noteDesc">Concepto / Descripción corta</label>
                    <input id="noteDesc" type="text" placeholder="Ej. Resto pendiente por entrega parcial..." class="form-control">
                  </div>
                </div>
                <button class="btn btn-primary mt-2" onclick="createControlNote()">Registrar nota</button>
              </div></div>

              <div class="card mt-3 show-only-open"><div class="card-body">
                <h3>Abonar a Nota Pendiente</h3>
                <p class="text-muted mb-3">Registre un pago parcial o total a una nota existente utilizando su ID.</p>
                <div class="grid grid-3">
                  <div class="form-group">
                    <label for="payNoteId">ID de Nota</label>
                    <input id="payNoteId" type="number" min="1" placeholder="Ej. 12" class="form-control">
                  </div>
                  <div class="form-group">
                    <label for="payAmount">Monto del Abono</label>
                    <div class="input-with-prefix">
                      <span class="prefix">$</span>
                      <input id="payAmount" type="number" step="0.01" min="0.01" placeholder="0.00" class="form-control">
                    </div>
                  </div>
                  <div class="form-group">
                    <label for="payMethod">Método de Pago</label>
                    <select id="payMethod" class="form-control">
                      <option value="cash">Efectivo (Caja)</option>
                      <option value="transfer">Transferencia</option>
                      <option value="card">Tarjeta de Débito/Crédito</option>
                    </select>
                  </div>
                </div>
                <div class="form-group">
                  <label for="payNotes">Notas / Observaciones del Abono</label>
                  <input id="payNotes" type="text" placeholder="Ej. Pago con billete de $500..." class="form-control">
                </div>
                <button class="btn btn-secondary mt-2" onclick="registerNotePayment()">Registrar abono</button>
              </div></div>

              <div class="card mt-3"><div class="card-body">
                <h3>Notas Registradas (Ledger)</h3>
                <p class="text-muted mb-2">Listado general de cuentas pendientes y liquidadas.</p>
                <div class="table-responsive">
                  <div id="notesList" class="text-muted">Cargando notas...</div>
                </div>
              </div></div>
            </section>

            <!-- Tab: Flujo semanal -->
            <section id="cashWeeklyTab" class="tab-content">
              <div class="card mt-3"><div class="card-body">
                <h3>Flujo Semanal Histórico</h3>
                <p class="text-muted mb-3">Consulte el consolidado de entradas, salidas y flujo neto de caja de las últimas semanas.</p>
                <div class="grid grid-3">
                  <div class="form-group">
                    <label for="flowWeeks">Semanas a consultar</label>
                    <input id="flowWeeks" type="number" min="1" max="24" value="8" class="form-control">
                  </div>
                  <div class="form-group d-flex align-end">
                    <button class="btn btn-primary btn-block" onclick="loadWeeklyCashflow()">Consultar flujo</button>
                  </div>
                </div>
                <div class="table-responsive">
                  <div id="weeklyFlow" class="text-muted">Sin datos de flujo.</div>
                </div>
              </div></div>
            </section>
        </div>
    </div>
</main>
<script>
window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
</script>
<script src="js/main.js?v=2.6"></script>
<script>
function formatMoney(value) {
  return `$${Number(value || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function renderMetrics(summary) {
  const box = document.getElementById('cashierMetrics');
  if (!box) return;
  if (!summary) {
    box.innerHTML = '<div class="text-muted">Sin datos de resumen de caja activos.</div>';
    return;
  }

  const items = [
    { label: 'Efectivo esperado', value: formatMoney(summary.cash_expected), icon: '💵', variant: 'primary' },
    { label: 'Ventas del día', value: formatMoney(summary.sales_today), icon: '📈', variant: 'positive' },
    { label: 'Cobros pendientes', value: formatMoney(summary.pending_collections), icon: '⏳', variant: 'warning' },
    { label: 'A proveedores pend.', value: formatMoney(summary.pending_supplier_payments), icon: '📉', variant: 'negative' },
    { label: 'Notas pendientes', value: formatMoney(summary.pending_notes), icon: '📋', variant: 'warning' },
    { label: 'Notas vencidas', value: formatMoney(summary.overdue_notes), icon: '⚠️', variant: 'negative' },
    { label: 'Ganancia estimada', value: formatMoney(summary.real_profit), icon: '💰', variant: 'positive' },
    { label: 'Margen de ganancia', value: `${Number(summary.profit_margin_pct || 0).toFixed(2)}%`, icon: '📊', variant: 'primary' }
  ];

  box.innerHTML = items.map(item => `
    <div class="metric-card ${item.variant}">
      <div class="metric-header">
        <span class="metric-title">${item.label}</span>
        <span class="metric-icon">${item.icon}</span>
      </div>
      <div class="metric-val">${item.value}</div>
    </div>
  `).join('');
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
  const container = document.getElementById('cashierContainer');
  if (container) container.classList.add('drawer-loading');

  const res = await apiCall('/cashier.php?action=summary');
  
  if (container) container.classList.remove('drawer-loading');

  const statusText = document.getElementById('cashierStatusText');
  const statusSubtext = document.getElementById('cashierStatusSubtext');
  
  if (!res || !res.success) {
    if (statusText) statusText.textContent = 'Error al consultar estatus';
    if (statusSubtext) statusSubtext.textContent = 'No fue posible comunicarse con el servidor';
    if (container) {
      container.classList.remove('drawer-open');
      container.classList.add('drawer-closed');
    }
    renderMetrics(null);
    return;
  }
  
  if (!res.open_session) {
    if (statusText) statusText.textContent = 'Caja Cerrada';
    if (statusSubtext) statusSubtext.textContent = 'Inicie un turno de caja para realizar transacciones.';
    if (container) {
      container.classList.remove('drawer-open');
      container.classList.add('drawer-closed');
    }
    const openAmount = document.getElementById('openAmount');
    if (openAmount) openAmount.value = '';
  } else {
    const dateFormatted = new Date(res.open_session.opened_at).toLocaleString('es-MX', {
      hour: '2-digit',
      minute: '2-digit',
      day: '2-digit',
      month: 'short'
    });
    if (statusText) statusText.textContent = 'Caja Activa (Abierta)';
    if (statusSubtext) statusSubtext.textContent = `Turno iniciado por ID de usuario #${res.open_session.opened_by} el ${dateFormatted} con fondo inicial de ${formatMoney(res.open_session.opening_amount)}`;
    if (container) {
      container.classList.remove('drawer-closed');
      container.classList.add('drawer-open');
    }
    const closeAmount = document.getElementById('closeAmount');
    const closeNote = document.getElementById('closeNote');
    if (closeAmount) closeAmount.value = '';
    if (closeNote) closeNote.value = '';
  }
  
  renderMetrics(res.summary || null);

  if (res.open_session) {
    updateMonthlyGoalProgress(res.summary ? Number(res.summary.sales_today || 0) : 0);
  } else {
    const goalCard = document.getElementById('monthlyGoalProgressCard');
    if (goalCard) goalCard.style.display = 'none';
  }
}

async function updateMonthlyGoalProgress(salesToday) {
  const goalCard = document.getElementById('monthlyGoalProgressCard');
  if (!goalCard) return;

  const currentMonthKey = new Date().toISOString().slice(0, 7);
  const goalRes = await apiCall(`/cashier.php?action=goal-summary&month_key=${currentMonthKey}`, 'GET', null, { silent: true });
  
  if (goalRes && goalRes.success && goalRes.goal) {
    goalCard.style.display = 'block';
    
    const goal = goalRes.goal;
    const target = Number(goal.target_amount || 0);
    const achieved = Number(goal.achieved_amount || 0);
    const progress = Number(goal.progress_pct || 0);
    
    document.getElementById('monthlyGoalLabel').textContent = `Meta de ${currentMonthKey}: ${formatMoney(target)}`;
    document.getElementById('monthlyGoalPct').textContent = `${progress.toFixed(1)}%`;
    document.getElementById('monthlyGoalProgressFill').style.width = `${Math.min(100, progress)}%`;
    document.getElementById('monthlyGoalAchieved').textContent = formatMoney(achieved);
    
    // Comparar ventas de hoy vs meta diaria proporcional (Meta / 30)
    const dailyTarget = target / 30;
    const dailyPct = dailyTarget > 0 ? (salesToday / dailyTarget * 100) : 0;
    
    document.getElementById('dailyGoalComparison').innerHTML = `
      ${formatMoney(salesToday)} 
      <span style="color: ${salesToday >= dailyTarget ? '#2ecc71' : '#f59e0b'}; font-size: 0.75rem; font-weight: bold;">
        (${salesToday >= dailyTarget ? '✓ Cumplido' : '⏳ ' + dailyPct.toFixed(1) + '% de meta diaria'})
      </span>
    `;
  } else {
    goalCard.style.display = 'none';
  }
}

async function openDrawer() {
  const amountVal = document.getElementById('openAmount').value || 0;
  const res = await apiCall('/cashier.php?action=open', 'POST', { opening_amount: amountVal });
  if (res && res.success) {
    showAlert(res.message, 'success');
    refreshStatus();
  } else if (res) {
    showAlert(res.message, 'error');
  }
}

async function addMovement() {
  const moveType = document.getElementById('moveType').value;
  const moveAmount = document.getElementById('moveAmount').value;
  const moveDesc = document.getElementById('moveDesc').value;

  if (!moveAmount || Number(moveAmount) <= 0) {
    showAlert('Por favor, ingrese un monto válido para el movimiento', 'error');
    return;
  }

  const res = await apiCall('/cashier.php?action=movement', 'POST', {
    movement_type: moveType,
    amount: moveAmount,
    description: moveDesc
  });
  
  if (res && res.success) {
    showAlert(res.message, 'success');
    document.getElementById('moveAmount').value = '';
    document.getElementById('moveDesc').value = '';
    refreshStatus();
  } else if (res) {
    showAlert(res.message, 'error');
  }
}

async function closeDrawer() {
  const closeAmount = document.getElementById('closeAmount').value;
  const closeNote = document.getElementById('closeNote').value;

  if (closeAmount === '') {
    showAlert('Por favor, ingrese el monto contado de caja para realizar el arqueo', 'error');
    return;
  }

  const res = await apiCall('/cashier.php?action=close', 'POST', {
    closing_amount: closeAmount,
    notes: closeNote
  });
  
  if (res && res.success) {
    showAlert(`Caja cerrada exitosamente. Arqueo completado. Diferencia de caja: ${formatMoney(res.difference_amount)}`, 'success');
    refreshStatus();
  } else if (res) {
    showAlert(res.message, 'error');
  }
}

async function createControlNote() {
  const typeVal = document.getElementById('noteType').value;
  const amountVal = document.getElementById('noteAmount').value;
  const termVal = document.getElementById('noteTerm').value;
  const refVal = document.getElementById('noteRef').value;
  const descVal = document.getElementById('noteDesc').value;

  if (!amountVal || Number(amountVal) <= 0) {
    showAlert('Por favor, ingrese un monto de deuda válido', 'error');
    return;
  }

  const payload = {
    note_type: typeVal,
    total_amount: amountVal,
    payment_term: termVal,
    reference_ticket: refVal,
    description: descVal
  };
  
  const res = await apiCall('/cashier.php?action=note-create', 'POST', payload);
  if (res && res.success) {
    showAlert(`${res.message}: ${res.note_folio || ''}`, 'success');
    document.getElementById('noteAmount').value = '';
    document.getElementById('noteRef').value = '';
    document.getElementById('noteDesc').value = '';
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
      <thead>
        <tr>
          <th>ID</th>
          <th>Folio</th>
          <th>Tipo</th>
          <th>Total</th>
          <th>Pagado</th>
          <th>Saldo</th>
          <th>Plazo</th>
          <th>Vence</th>
          <th>Estatus</th>
        </tr>
      </thead>
      <tbody>
        ${res.items.map((n) => {
          const total = Number(n.total_amount || 0);
          const paid = Number(n.amount_paid || 0);
          const remaining = Math.max(0, total - paid);
          
          let badgeClass = 'badge-pending';
          let statusText = n.status || '';
          if (n.status === 'paid') badgeClass = 'badge-paid';
          else if (n.status === 'partial') badgeClass = 'badge-partial';
          else if (n.status === 'overdue') badgeClass = 'badge-overdue';
          
          let typeLabel = n.note_type === 'supplier' ? '👨‍💼 Proveedor' : '👤 Cliente';
          let termLabel = n.payment_term || '';
          if (n.payment_term === '15dias') termLabel = '15 días';
          else if (n.payment_term === '30dias') termLabel = '30 días';
          else if (n.payment_term === 'contado') termLabel = 'Contado';
          
          return `<tr>
            <td><strong>#${n.id}</strong></td>
            <td><code>${n.note_folio || ''}</code></td>
            <td>${typeLabel}</td>
            <td>${formatMoney(total)}</td>
            <td>${formatMoney(paid)}</td>
            <td style="font-weight:600; color:${remaining > 0 ? '#f59e0b' : '#10b981'};">${formatMoney(remaining)}</td>
            <td>${termLabel}</td>
            <td>${n.due_date || '-'}</td>
            <td><span class="badge ${badgeClass}">${statusText}</span></td>
          </tr>`;
        }).join('')}
      </tbody>
    </table>
  `;
}

async function registerNotePayment() {
  const noteIdVal = document.getElementById('payNoteId').value;
  const amountVal = document.getElementById('payAmount').value;
  const methodVal = document.getElementById('payMethod').value || 'cash';
  const notesVal = document.getElementById('payNotes').value;

  if (!noteIdVal || Number(noteIdVal) <= 0) {
    showAlert('Por favor, ingrese un ID de nota válido', 'error');
    return;
  }
  if (!amountVal || Number(amountVal) <= 0) {
    showAlert('Por favor, ingrese un monto de abono válido', 'error');
    return;
  }

  const payload = {
    note_id: noteIdVal,
    amount: amountVal,
    payment_method: methodVal,
    notes: notesVal
  };
  
  const res = await apiCall('/cashier.php?action=note-payment', 'POST', payload);
  if (res && res.success) {
    showAlert(`${res.message}. Saldo restante: ${formatMoney(res.remaining || 0)}`, 'success');
    document.getElementById('payNoteId').value = '';
    document.getElementById('payAmount').value = '';
    document.getElementById('payNotes').value = '';
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
    if (box) box.innerHTML = '<p class="text-muted">Sin movimientos registrados para el periodo seleccionado.</p>';
    return;
  }

  box.innerHTML = `
    <table>
      <thead>
        <tr>
          <th>Semana</th>
          <th>Entradas (+)</th>
          <th>Salidas (-)</th>
          <th>Flujo Neto</th>
        </tr>
      </thead>
      <tbody>
        ${movementRows.map((r) => {
          const totalIn = Number(r.total_in || 0);
          const totalOut = Number(r.total_out || 0);
          const net = totalIn - totalOut;
          const netColor = net >= 0 ? '#10b981' : '#ef4444';
          return `<tr>
            <td>Semana del ${r.week_start}</td>
            <td style="color:#10b981;">+${formatMoney(totalIn)}</td>
            <td style="color:#ef4444;">-${formatMoney(totalOut)}</td>
            <td style="font-weight:700; color:${netColor};">${net >= 0 ? '+' : ''}${formatMoney(net)}</td>
          </tr>`;
        }).join('')}
      </tbody>
    </table>
  `;
}

document.addEventListener('DOMContentLoaded', () => {
  setupTabs();
  refreshStatus();
  loadNotesList();
  loadWeeklyCashflow();
});
</script>
</body>
</html>
