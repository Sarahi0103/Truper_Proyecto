<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tareas - Truper Platform</title>
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
                <a href="orders.php">Pedidos</a>
                <a href="tasks.php" class="active">Tareas</a>
                <a href="analytics.php">Estadísticas</a>
                <a href="profile.php">Perfil</a>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name">Usuario</div>
                <div class="user-role">Empleado</div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="container-fluid">
            <div class="d-flex justify-between align-center">
                <h1>Gestión de Tareas</h1>
                <button class="btn btn-primary" onclick="openModal('taskModal')">
                    ➕ Nueva Tarea
                </button>
            </div>

            <!-- FILTROS -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                        <div>
                            <label>Filtrar por Estado</label>
                            <select id="taskFilter" onchange="filterTasks()">
                                <option value="">Todas</option>
                                <option value="pending">Pendiente</option>
                                <option value="in_progress">En Progreso</option>
                                <option value="completed">Completada</option>
                                <option value="cancelled">Cancelada</option>
                            </select>
                        </div>
                        <div>
                            <label>Ordenar por Prioridad</label>
                            <button class="btn btn-secondary" onclick="sortTasksByPriority()">Aplicar</button>
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
                        <label>Horas Estimadas</label>
                        <input type="number" placeholder="0.00" min="0" step="0.5">
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
    <script src="js/tasks.js"></script>
    <script>
        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }
    </script>
</body>
</html>
