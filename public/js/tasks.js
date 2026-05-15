/**
 * Script para gestión de tareas
 */

const TASKS_ROLE = (window.TRUPER_TASKS_ROLE || 'client').toLowerCase();
const TASKS_IS_ADMIN = TASKS_ROLE === 'admin';

// Estado global de filtros
let CURRENT_PRIORITY_FILTER = 'all';
let SHOW_COMPLETED_TASKS = false;
let ALL_TASKS = [];
let CURRENT_VIEW = 'list'; // 'list' o 'kanban'

// Mapeo de prioridades
const PRIORITY_ORDER = {
    'urgent': 0,
    'high': 1,
    'medium': 2,
    'low': 3
};

const PRIORITY_LABELS = {
    'urgent': '🔴 Urgente',
    'high': '🟠 Alta',
    'medium': '🟡 Media',
    'low': '🟢 Baja'
};

/**
 * Crear nueva tarea
 */
async function createTask() {
    const form = document.getElementById('taskForm');
    if (!form || !form.checkValidity()) {
        showAlert('Por favor completa todos los campos', 'warning');
        return;
    }
    
    if (hoursInput || minsInput) {
        const hours = parseInt(hoursInput, 10) || 0;
        const mins = parseInt(minsInput, 10) || 0;
        
        if (isNaN(hours) || hours < 0) {
            showAlert('Horas inválidas', 'warning');
            return;
        }
        
        if (isNaN(mins) || mins < 0 || mins > 59) {
            showAlert('Minutos inválidos (0-59)', 'warning');
            return;
        }
        
        estimatedHours = hours + (mins / 60);
        estimatedHours = Math.round(estimatedHours * 100) / 100;
    }
    
    const taskData = {
        title: document.getElementById('taskTitle').value,
        description: document.getElementById('taskDescription').value,
        assigned_to: document.getElementById('assignTo').value,
        due_date: document.getElementById('dueDate').value,
        priority: document.getElementById('priority').value,
        estimated_hours: estimatedHours,
        estimated_ampm: 'DURATION' // Indicamos que es duración
    };
    
    const response = await apiCall('/tasks.php?action=create', 'POST', taskData);
    
    if (response && response.success) {
        handleSuccessResponse(response, {
            scrollTarget: '#tasksList',
            successMessage: response.message || 'Tarea creada correctamente',
            onSuccess: () => {
                form.reset();
                closeModal('taskModal');
                loadTasks();
                if (TASKS_IS_ADMIN) {
                    loadAssignees();
                }
            }
        });
        return;
    }

    showAlert((response && response.message) ? response.message : 'No fue posible crear la tarea', 'error');
}

/**
 * Actualizar estado de tarea
 */
async function updateTaskStatus(taskId, newStatus) {
    const response = await apiCall('/tasks.php?action=update-status', 'PUT', {
        task_id: taskId,
        status: newStatus
    });
    
    if (response && response.success) {
        handleSuccessResponse(response, {
            scrollTarget: '#tasksList',
            successMessage: newStatus === 'completed' 
                ? '¡Tarea completada! 🎉' 
                : 'Tarea actualizada exitosamente',
            onSuccess: loadTasks
        });
        return;
    }

    showAlert((response && response.message) ? response.message : 'No fue posible actualizar la tarea', 'error');
}

/**
 * Registrar horas de trabajo
 */
function parseAmPmToMinutes(value) {
    const normalized = String(value || '').trim().toUpperCase();
    const match = normalized.match(/^(0?[1-9]|1[0-2]):([0-5][0-9])\s*(AM|PM)$/);
    if (!match) {
        return null;
    }

    let hours = parseInt(match[1], 10);
    const minutes = parseInt(match[2], 10);
    const period = match[3];

    if (period === 'AM' && hours === 12) {
        hours = 0;
    }
    if (period === 'PM' && hours !== 12) {
        hours += 12;
    }

    return (hours * 60) + minutes;
}

/**
 * Alternar AM/PM en el botón del formulario
 */
