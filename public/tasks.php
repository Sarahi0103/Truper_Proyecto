<?php
require_once '../config/config.php';
require_login();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
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
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }
        .task-item:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); }
        
        .task-title { color: #f8fafc; font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; }
        .task-details-box { background: #0f172a; border-radius: 8px; padding: 1rem; margin: 1rem 0; border: 1px solid #334155; }
        .task-details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; font-size: 0.85rem; }
        .task-label { color: #94a3b8; margin-bottom: 2px; display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .task-value { color: #f8fafc; font-weight: 600; }
        
        .task-overdue { border-left: 6px solid #ef4444 !important; }
        .overdue-badge { background: #ef4444; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        
        /* Stats Cards Dark Mode */
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .card-body { padding: 1.25rem; }
        .text-muted { color: #94a3b8 !important; }
        
        /* Buttons and Inputs */
        .btn-priority { 
            padding: 0.6rem 1.2rem; border-radius: 8px; border: 1px solid #334155; 
            cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;
            background: #1e293b; color: #94a3b8; font-size: 0.75rem; font-weight: 700; min-width: 90px;
        }
        .btn-priority.active { border-color: #ff7f00; color: #ff7f00; background: rgba(255, 127, 0, 0.1); }
        
        /* Time Input Box - HIGH VISIBILITY */
        .time-input-container {
            display: flex; align-items: center; background: white; border: 2px solid #ff7f00; 
            border-radius: 6px; padding: 2px 6px; gap: 2px;
        }
        .time-input-container input {
            background: transparent; border: none; color: #1e293b; width: 35px; 
            text-align: center; font-weight: 800; font-size: 1rem; padding: 2px 0;
        }
        .time-input-container input:focus { outline: none; }
        .time-input-container span { color: #64748b; font-size: 0.75rem; font-weight: 800; }
        
        .btn-toggle-completed {
            background: #1e293b; color: #94a3b8; border: 1px solid #334155; padding: 0.6rem 1rem;
            border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .btn-toggle-completed.active {
            background: #ff7f00; color: white; border-color: #ff7f00;
        }
        
        #taskSearch { 
            background: #1e293b; border: 1px solid #334155; color: white; 
            padding: 0.75rem 1rem; border-radius: 8px; width: 100%; max-width: 400px;
        }
        #taskSearch:focus { outline: none; border-color: #ff7f00; box-shadow: 0 0 0 2px rgba(255, 127, 0, 0.2); }
        
        .task-priority-urgent { border-left: 6px solid #ef4444; }
        .task-priority-high { border-left: 6px solid #f97316; }
        .task-priority-medium { border-left: 6px solid #eab308; }
        .task-priority-low { border-left: 6px solid #22c55e; }
        
        .priority-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 4px; font-weight: 700; }
        .priority-urgent { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .priority-high { background: rgba(249, 115, 22, 0.15); color: #f97316; }
        .priority-medium { background: rgba(234, 179, 8, 0.15); color: #eab308; }
        .priority-low { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
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
                <div class="user-role"><?php echo ucfirst($user_role); ?></div>
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

    <script src="js/main.js"></script>
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
