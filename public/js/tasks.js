/**
 * Script para gestión de tareas - Versión DARK MODE Simplificada
 */

const TASKS_ROLE = (window.TRUPER_TASKS_ROLE || 'client').toLowerCase();
const TASKS_IS_ADMIN = TASKS_ROLE === 'admin';

// Estado global
let CURRENT_PRIORITY_FILTER = 'all';
let SHOW_COMPLETED_TASKS = false;
let ALL_TASKS = [];

const PRIORITY_LABELS = {
    'urgent': 'URGENTE',
    'high': 'ALTA',
    'medium': 'MEDIA',
    'low': 'BAJA'
};

/**
 * Cargar tareas desde la API
 */
async function loadTasks() {
    const action = TASKS_IS_ADMIN ? 'list-all' : 'list';
    const response = await apiCall(`/tasks.php?action=${action}`);
    if (response && response.success && Array.isArray(response.tasks)) {
        ALL_TASKS = response.tasks;
        renderFilteredTasks();
    }
}

/**
 * Renderizar la lista de tareas
 */
function renderFilteredTasks() {
    const container = document.getElementById('tasksList');
    if (!container) return;

    // 1. Filtrar
    let filtered = ALL_TASKS;
    
    // Filtro prioridad
    if (CURRENT_PRIORITY_FILTER !== 'all') {
        filtered = filtered.filter(t => t.priority === CURRENT_PRIORITY_FILTER);
    }
    
    // Filtro completadas
    if (!SHOW_COMPLETED_TASKS) {
        filtered = filtered.filter(t => t.status !== 'completed');
    }
    
    // Filtro búsqueda
    const searchTerm = document.getElementById('taskSearch')?.value.toLowerCase() || '';
    if (searchTerm) {
        filtered = filtered.filter(t => 
            t.title.toLowerCase().includes(searchTerm) || 
            t.description.toLowerCase().includes(searchTerm)
        );
    }

    // 2. Ordenar (Urgentes primero, luego por fecha)
    const priorityOrder = { 'urgent': 0, 'high': 1, 'medium': 2, 'low': 3 };
    filtered.sort((a, b) => {
        if (a.priority !== b.priority) return priorityOrder[a.priority] - priorityOrder[b.priority];
        return new Date(a.due_date || '9999-12-31') - new Date(b.due_date || '9999-12-31');
    });

    // 3. Renderizar
    if (filtered.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding: 4rem; color: #94a3b8;">No se encontraron tareas con estos filtros.</div>';
    } else {
        container.innerHTML = filtered.map(t => renderTaskCard(t)).join('');
    }
    
    updateSummary();
}

/**
 * Renderizar una tarjeta de tarea individual
 */
function renderTaskCard(task) {
    const isOverdue = task.status !== 'completed' && task.due_date && new Date(task.due_date) < new Date().setHours(0,0,0,0);
    const statusText = { 'pending': 'Pendiente', 'in_progress': 'En curso', 'completed': 'Finalizada' }[task.status];
    
    const est = parseFloat(task.estimated_hours || 0);
    const act = parseFloat(task.actual_hours || 0);
    const progress = est > 0 ? Math.min(100, (act / est) * 100) : 0;
    const progressClass = progress > 100 ? 'danger' : (progress > 85 ? 'warning' : '');

    return `
        <div class="task-item ${isOverdue ? 'task-overdue' : ''} task-priority-${task.priority}">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="task-title">${task.title}</span>
                        ${isOverdue ? '<span class="overdue-badge">Atrasada</span>' : ''}
                    </div>
                    <div style="color: #94a3b8; font-size: 0.85rem; margin-top: 4px;">${task.description}</div>
                </div>
                <span class="priority-badge priority-${task.priority}">${PRIORITY_LABELS[task.priority]}</span>
            </div>

            <div class="task-details-box" style="margin: 0.5rem 0 1.25rem 0;">
                <div class="task-details-grid">
                    <div>
                        <span class="task-label">Fecha Límite</span>
                        <span class="task-value">📅 ${task.due_date || 'Sin fecha'}</span>
                    </div>
                    <div>
                        <span class="task-label">Estado</span>
                        <span class="task-value">⏱️ ${statusText}</span>
                    </div>
                    <div>
                        <span class="task-label">Estimado</span>
                        <span class="task-value">⏳ ${formatTime(task.estimated_hours)}</span>
                    </div>
                    <div>
                        <span class="task-label">Real</span>
                        <span class="task-value">✅ ${formatTime(task.actual_hours)}</span>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; align-items: center;">
                ${renderActions(task)}
            </div>
        </div>
    `;
}