function toggleEstimatedAmPm() {
    const btn = document.getElementById('estimatedAmpm');
    if (btn) {
        btn.textContent = btn.textContent === 'AM' ? 'PM' : 'AM';
    }
}

/**
 * Alternar AM/PM en el botón
 */
function toggleAmPm(taskId) {
    const btn = document.getElementById(`ampm_${taskId}`);
    if (btn) {
        btn.textContent = btn.textContent === 'AM' ? 'PM' : 'AM';
    }
}

/**
 * Formatear horas decimales a formato visual con AM/PM (4.67 → 4h 40 AM)
 */
function formatHoursDisplay(decimalHours, ampm = 'AM') {
    if (!decimalHours || decimalHours === '-' || isNaN(decimalHours)) {
        return '-';
    }
    
    const totalHours = parseFloat(decimalHours);
    const wholeHours = Math.floor(totalHours);
    const minutes = Math.round((totalHours - wholeHours) * 60);
    
    if (wholeHours === 0 && minutes === 0) return '-';
    if (wholeHours === 0) return `${minutes} ${ampm}`;
    if (minutes === 0) return `${wholeHours}h ${ampm}`;
    
    return `${wholeHours}h ${String(minutes).padStart(2, '0')} ${ampm}`;
}

/**
 * Registrar horas de trabajo
 */
async function logTaskHours(taskId) {
    const hoursInput = document.getElementById(`hours_${taskId}`)?.value || '';
    const minsInput = document.getElementById(`mins_${taskId}`)?.value || '0';

    const hours = parseInt(hoursInput, 10) || 0;
    const mins = parseInt(minsInput, 10) || 0;

    if (hours === 0 && mins === 0) {
        showAlert('Ingresa el tiempo trabajado', 'warning');
        return;
    }

    if (isNaN(hours) || hours < 0) {
        showAlert('Horas inválidas', 'warning');
        return;
    }

    if (isNaN(mins) || mins < 0 || mins > 59) {
        showAlert('Minutos inválidos (0-59)', 'warning');
        return;
    }

    let totalHours = hours + (mins / 60);
    totalHours = Math.round(totalHours * 100) / 100;
    
    const response = await apiCall('/tasks.php?action=log-hours', 'POST', {
        task_id: taskId,
        hours: totalHours,
        actual_ampm: 'DURATION'
    });
    
    if (response && response.success) {
        handleSuccessResponse(response, {
            scrollTarget: '#tasksList',
            successMessage: response.message || `Horas registradas: ${hours}h ${String(mins).padStart(2, '0')} ${ampm}`,
            onSuccess: loadTasks
        });
        const hoursEl = document.getElementById(`hours_${taskId}`);
        const minsEl = document.getElementById(`mins_${taskId}`);
        if (hoursEl) hoursEl.value = '';
        if (minsEl) minsEl.value = '';
        return;
    }

    showAlert((response && response.message) ? response.message : 'No fue posible registrar horas', 'error');
}

/**
 * Filtrar tareas por prioridad
 */
function filterByPriority(priority) {
    CURRENT_PRIORITY_FILTER = priority;
    
    // Actualizar botones activos
    document.querySelectorAll('.btn-priority').forEach(btn => {
        btn.classList.remove('active');
    });
    
    if (priority === 'all') {
        document.querySelector('.btn-priority-all').classList.add('active');
    } else {
        document.querySelector(`.btn-priority-${priority}`).classList.add('active');
    }
    
    renderFilteredTasks();
}

/**
 * Alternar visibilidad de tareas completadas
 */
function toggleCompletedTasks() {
    SHOW_COMPLETED_TASKS = document.getElementById('showCompleted')?.checked ?? false;
    renderFilteredTasks();
}

