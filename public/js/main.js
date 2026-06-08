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
    // Force dark theme always
    return 'dark';
}

function setThemePreference(theme) {
    // Always set to dark theme
    const next = 'dark';
    document.documentElement.setAttribute('data-theme', next);
    // Don't save to localStorage to prevent theme switching
}

function toggleTheme() {
    // Theme toggle disabled - always use dark mode
    return;
}

function ensureThemeToggleButton() {
    // Theme toggle button disabled - no button needed with dark-only mode
    return;
}

function initThemeSystem() {
    // Always initialize to dark theme
    document.documentElement.setAttribute('data-theme', 'dark');
}

/**
 * Mostrar alerta
 */
function showAlert(message, type = 'info') {
    // 1. Obtener o crear el contenedor de Toasts flotantes
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = `
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 100000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 360px;
            width: calc(100% - 48px);
            pointer-events: none;
        `;
        document.body.appendChild(toastContainer);
    }

    // 2. Crear el Toast
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} toast-item`;
    toast.style.cssText = `
        pointer-events: auto;
        margin: 0 !important;
        animation: toast-slide-in 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.55);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        background: #111111;
        color: #ffffff;
        padding: 1.1rem 1.4rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        position: relative;
    `;

    // Asignar colores e íconos según el tipo
    let icon = 'ℹ️';
    let borderColor = 'rgba(255,255,255,0.15)';
    let accentColor = '#94a3b8';
    
    if (type === 'success') {
        const isCartMsg = String(message).toLowerCase().includes('carrito') || String(message).toLowerCase().includes('carro');
        icon = isCartMsg ? '🛒' : '✅';
        borderColor = 'rgba(34, 197, 94, 0.4)';
        accentColor = '#22c55e';
        toast.style.background = '#0d1612'; // Verde oscuro sutil
    } else if (type === 'error') {
        icon = '❌';
        borderColor = 'rgba(239, 68, 68, 0.4)';
        accentColor = '#ef4444';
        toast.style.background = '#180e0e'; // Rojo oscuro sutil
    } else if (type === 'warning') {
        icon = '⚠️';
        borderColor = 'rgba(245, 158, 11, 0.4)';
        accentColor = '#f59e0b';
        toast.style.background = '#18120d'; // Amarillo/Naranja oscuro sutil
    }

    toast.style.borderColor = borderColor;
    
    toast.innerHTML = `
        <div style="display:flex; align-items:center; gap:12px; flex:1;">
            <span style="font-size:1.3rem; color:${accentColor}; display:flex; align-items:center; justify-content:center;">${icon}</span>
            <span style="font-weight:600; font-size:0.92rem; line-height:1.4; color:#ffffff;">${message}</span>
        </div>
        <span class="close-alert" onclick="this.parentElement.remove()" style="cursor:pointer; font-size:1.25rem; opacity:0.5; transition:opacity 0.2s; padding:2px; display:flex; align-items:center; justify-content:center; color:#ffffff;">×</span>
    `;

    // Inyectar animación keyframes al documento si no está agregada
    if (!document.getElementById('toast-animation-styles')) {
        const styles = document.createElement('style');
        styles.id = 'toast-animation-styles';
        styles.innerHTML = `
            @keyframes toast-slide-in {
                from { transform: translateX(50px) scale(0.95); opacity: 0; }
                to { transform: translateX(0) scale(1); opacity: 1; }
            }
            @keyframes toast-fade-out {
                to { transform: translateY(-10px) scale(0.95); opacity: 0; }
            }
            .toast-item-fadeout {
                animation: toast-fade-out 0.25s ease forwards !important;
            }
            .close-alert:hover {
                opacity: 1 !important;
                color: #ff7f00 !important;
            }
        `;
        document.head.appendChild(styles);
    }

    toastContainer.appendChild(toast);

    // Auto eliminar después de 4 segundos
    setTimeout(() => {
        if (toast.parentNode) {
            toast.classList.add('toast-item-fadeout');
            setTimeout(() => {
                if (toast.parentNode) toast.remove();
            }, 250);
        }
    }, 4000);
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
        const storedName = localStorage.getItem('userName');
        if (storedName) {
            userNameEl.textContent = storedName;
        }
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
