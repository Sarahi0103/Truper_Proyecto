/**
 * Script principal de Truper Platform
 * Maneja funcionalidad general del sitio
 */

// Configuración global
const APP = {
    apiUrl: '/api',
    timeout: 5000
};

function getThemePreference() {
    return localStorage.getItem('theme') || 'light';
}

function setThemePreference(theme) {
    const next = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);

    const toggleLabel = document.querySelector('[data-theme-toggle-label]');
    if (toggleLabel) {
        toggleLabel.textContent = next === 'dark' ? 'Modo obscuro' : 'Modo claro';
    }
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || getThemePreference();
    setThemePreference(current === 'dark' ? 'light' : 'dark');
}

function ensureThemeToggleButton() {
    let wrap = document.querySelector('.theme-toggle');
    const header = document.querySelector('header');
    const preferredTargets = [
        document.querySelector('header .header-content'),
        document.querySelector('header .header-actions'),
        document.querySelector('header .user-menu'),
        document.querySelector('.auth-form-wrap')
    ].filter(Boolean);
    const target = preferredTargets[0] || header || document.body;

    if (!wrap) {
        wrap = document.createElement('div');
        wrap.className = 'theme-toggle';
        wrap.innerHTML = '<button type="button" data-theme-toggle-btn><span data-theme-toggle-label>Modo obscuro</span></button>';
    }

    const btn = wrap.querySelector('button');
    if (btn) {
        btn.style.setProperty('background', '#111111', 'important');
        btn.style.setProperty('color', '#ffffff', 'important');
        btn.style.setProperty('border-color', '#111111', 'important');
    }

    if (target && wrap.parentElement !== target) {
        target.prepend(wrap);
    }

    wrap.classList.toggle('theme-toggle-inline', target !== document.body);

    if (btn && !btn.__themeBound) {
        btn.removeAttribute('onclick');
        btn.addEventListener('click', toggleTheme);
        btn.__themeBound = true;
    }
}

function initThemeSystem() {
    setThemePreference(getThemePreference());
    ensureThemeToggleButton();
}

/**
 * Mostrar alerta
 */
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        ${message}
        <span class="close-alert" onclick="this.parentElement.remove()">×</span>
    `;
    
    const container = document.querySelector('main') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

/**
 * Función para debouncing de eventos (optimización)
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function handleSuccessResponse(response, options = {}) {
    if (!response || !response.success) {
        return false;
    }

    const successMessage = options.successMessage || response.message || 'Operación completada';
    if (options.notify !== false) {
        showAlert(successMessage, 'success');
    }

    const redirectTarget = options.redirect || response.redirect || '';
    const reloadAfterSuccess = options.reloadAfterSuccess || Boolean(response.reload);
    const scrollTarget = options.scrollTarget || response.scroll_to || response.scrollTarget || '';
    const tabTarget = options.tabTarget || response.tab || '';

    const finish = () => {
        if (reloadAfterSuccess) {
            window.location.reload();
            return;
        }

        if (redirectTarget) {
            window.location.href = redirectTarget;
            return;
        }

        if (typeof options.onSuccess === 'function') {
            options.onSuccess(response);
        }

        if (tabTarget) {
            const tabButton = document.querySelector(`[data-tab="${tabTarget}"]`);
            if (tabButton) {
                tabButton.click();
            }
        }

        if (scrollTarget) {
            const target = document.querySelector(scrollTarget) || document.getElementById(scrollTarget.replace(/^#/, ''));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

    };

    const delay = typeof options.successDelay === 'number' ? options.successDelay : 900;
    window.setTimeout(finish, delay);
    return true;
}

/**
 * Hacer petición AJAX
 */
async function apiCall(endpoint, method = 'GET', data = null, options = {}) {
    const silent = typeof options.silent === 'boolean' ? options.silent : method === 'GET';
    try {
        const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
        const fetchOptions = {
            method: method,
            credentials: 'include',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        let bodyData = data;
        if (bodyData && (method === 'POST' || method === 'PUT')) {
            // Add CSRF token to request data
            if (typeof bodyData === 'object' && bodyData !== null) {
                bodyData.csrf_token = window.csrfToken || '';
            }
            fetchOptions.body = JSON.stringify(bodyData);
        }
        
        const response = await fetch(`${APP.apiUrl}${normalizedEndpoint}`, fetchOptions);
        
        if (!response.ok) {
            throw new Error(`Error ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();
        if (options.autoHandleSuccess) {
            handleSuccessResponse(result, options);
        }
        return result;
    } catch (error) {
        console.error('API Error:', error);
        if (!silent) {
            showAlert('Error al procesar la solicitud. Intenta de nuevo.', 'error');
        }
        return null;
    }
}