function renderActions(task) {
    if (TASKS_IS_ADMIN) {
        return `
            ${task.status !== 'in_progress' ? `<button class="btn btn-small btn-secondary" onclick="updateStatus(${task.id}, 'in_progress')">Iniciar</button>` : ''}
            ${task.status !== 'completed' ? `<button class="btn btn-small btn-success" onclick="updateStatus(${task.id}, 'completed')">Completar</button>` : ''}
            
            <div class="time-input-container">
                <input id="h_${task.id}" type="number" placeholder="0">
                <span>h</span>
                <input id="m_${task.id}" type="number" placeholder="0" max="59">
                <span>m</span>
            </div>
            
            <button class="btn btn-small btn-primary" style="background:#ff7f00; border-color:#ff7f00; color:white; font-weight:700;" onclick="logTime(${task.id})">Registrar</button>
            <button class="btn btn-small btn-danger" onclick="deleteT(${task.id})">Eliminar</button>
        `;
    }
    
    if (task.status === 'completed') return '<span style="color: #22c55e; font-size: 0.8rem; font-weight: 700;">✓ Tarea Finalizada</span>';
    return `<button class="btn btn-small ${task.status === 'in_progress' ? 'btn-success' : 'btn-secondary'}" 
        onclick="updateStatus(${task.id}, '${task.status === 'in_progress' ? 'completed' : 'in_progress'}')">
        ${task.status === 'in_progress' ? 'Completar' : 'Iniciar'}
    </button>`;
}

// Auxiliares
function formatTime(decimal) {
    if (!decimal || isNaN(decimal)) return '-';
    const totalMin = Math.round(decimal * 60);
    const h = Math.floor(totalMin / 60);
    const m = totalMin % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

async function updateStatus(id, status) {
    const res = await apiCall('/tasks.php?action=update-status', 'PUT', { task_id: id, status });
    if (res?.success) loadTasks();
}

async function logTime(id) {
    const h = parseInt(document.getElementById(`h_${id}`).value || 0);
    const m = parseInt(document.getElementById(`m_${id}`).value || 0);
    if (h === 0 && m === 0) return showAlert('Ingresa tiempo válido', 'warning');
    
    const decimal = Math.round((h + m/60) * 100) / 100;
    const res = await apiCall('/tasks.php?action=log-hours', 'POST', { task_id: id, hours: decimal, actual_ampm: 'DURATION' });
    if (res?.success) loadTasks();
}

async function deleteT(id) {
    if (confirm('¿Eliminar tarea?')) {
        const res = await apiCall('/tasks.php?action=delete', 'POST', { task_id: id });
        if (res?.success) loadTasks();
    }
}

function filterByPriority(p) {
    CURRENT_PRIORITY_FILTER = p;
    document.querySelectorAll('.btn-priority').forEach(b => b.classList.remove('active'));
    document.querySelector(`.btn-priority-${p}`).classList.add('active');
    renderFilteredTasks();
}

function toggleCompletedTasks() {
    SHOW_COMPLETED_TASKS = !SHOW_COMPLETED_TASKS;
    const btn = document.getElementById('btnToggleCompleted');
    if (btn) {
        btn.classList.toggle('active', SHOW_COMPLETED_TASKS);
        btn.innerHTML = SHOW_COMPLETED_TASKS ? '🙈 Ocultar completadas' : '👁️ Mostrar completadas';
    }
    renderFilteredTasks();
}

function updateSummary() {
    const counts = { total: ALL_TASKS.length, p: 0, i: 0, c: 0 };
    ALL_TASKS.forEach(t => {
        if (t.status === 'pending') counts.p++;
        else if (t.status === 'in_progress') counts.i++;
        else if (t.status === 'completed') counts.c++;
    });
    
    const sum = document.getElementById('taskSummary');
    if (sum) {
        sum.innerHTML = `
            <div class="card"><div class="card-body"><div class="text-muted">Total</div><div style="font-size:1.5rem;font-weight:700;">${counts.total}</div></div></div>
            <div class="card"><div class="card-body"><div class="text-muted">Pendientes</div><div style="font-size:1.5rem;font-weight:700;">${counts.p}</div></div></div>
            <div class="card"><div class="card-body"><div class="text-muted">En Curso</div><div style="font-size:1.5rem;font-weight:700;">${counts.i}</div></div></div>
            <div class="card"><div class="card-body"><div class="text-muted">Listas</div><div style="font-size:1.5rem;font-weight:700;">${counts.c}</div></div></div>
        `;
    }
}

async function loadAssignees() {
    const res = await apiCall('/tasks.php?action=assignees');
    const sel = document.getElementById('assignTo');
    if (sel && res?.success) {
        sel.innerHTML = '<option value="">Asignar a...</option>' + 
            res.users.map(u => `<option value="${u.id}">${u.first_name} ${u.last_name}</option>`).join('');
    }
}

async function createTask() {
    const title = document.getElementById('taskTitle').value;
    const desc = document.getElementById('taskDescription').value;
    const assigned = document.getElementById('assignTo').value;
    const date = document.getElementById('dueDate').value;
    const prio = document.getElementById('priority').value;
    const h = parseInt(document.getElementById('estimatedHours').value || 0);
    const m = parseInt(document.getElementById('estimatedMins').value || 0);
    
    if (!title || !desc) return showAlert('Título y descripción obligatorios', 'warning');
    
    const decimal = Math.round((h + m/60) * 100) / 100;
    const res = await apiCall('/tasks.php?action=create', 'POST', {
        title, description: desc, assigned_to: assigned, due_date: date, priority: prio, estimated_hours: decimal, estimated_ampm: 'DURATION'
    });
    
    if (res?.success) {
        closeModal('taskModal');
        document.getElementById('taskForm').reset();
        loadTasks();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadTasks();
    if (TASKS_IS_ADMIN) loadAssignees();
});
