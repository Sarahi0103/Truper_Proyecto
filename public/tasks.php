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
    <link rel="stylesheet" href="css/styles.css?v=2.1">
    <link rel="stylesheet" href="css/theme.css?v=2.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css">
    <style>
        .task-overdue { border-left: 5px solid #ef4444 !important; background: #fff5f5 !important; }
        .overdue-badge { background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; margin-left: 8px; }
        .task-progress-bar { height: 6px; background: #eee; border-radius: 3px; overflow: hidden; margin-top: 8px; }
        .task-progress-fill { height: 100%; background: #3b82f6; transition: width 0.3s; }
        .task-progress-fill.warning { background: #f59e0b; }
        .task-progress-fill.danger { background: #ef4444; }
        
        .kanban-board { display: flex; gap: 1.5rem; overflow-x: auto; padding-bottom: 1rem; align-items: flex-start; }
        .kanban-column { flex: 1; min-width: 300px; background: #f8fafc; border-radius: 12px; padding: 1rem; border: 1px solid #e2e8f0; }
        .kanban-column-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0; }
        .kanban-column-title { font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem; }
        .kanban-count { background: #e2e8f0; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; }
        
        .btn-priority.active { box-shadow: 0 0 0 2px #3b82f6; }
        .view-toggle .btn.active { background: #fff; color: #3b82f6; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
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
                <a href="cart.php">Carrito</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="orders.php">Pedidos</a>
                <a href="wholesale.php">Mayoreo</a>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="cashier.php">Caja</a><?php endif; ?>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="admin_supply.php">Abastecimiento</a><?php endif; ?>
                <a href="tasks.php" class="active">Tareas</a>
                <a href="analytics.php">Estadísticas</a>
                <a href="profile.php">Perfil</a>
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
        <div class="container-fluid">
            <div class="d-flex justify-between align-center">
                <h1><?php echo $is_admin ? 'Gestión de Tareas' : 'Mis Tareas'; ?></h1>
                <?php if ($is_admin): ?>
                <button class="btn btn-primary" onclick="openModal('taskModal')">
                    ➕ Nueva Tarea
                </button>
                <?php endif; ?>
            </div>

            <?php if (!$is_admin): ?>
            <p class="text-muted" style="margin-bottom: 1rem;">Da seguimiento a tus pendientes y marca avances para mantener actualizado tu flujo de trabajo.</p>
            <?php endif; ?>

            <div id="taskSummary" class="grid grid-4" style="margin-bottom: 1rem;"></div>

            <!-- FILTROS POR PRIORIDAD -->
            <div class="card tasks-filter-card" style="margin-bottom: 2rem;">
                <div class="card-body">
                    <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center;">
                        <div style="flex: 1; min-width: 300px;">
                            <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Buscar y Filtrar</label>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                                <input type="text" id="taskSearch" placeholder="Buscar por título o descripción..." 
                                    style="padding: 0.5rem; border-radius: 6px; border: 1px solid #ddd; min-width: 250px;"
                                    oninput="renderFilteredTasks()">
                                <div style="display: flex; gap: 0.3rem;">
                                    <button class="btn-priority btn-priority-all active" onclick="filterByPriority('all')">Todas</button>
                                    <button class="btn-priority btn-priority-urgent" onclick="filterByPriority('urgent')">🔴</button>
                                    <button class="btn-priority btn-priority-high" onclick="filterByPriority('high')">🟠</button>
                                    <button class="btn-priority btn-priority-medium" onclick="filterByPriority('medium')">🟡</button>
                                    <button class="btn-priority btn-priority-low" onclick="filterByPriority('low')">🟢</button>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" id="showCompleted" onchange="toggleCompletedTasks()" />
                                <label for="showCompleted" style="cursor: pointer; margin: 0; font-size: 0.9rem;">Mostrar completadas</label>
                            </div>
                            <div class="view-toggle" style="background: #f0f0f0; padding: 4px; border-radius: 8px; display: flex;">
                                <button id="btnListView" class="btn btn-small active" onclick="toggleView('list')" style="margin:0; border-radius: 6px;">Lista</button>
                                <button id="btnKanbanView" class="btn btn-small" onclick="toggleView('kanban')" style="margin:0; border-radius: 6px;">Kanban</button>
                            </div>
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
                        <div style="display: inline-flex; gap: 0.3rem; align-items: center;">
                            <input type="number" id="estimatedHours" min="0" max="99" placeholder="H" style="width: 50px; padding: 0.4rem; text-align: center;" title="Horas">
                            <span style="font-weight: bold;">h</span>
                            <input type="number" id="estimatedMins" min="0" max="59" placeholder="M" style="width: 50px; padding: 0.4rem; text-align: center;" title="Minutos">
                            <span style="font-weight: bold;">m</span>
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
