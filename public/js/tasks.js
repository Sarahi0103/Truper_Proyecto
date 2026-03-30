/**
 * Script para gestión de tareas
 */

/**
 * Crear nueva tarea
 */
async function createTask() {
    const form = document.getElementById('taskForm');
    if (!form || !form.checkValidity()) {
        showAlert('Por favor completa todos los campos', 'warning');
        return;
    }
    
    const taskData = {
        title: document.getElementById('taskTitle').value,
        description: document.getElementById('taskDescription').value,
        assigned_to: document.getElementById('assignTo').value,
        due_date: document.getElementById('dueDate').value,
        priority: document.getElementById('priority').value
    };
    
    const response = await apiCall('/tasks.php?action=create', 'POST', taskData);
    
    if (response && response.success) {
        showAlert(response.message, 'success');
        form.reset();
        closeModal('taskModal');
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
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
        showAlert('Tarea actualizada exitosamente', 'success');
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
}

/**
 * Registrar horas de trabajo
 */
async function logTaskHours(taskId) {
    const hours = parseFloat(document.getElementById(`hours_${taskId}`)?.value || 0);
    
    if (hours <= 0) {
        showAlert('Ingresa horas válidas', 'warning');
        return;
    }
    
    const response = await apiCall('/tasks.php?action=log-hours', 'POST', {
        task_id: taskId,
        hours: hours
    });
    
    if (response && response.success) {
        showAlert(response.message, 'success');
        document.getElementById(`hours_${taskId}`).value = '';
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
}

/**
 * Filtrar tareas por estado
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

/**
 * Eliminar tarea
 */
async function deleteTask(taskId) {
    if (!confirm('¿Deseas eliminar esta tarea?')) {
        return;
    }
    
    const response = await apiCall('/tasks.php?action=delete', 'DELETE', { task_id: taskId });
    
    if (response && response.success) {
        showAlert('Tarea eliminada', 'success');
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
}

function getPriorityClass(priority) {
    if (priority === 'high' || priority === 'urgent') return 'priority-high';
    if (priority === 'low') return 'priority-low';
    return 'priority-medium';
}

async function loadTasks() {
    let response = await apiCall('/tasks.php?action=list-all');
    if (!response || !response.success) {
        response = await apiCall('/tasks.php?action=list');
    }

    const container = document.getElementById('tasksList');
    if (!container) return;

    if (!response || !response.success || !Array.isArray(response.tasks)) {
        container.innerHTML = '<p class="text-muted">No fue posible cargar tareas.</p>';
        return;
    }

    if (response.tasks.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay tareas registradas.</p>';
        return;
    }

    container.innerHTML = response.tasks.map(task => `
        <div class="task-item task-priority-${task.priority}" data-status="${task.status}" data-priority="${task.priority}">
            <div class="task-header">
                <div class="task-title">${task.title}</div>
                <span class="priority-badge ${getPriorityClass(task.priority)}">${task.priority}</span>
            </div>
            <div>${task.description}</div>
            <div class="task-details">Vence: ${task.due_date || 'Sin fecha'} | Estado: ${task.status}</div>
            <div class="task-actions">
                <button class="btn btn-small btn-secondary" onclick="updateTaskStatus(${task.id}, 'in_progress')">En progreso</button>
                <button class="btn btn-small btn-success" onclick="updateTaskStatus(${task.id}, 'completed')">Completar</button>
                <input id="hours_${task.id}" type="number" min="0" step="0.5" placeholder="Horas" style="width: 90px;">
                <button class="btn btn-small btn-primary" onclick="logTaskHours(${task.id})">Registrar horas</button>
            </div>
        </div>
    `).join('');
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

document.addEventListener('DOMContentLoaded', function() {
    loadTasks();
    loadAssignees();
});