/**
 * Formatear moneda
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP'
    }).format(amount);
}

/**
 * Formatear fecha
 */
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('es-CL', options);
}

/**
 * Validar formulario
 */
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    return form.checkValidity();
}

/**
 * Cargar datos del usuario desde sesión
 */
function loadUserData() {
    const userNameEl = document.querySelector('.user-name');
    if (userNameEl) {
        userNameEl.textContent = localStorage.getItem('userName') || 'Usuario';
    }
}

/**
 * Manejo de pestañas (tabs)
 */
function setupTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Desactivar todos los tabs
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Activar tab seleccionado
            this.classList.add('active');
            document.getElementById(tabName).classList.add('active');
        });
    });
}

/**
 * Manejo de modales
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Cerrar modal al hacer clic fuera
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

// Cerrar modal con botón de cerrar
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-close')) {
        const modal = e.target.closest('.modal');
        if (modal) {
            modal.classList.remove('active');
        }
    }
});

/**
 * Inicializar cuando el DOM está listo
 */
document.addEventListener('DOMContentLoaded', function() {
    initThemeSystem();
    loadUserData();
    setupTabs();
    
    // Agregar listener para formularios
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
    });
});

/**
 * Manejo genérico de envío de formularios
 */
async function handleFormSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const action = form.getAttribute('action');
    const method = form.getAttribute('method') || 'POST';
    const redirectTarget = form.dataset.successRedirect || '';
    const scrollTarget = form.dataset.successScroll || '';
    const tabTarget = form.dataset.successTab || '';
    const successMessage = form.dataset.successMessage || '';
    const reloadAfterSuccess = form.dataset.successReload === 'true';
    
    if (!action) {
        showAlert('Formulario sin acción configurada', 'error');
        return;
    }
    
    // Mostrar cargando
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="loading"></span> Procesando...';
    
    try {
        const response = await fetch(action, {
            method: method,
            body: formData,
            credentials: 'include',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            const mergedOptions = {
                redirect: redirectTarget || result.redirect || '',
                scrollTarget,
                tabTarget,
                reloadAfterSuccess,
                successMessage: successMessage || result.message || 'Operación completada',
                successDelay: Number(form.dataset.successDelay || 900),
                onSuccess: () => {
                    form.reset();
                }
            };

            if (form.id === 'registerForm' || form.id === 'loginForm') {
                mergedOptions.successDelay = Number(form.dataset.successDelay || 1400);
            }

            handleSuccessResponse(result, mergedOptions);
        } else {
            showAlert(result.message || 'Error procesando el formulario', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error al procesar el formulario', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

/**
 * Exportar a CSV
 */
function exportToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        let csvRow = [];
        row.querySelectorAll('td, th').forEach(cell => {
            csvRow.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
        });
        csv.push(csvRow.join(','));
    });
    
    const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
    const link = document.createElement('a');
    link.setAttribute('href', encodeURI(csvContent));
    link.setAttribute('download', filename);
    link.click();
}

/**
 * Imprimir elemento
 */
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', '', 'height=400,width=800');
    printWindow.document.write(element.innerHTML);
    printWindow.document.close();
    printWindow.print();
}

/**
 * Confirmación de eliminación
 */
function confirmDelete(message = '¿Estás seguro de que deseas eliminar esto?') {
    return confirm(message);
}

/**
 * Formato de número con separadores
 */
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

/**
 * Validar entrada email
 */
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
