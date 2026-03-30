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
    
    const response = await apiCall('/tasks/create', 'POST', taskData);
    
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
    const response = await apiCall(`/tasks/${taskId}/status`, 'PUT', {
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
    
    const response = await apiCall(`/tasks/${taskId}/hours`, 'POST', {
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
    
    const response = await apiCall(`/tasks/${taskId}`, 'DELETE');
    
    if (response && response.success) {
        showAlert('Tarea eliminada', 'success');
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
}