function renderFilteredTasks() {
    const container = document.getElementById('tasksList');
    if (!container) return;

    // Filtrar tareas según prioridad
    let filteredTasks = ALL_TASKS;

    if (CURRENT_PRIORITY_FILTER !== 'all') {
        filteredTasks = filteredTasks.filter(task => task.priority === CURRENT_PRIORITY_FILTER);
    }

    // Filtrar tareas completadas
    if (!SHOW_COMPLETED_TASKS) {
        filteredTasks = filteredTasks.filter(task => task.status !== 'completed');
    }

    // Renderizar según la vista actual
    if (CURRENT_VIEW === 'kanban') {
        renderKanbanBoard(filteredTasks);
        return;
    }

    // Filtro de búsqueda por texto
    const searchTerm = document.getElementById('taskSearch')?.value.toLowerCase() || '';
    if (searchTerm) {
        filteredTasks = filteredTasks.filter(task => 
            task.title.toLowerCase().includes(searchTerm) || 
            task.description.toLowerCase().includes(searchTerm)
        );
    }

    // Agrupar por prioridad
    const groupedByPriority = {
        'urgent': [],
        'high': [],
        'medium': [],
        'low': []
    };

    filteredTasks.forEach(task => {
        if (groupedByPriority.hasOwnProperty(task.priority)) {
            groupedByPriority[task.priority].push(task);
        }
    });

    // Ordenar dentro de cada grupo por fecha vencimiento
    Object.keys(groupedByPriority).forEach(priority => {
        groupedByPriority[priority].sort((a, b) => {
            if (!a.due_date) return 1;
            if (!b.due_date) return -1;
            return new Date(a.due_date) - new Date(b.due_date);
        });
    });

    // Renderizar tareas agrupadas
    let html = '';
    
    const priorityOrder = ['urgent', 'high', 'medium', 'low'];
    for (const priority of priorityOrder) {
        const tasks = groupedByPriority[priority];
        if (tasks.length === 0) continue;

        html += `
            <div style="margin-bottom: 2rem;">
                <h3 style="color: #1e293b; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem;">
                    <span>${PRIORITY_LABELS[priority]}</span>
                    <span class="kanban-count">${tasks.length}</span>
                </h3>
                <div class="list-layout-container">
                    ${tasks.map(task => renderTaskItem(task)).join('')}
                </div>
            </div>
        `;
    }

    if (html === '') {
        if (SHOW_COMPLETED_TASKS) {
            container.innerHTML = TASKS_IS_ADMIN
                ? '<p class="text-muted">No hay tareas en esta categoría.</p>'
                : '<p class="text-muted">No tienes tareas en esta categoría por ahora.</p>';
        } else {
            container.innerHTML = TASKS_IS_ADMIN
                ? '<p class="text-muted">Todas las tareas han sido completadas. ¡Excelente trabajo! 🎉</p>'
                : '<p class="text-muted">No tienes tareas pendientes. ¡Buen trabajo! 🎉</p>';
        }
        return;
    }

    container.innerHTML = html;
}

/**
 * Renderizar un item de tarea
 */
function renderTaskItem(task) {
    const statusLabel = {
        'pending': '⏳ Pendiente',
        'in_progress': '⚙️ En Progreso',
        'completed': '✅ Completada'
    }[task.status] || task.status;

    const taskClasses = `task-item task-priority-${task.priority}${task.status === 'completed' ? ' task-completed' : ''}`;

    return `
        <div class="${taskClasses}" data-status="${task.status}" data-priority="${task.priority}" data-task-id="${task.id}">
            <div class="task-header">
                <div>
                    <div class="task-title">${task.title}</div>
                    <div class="task-details" style="margin-top: 0.5rem;">${task.description}</div>
                </div>
                <span class="priority-badge ${getPriorityClass(task.priority)}">${PRIORITY_LABELS[task.priority]}</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0; font-size: 0.9rem;">
                <div class="task-details">📅 Vence: <strong>${task.due_date || 'Sin fecha'}</strong></div>
                <div class="task-details">⏱️ Estado: <strong>${statusLabel}</strong></div>
                <div class="task-details">🕐 Estimadas: <strong>${formatHoursDisplay(task.estimated_hours, task.estimated_ampm || 'AM')}</strong></div>
                <div class="task-details">✓ Reales: <strong>${formatHoursDisplay(task.actual_hours, task.actual_ampm || 'AM')}</strong></div>
            </div>
            <div class="task-actions">
                ${taskActionButtons(task)}
            </div>
        </div>
    `;
}

