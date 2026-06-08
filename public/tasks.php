<?php
require_once '../config/config.php';
require_login();

$user_name = htmlspecialchars(($_SESSION['role'] ?? '') === 'admin' ? 'admin' : ($_SESSION['name'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'employee', ENT_QUOTES, 'UTF-8');
$is_admin = (($_SESSION['role'] ?? '') === 'admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Tareas - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css?v=2.2">
    <link rel="stylesheet" href="css/theme.css?v=2.5">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=2.2">
    <style>
        /* Task Cards */
        .task-item {
            background: #111111;
            border: 1.5px solid #222222;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }
        .task-item:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5); 
        }
        
        .task-title { color: #f8fafc; font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; }
        .task-details-box { background: #0a0a0a; border-radius: 12px; padding: 1.25rem; margin: 1rem 0; border: 1px solid #222222; }
        .task-details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; font-size: 0.85rem; }
        .task-label { color: #888888; margin-bottom: 4px; display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
        .task-value { color: #f8fafc; font-weight: 600; }
        
        .task-overdue { border-left: 6px solid #ef4444 !important; }
        .overdue-badge { background: #ef4444; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        
        /* Stats Cards Dark Mode */
        .card { background: #0a0a0a; border: 1.5px solid #222222; border-radius: 16px; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4); }
        .card-body { padding: 1.25rem; }
        .text-muted { color: #888888 !important; }
        
        /* Buttons and Inputs */
        .btn-priority { 
            padding: 0.6rem 1.2rem; border-radius: 8px; border: 1px solid #222222; 
            cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;
            background: #111111; color: #888888; font-size: 0.75rem; font-weight: 700; min-width: 90px;
        }
        .btn-priority.active { border-color: #ff7f00; color: #ff7f00; background: rgba(255, 127, 0, 0.1); }
        
        .btn-toggle-completed {
            background: #111111; color: #888888; border: 1px solid #222222; padding: 0.6rem 1rem;
            border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .btn-toggle-completed.active {
            background: #ff7f00; color: #000000; border-color: #ff7f00; font-weight: 700;
        }
        
        #taskSearch { 
            background: #000000; border: 1.5px solid #333333; color: white; 
            padding: 0.75rem 1rem; border-radius: 8px; width: 100%; max-width: 400px;
        }
        #taskSearch:focus { outline: none; border-color: #ff7f00; box-shadow: 0 0 0 2px rgba(255, 127, 0, 0.15); }
        
        .task-priority-urgent { border-left: 6px solid #ef4444; }
        .task-priority-high { border-left: 6px solid #f97316; }
        .task-priority-medium { border-left: 6px solid #eab308; }
        .task-priority-low { border-left: 6px solid #22c55e; }
        
        .priority-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 4px; font-weight: 700; }
        .priority-urgent { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .priority-high { background: rgba(249, 115, 22, 0.15); color: #f97316; }
        .priority-medium { background: rgba(234, 179, 8, 0.15); color: #eab308; }
        .priority-low { background: rgba(34, 197, 94, 0.15); color: #22c55e; }

        /* Time Input Box - Premium Dark Mode */
        .time-input-container {
            display: inline-flex; 
            align-items: center; 
            background: rgba(255, 255, 255, 0.04) !important; 
            border: 1px solid #334155 !important; 
            border-radius: 8px; 
            padding: 4px 10px; 
            gap: 4px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            height: 34px;
            box-sizing: border-box;
            vertical-align: middle;
        }
        .time-input-container:focus-within {
            border-color: #ff7f00 !important;
            box-shadow: 0 0 8px rgba(255, 127, 0, 0.2);
        }
        .time-input-container input {
            background: transparent !important; 
            border: none !important; 
            color: #ffffff !important; 
            width: 30px !important; 
            text-align: center; 
            font-weight: 700 !important; 
            font-size: 0.95rem !important; 
            padding: 0 !important;
            margin: 0 !important;
            height: auto !important;
        }
        .time-input-container input:focus { 
            outline: none !important; 
        }
        .time-input-container span { 
            color: #94a3b8 !important; 
            font-size: 0.8rem !important; 
            font-weight: 700; 
        }
        /* Ocultar spinners nativos de tipo number */
        .time-input-container input::-webkit-outer-spin-button,
        .time-input-container input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .time-input-container input[type=number] {
            -moz-appearance: textfield;
        }
        
        /* Botones de acción premium de tareas */
        .task-btn {
            padding: 0.5rem 1rem !important;
            font-size: 0.8rem !important;
            font-weight: 700 !important;
            border-radius: 8px !important;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            border: 1px solid transparent !important;
            min-height: 34px;
            box-sizing: border-box;
            vertical-align: middle;
        }
        .task-btn:hover {
            transform: translateY(-1px);
        }
        .task-btn-iniciar {
            background: rgba(255, 255, 255, 0.05) !important;
            border-color: rgba(255, 255, 255, 0.15) !important;
            color: #f8fafc !important;
        }
        .task-btn-iniciar:hover {
            background: rgba(255, 127, 0, 0.15) !important;
            border-color: #ff7f00 !important;
            color: #ff7f00 !important;
            box-shadow: 0 4px 12px rgba(255, 127, 0, 0.15);
        }
        .task-btn-completar {
            background: rgba(34, 197, 94, 0.1) !important;
            border-color: rgba(34, 197, 94, 0.3) !important;
            color: #22c55e !important;
        }
        .task-btn-completar:hover {
            background: #22c55e !important;
            color: #000000 !important;
            border-color: #22c55e !important;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        .task-btn-registrar {
            background: linear-gradient(135deg, #ff7f00 0%, #e26f00 100%) !important;
            color: #000000 !important;
            font-weight: 800 !important;
        }
        .task-btn-registrar:hover {
            box-shadow: 0 4px 12px rgba(255, 127, 0, 0.35);
        }
        .task-btn-eliminar {
            background: rgba(239, 68, 68, 0.1) !important;
            border-color: rgba(239, 68, 68, 0.3) !important;
            color: #ef4444 !important;
        }
        .task-btn-eliminar:hover {
            background: #ef4444 !important;
            color: #ffffff !important;
            border-color: #ef4444 !important;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body>
    <!-- HEADER -->
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
                        <?php if ($is_admin): ?><a href="cashier.php">Caja</a><?php endif; ?>
                        <?php if ($is_admin): ?><a href="admin_supply.php?nocache=true">Abastecimiento</a><?php endif; ?>
                        <?php if ($is_admin): ?><a href="tickets.php">Tickets</a><?php endif; ?>
                        <a href="tasks.php" class="active">Tareas</a>
                        <a href="analytics.php">Estadísticas</a>
                    </div>
                </div>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo $user_name; ?></div>
                <div class="user-role"><?php echo strtoupper($user_role); ?></div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="container-fluid admin-supply-shell">
            <div class="page-hero d-flex justify-between align-center">
                <div>
                    <div class="module-badge module-admin"><span class="module-glyph">TR</span> Módulo de Tareas</div>
                    <h1><?php echo $is_admin ? 'Gestión de Tareas' : 'Mis Tareas'; ?></h1>
                    <p class="text-muted">Da seguimiento a pendientes, asigna responsables y marca avances en el flujo de trabajo.</p>
                </div>
                <?php if ($is_admin): ?>
                <button class="btn btn-primary" onclick="openModal('taskModal')">
                    ➕ Nueva Tarea
                </button>
                <?php endif; ?>
            </div>

            <div id="taskSummary" class="grid grid-4" style="margin-bottom: 1rem;"></div>

            <!-- FILTROS POR PRIORIDAD -->
            <div class="card tasks-filter-card" style="margin-bottom: 2rem;">
                <div class="card-body">
                    <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center;">
                        <div style="flex: 1; min-width: 300px;">
                            <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Buscar y Filtrar</label>
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                                <input type="text" id="taskSearch" placeholder="🔍 Buscar tareas..." 
                                    oninput="renderFilteredTasks()">
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <button class="btn-priority btn-priority-all active" onclick="filterByPriority('all')">Todas</button>
                                    <button class="btn-priority btn-priority-urgent" onclick="filterByPriority('urgent')">🔴 Urgente</button>
                                    <button class="btn-priority btn-priority-high" onclick="filterByPriority('high')">🟠 Alta</button>
                                    <button class="btn-priority btn-priority-medium" onclick="filterByPriority('medium')">🟡 Media</button>
                                    <button class="btn-priority btn-priority-low" onclick="filterByPriority('low')">🟢 Baja</button>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <button id="btnToggleCompleted" class="btn-toggle-completed" onclick="toggleCompletedTasks()">
                                👁️ Mostrar completadas
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LISTA DE TAREAS -->
            <div id="tasksList">
                <p class="text-muted">Cargando tareas...</p>
            </div>
        </div>
    </main>

    <!-- MODAL NUEVA TAREA -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Crear Nueva Tarea</h2>
                <button class="modal-close" onclick="closeModal('taskModal')">×</button>
            </div>
            <div class="modal-body">
                <form id="taskForm">
                    <div class="form-group">
                        <label>Título de la Tarea</label>
                        <input type="text" id="taskTitle" required placeholder="Ej: Revisión de inventario">
                    </div>

                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea id="taskDescription" required placeholder="Describe la tarea..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Asignar a</label>
                        <select id="assignTo" required>
                            <option value="">Seleccionar empleado...</option>
                            <!-- Se cargan dinámicamente -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Fecha de Vencimiento</label>
                        <input type="date" id="dueDate" required>
                    </div>

                    <div class="form-group">
                        <label>Prioridad</label>
                        <select id="priority" required>
                            <option value="low">Baja</option>
                            <option value="medium" selected>Media</option>
                            <option value="high">Alta</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tiempo Estimado (Duración)</label>
                        <div style="display: inline-flex; gap: 0.5rem; align-items: center;">
                            <input type="number" id="estimatedHours" min="0" max="99" placeholder="H" style="width: 55px; height: 35px; background: #ffffff !important; color: #000000 !important; border: 2px solid #ff7f00 !important; border-radius: 6px; text-align: center; font-weight: 800; font-size: 1.1rem; padding: 0 !important; margin: 0;" title="Horas">
                            <span style="font-weight: bold; color: #ff7f00; font-size: 1.1rem;">h</span>
                            <input type="number" id="estimatedMins" min="0" max="59" placeholder="M" style="width: 55px; height: 35px; background: #ffffff !important; color: #000000 !important; border: 2px solid #ff7f00 !important; border-radius: 6px; text-align: center; font-weight: 800; font-size: 1.1rem; padding: 0 !important; margin: 0;" title="Minutos">
                            <span style="font-weight: bold; color: #ff7f00; font-size: 1.1rem;">m</span>
                        </div>
                        <small class="text-muted" style="display: block; margin-top: 4px;">Define cuánto tiempo crees que tomará esta tarea.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('taskModal')">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="createTask()">Crear Tarea</button>
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
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Truper Platform. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/main.js?v=2.6"></script>
    <script>
        window.TRUPER_TASKS_ROLE = '<?php echo htmlspecialchars($_SESSION['role'] ?? 'client', ENT_QUOTES, 'UTF-8'); ?>';
    </script>
    <script src="js/tasks.js?v=20260506b8"></script>
    <script>
        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }
    </script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