/**
 * Filtrar tareas por estado (función antigua - mantener por compatibilidad)
 */
function filterTasks() {
    const filterValue = document.getElementById('taskFilter')?.value || '';
    const tasks = document.querySelectorAll('.task-item');
    
    tasks.forEach(task => {
        if (!filterValue || task.getAttribute('data-status') === filterValue) {
            task.style.display = '';
        } else {
            task.style.display = 'none';
        }
    });
}

/**
 * Ordenar tareas por prioridad
 */
function sortTasksByPriority() {
    const container = document.getElementById('tasksList');
    if (!container) return;
    
    const tasks = Array.from(container.querySelectorAll('.task-item'));
    const priorityOrder = { urgent: 0, high: 1, medium: 2, low: 3 };
    
    tasks.sort((a, b) => {
        const priorityA = priorityOrder[a.getAttribute('data-priority')] || 99;
        const priorityB = priorityOrder[b.getAttribute('data-priority')] || 99;
        return priorityA - priorityB;
    });
    
    container.innerHTML = '';
    tasks.forEach(task => container.appendChild(task));
}

function renderTaskSummary(tasks) {
    const summary = document.getElementById('taskSummary');
    if (!summary) return;

    const counts = {
        total: tasks.length,
        pending: 0,
        in_progress: 0,
        completed: 0,
        cancelled: 0
    };

    tasks.forEach((task) => {
        const status = String(task.status || '').toLowerCase();
        if (Object.prototype.hasOwnProperty.call(counts, status)) {
            counts[status] += 1;
        }
    });

    summary.innerHTML = `
        <div class="card"><div class="card-body"><div class="text-muted">Total</div><div style="font-size:1.5rem;font-weight:700;">${counts.total}</div></div></div>
        <div class="card"><div class="card-body"><div class="text-muted">Pendientes</div><div style="font-size:1.5rem;font-weight:700;">${counts.pending}</div></div></div>
        <div class="card"><div class="card-body"><div class="text-muted">En Progreso</div><div style="font-size:1.5rem;font-weight:700;">${counts.in_progress}</div></div></div>
        <div class="card"><div class="card-body"><div class="text-muted">Completadas</div><div style="font-size:1.5rem;font-weight:700;">${counts.completed}</div></div></div>
    `;
}

function taskActionButtons(task) {
    if (TASKS_IS_ADMIN) {
        return `
            <button class="btn btn-small btn-secondary" onclick="updateTaskStatus(${task.id}, 'in_progress')">En progreso</button>
            <button class="btn btn-small btn-success" onclick="updateTaskStatus(${task.id}, 'completed')">Completar</button>
            <div style="display: inline-flex; gap: 0.2rem; align-items: center; background: #eee; padding: 2px 5px; border-radius: 4px;">
                <input id="hours_${task.id}" type="number" min="0" max="99" placeholder="H" style="width: 35px; border:none; background:transparent; font-size:0.8rem; text-align:center;">
                <span style="font-size:0.7rem; font-weight:bold;">h</span>
                <input id="mins_${task.id}" type="number" min="0" max="59" placeholder="M" style="width: 35px; border:none; background:transparent; font-size:0.8rem; text-align:center;">
                <span style="font-size:0.7rem; font-weight:bold;">m</span>
            </div>
            <button class="btn btn-small btn-primary" onclick="logTaskHours(${task.id})">Registrar</button>
            <button class="btn btn-small btn-danger" onclick="deleteTask(${task.id})">Eliminar</button>
        `;
    }

    if (task.status === 'completed') {
        return '<span class="text-muted">Tarea completada</span>';
    }

    if (task.status === 'in_progress') {
        return `
            <button class="btn btn-small btn-success" onclick="updateTaskStatus(${task.id}, 'completed')">Marcar completada</button>
            <button class="btn btn-small btn-ghost" onclick="updateTaskStatus(${task.id}, 'pending')">Volver a pendiente</button>
        `;
    }

    return `<button class="btn btn-small btn-secondary" onclick="updateTaskStatus(${task.id}, 'in_progress')">Iniciar tarea</button>`;
}

/**
 * Eliminar tarea
 */
async function deleteTask(taskId) {
    if (!confirm('¿Deseas eliminar esta tarea?')) {
        return;
    }
    
    const response = await apiCall('/tasks.php?action=delete', 'POST', { task_id: taskId });
    
    if (response && response.success) {
        handleSuccessResponse(response, {
            scrollTarget: '#tasksList',
            successMessage: response.message || 'Tarea eliminada',
            onSuccess: loadTasks
        });
        return;
    }

    showAlert((response && response.message) ? response.message : 'No fue posible eliminar la tarea', 'error');
}

function getPriorityClass(priority) {
    return `priority-${priority}`;
}

async function loadTasks() {
    let response;
    if (TASKS_IS_ADMIN) {
        response = await apiCall('/tasks.php?action=list-all');
        if (!response || !response.success) {
            response = await apiCall('/tasks.php?action=list');
        }
    } else {
        response = await apiCall('/tasks.php?action=list');
    }

    const container = document.getElementById('tasksList');
    if (!container) return;

    if (!response || !response.success || !Array.isArray(response.tasks)) {
        container.innerHTML = '<p class="text-muted">No fue posible cargar tareas.</p>';
        renderTaskSummary([]);
        return;
    }

    // Guardar todas las tareas en variable global
    ALL_TASKS = response.tasks;

    // Renderizar resumen
    renderTaskSummary(response.tasks);

    // Renderizar tareas filtradas
    renderFilteredTasks();
}

async function loadAssignees() {
    const response = await apiCall('/tasks.php?action=assignees');
    const select = document.getElementById('assignTo');
    if (!select || !response || !response.success || !Array.isArray(response.users)) return;

    const options = response.users.map(u =>
        `<option value="${u.id}">${u.first_name} ${u.last_name} (${u.role})</option>`
    ).join('');

    select.innerHTML = '<option value="">Seleccionar empleado...</option>' + options;
}

/**
 * Cambiar vista (Lista / Kanban)
 */
function toggleView(view) {
    CURRENT_VIEW = view;
    
    // Actualizar botones UI
    document.getElementById('btnListView').classList.toggle('active', view === 'list');
    document.getElementById('btnKanbanView').classList.toggle('active', view === 'kanban');
    
    renderFilteredTasks();
}

/**
 * Renderizar tablero Kanban
 */
function renderKanbanBoard(tasks) {
    const container = document.getElementById('tasksList');
    if (!container) return;

    // Filtro de búsqueda por texto
    const searchTerm = document.getElementById('taskSearch')?.value.toLowerCase() || '';
    let filtered = tasks;
    if (searchTerm) {
        filtered = filtered.filter(task => 
            task.title.toLowerCase().includes(searchTerm) || 
            task.description.toLowerCase().includes(searchTerm)
        );
    }

    const columns = {
        'pending': { title: 'Pendientes', icon: '⏳', tasks: [] },
        'in_progress': { title: 'En Progreso', icon: '⚙️', tasks: [] },
        'completed': { title: 'Completadas', icon: '✅', tasks: [] }
    };

    filtered.forEach(task => {
        if (columns[task.status]) {
            columns[task.status].tasks.push(task);
        }
    });

    let html = `<div class="kanban-board">`;
    
    for (const [id, col] of Object.entries(columns)) {
        html += `
            <div class="kanban-column" id="col_${id}">
                <div class="kanban-column-header">
                    <div class="kanban-column-title">
                        <span>${col.icon}</span>
                        <span>${col.title}</span>
                    </div>
                    <span class="kanban-count">${col.tasks.length}</span>
                </div>
                <div class="kanban-tasks-container">
                    ${col.tasks.map(task => renderTaskItem(task)).join('')}
                    ${col.tasks.length === 0 ? '<p style="text-align:center; color:#94a3b8; font-size:0.85rem; margin-top:2rem;">Sin tareas</p>' : ''}
                </div>
            </div>
        `;
    }
    
    html += `</div>`;
    container.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', function() {
    loadTasks();
    if (TASKS_IS_ADMIN) {
        loadAssignees();
    }
});
