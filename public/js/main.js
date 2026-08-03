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
    
    // FE-01: Use textContent to prevent XSS — message must not contain HTML
    const iconSpan = document.createElement('span');
    iconSpan.style.cssText = `font-size:1.3rem; color:${accentColor}; display:flex; align-items:center; justify-content:center;`;
    iconSpan.textContent = icon;

    const msgSpan = document.createElement('span');
    msgSpan.style.cssText = `font-weight:600; font-size:0.92rem; line-height:1.4; color:#ffffff;`;
    msgSpan.textContent = message;

    const contentDiv = document.createElement('div');
    contentDiv.style.cssText = `display:flex; align-items:center; gap:12px; flex:1;`;
    contentDiv.appendChild(iconSpan);
    contentDiv.appendChild(msgSpan);

    const closeBtn = document.createElement('span');
    closeBtn.className = 'close-alert';
    closeBtn.style.cssText = `cursor:pointer; font-size:1.25rem; opacity:0.5; transition:opacity 0.2s; padding:2px; display:flex; align-items:center; justify-content:center; color:#ffffff;`;
    closeBtn.textContent = '\u00d7';
    closeBtn.addEventListener('click', () => toast.remove());

    toast.appendChild(contentDiv);
    toast.appendChild(closeBtn);

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
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': window.csrfToken || getCookie('csrf_token') || ''
            }
        };
        
        let bodyData = data;
        if (bodyData && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
            // Add CSRF token to request data
            if (typeof bodyData === 'object' && bodyData !== null) {
                bodyData.csrf_token = window.csrfToken || getCookie('csrf_token') || '';
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
    const val = Number(amount);
    const num = Number.isFinite(val) ? val : 0;
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
}

/**
 * Crear objeto Date seguro para iOS Safari y todos los navegadores
 */
function safeNewDate(dateString) {
    if (!dateString) return new Date();
    if (dateString instanceof Date) return dateString;
    let str = String(dateString).trim();
    if (!str.includes('T')) {
        // Reemplazar guiones por barras para compatibilidad total con iOS Safari / WebKit
        str = str.replace(/-/g, '/');
    }
    return new Date(str);
}

/**
 * Formatear fecha
 */
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return safeNewDate(dateString).toLocaleDateString('es-MX', options);
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
 * Obtener valor de una cookie por su nombre
 */
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

/**
 * Configurar navegación y visibilidad de elementos para el personal (employee)
 */
function setupEmployeeNavigation() {
    // FE-09: Read role from window.userRole (injected by PHP) instead of cookie
    let role = (window.userRole || '').toLowerCase();

    const userRoleEl = document.querySelector('.user-role');
    if (!role && userRoleEl) {
        role = userRoleEl.textContent.trim().toLowerCase();
    }

    if (role === 'employee') {
        // Asegurar que el rol se muestre como PERSONAL en el DOM en vez de ADMIN
        const roleElements = document.querySelectorAll('.user-role');
        roleElements.forEach(el => {
            if (el.textContent.trim().toUpperCase() === 'ADMIN') {
                el.textContent = 'PERSONAL';
            }
        });

        // Si no existe el elemento de rol, inyectarlo en .user-info para consistencia
        if (roleElements.length === 0) {
            const userInfo = document.querySelector('.user-info');
            if (userInfo) {
                const newRoleEl = document.createElement('div');
                newRoleEl.className = 'user-role';
                newRoleEl.textContent = 'PERSONAL';
                userInfo.appendChild(newRoleEl);
            }
        }

        // Find the nav menu container
        const navMenu = document.querySelector('.nav-menu');
        if (navMenu) {
            // Check if Administration dropdown already exists
            let adminDropdown = Array.from(navMenu.querySelectorAll('.nav-dropdown')).find(dropdown => {
                const btn = dropdown.querySelector('.nav-dropdown-btn');
                return btn && btn.textContent.includes('Administración');
            });

            if (!adminDropdown) {
                // Create the Administration dropdown for employee (without Estadísticas)
                adminDropdown = document.createElement('div');
                adminDropdown.className = 'nav-dropdown';
                adminDropdown.innerHTML = `
                    <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="cashier.php">Caja</a>
                        <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                        <a href="tickets.php">Tickets</a>
                        <a href="tasks.php">Tareas</a>
                        <a href="gastos.php">Gastos</a>
                    </div>
                `;
                navMenu.appendChild(adminDropdown);
            } else {
                // If it already exists, overwrite its content to ensure it has all allowed administrative links for employee
                const contentEl = adminDropdown.querySelector('.nav-dropdown-content');
                if (contentEl) {
                    contentEl.innerHTML = `
                        <a href="cashier.php">Caja</a>
                        <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                        <a href="tickets.php">Tickets</a>
                        <a href="tasks.php">Tareas</a>
                        <a href="gastos.php">Gastos</a>
                    `;
                }
            }
        }

        // Hide quick actions and direct links to analytics.php
        const dashboardStatsLink = document.querySelector('a[href*="analytics.php"], #qa-stats');
        if (dashboardStatsLink) {
            dashboardStatsLink.remove();
        }

        // Hide "Estadísticas" link from db-admin-links if it exists
        const adminLinksStats = document.querySelector('.db-admin-links a[href*="analytics.php"]');
        if (adminLinksStats) {
            adminLinksStats.remove();
        }
    }
}

/**
 * Inicializar cuando el DOM está listo
 */
function initMain() {
    initThemeSystem();
    loadUserData();
    setupTabs();
    setupEmployeeNavigation();
    
    // FE-03: Only intercept forms explicitly marked for AJAX submission
    const forms = document.querySelectorAll('form[action][data-ajax="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
    });

    // Also intercept login/register forms by ID for backward compatibility
    ['loginForm', 'registerForm'].forEach(formId => {
        const form = document.getElementById(formId);
        if (form && form.getAttribute('action')) {
            form.addEventListener('submit', handleFormSubmit);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMain);
} else {
    initMain();
}

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

/**
 * Sistema de atajos de teclado
 */
class KeyboardShortcuts {
    constructor() {
        this.shortcuts = new Map();
        this.init();
    }

    init() {
        document.addEventListener('keydown', this.handleKeyDown.bind(this));
        this.registerDefaultShortcuts();
    }

    register(key, callback, description = '') {
        this.shortcuts.set(key, {
            callback,
            description,
            key
        });
    }

    registerDefaultShortcuts() {
        // Ctrl+K: Búsqueda
        this.register('ctrl+k', () => {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }, 'Foco en búsqueda');

        // Ctrl+/: Mostrar ayuda de atajos
        this.register('ctrl+/', () => {
            this.showHelp();
        }, 'Mostrar ayuda de atajos');

        // Escape: Cerrar modales y drawers
        this.register('escape', () => {
            this.closeModals();
        }, 'Cerrar modales');

        // Ctrl+S: Guardar (si hay formulario activo)
        this.register('ctrl+s', (e) => {
            const activeForm = document.activeElement?.closest('form');
            if (activeForm) {
                const submitBtn = activeForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    e.preventDefault();
                    submitBtn.click();
                }
            }
        }, 'Guardar formulario');

        // Ctrl+N: Nuevo producto (solo admin)
        this.register('ctrl+n', (e) => {
            if (window.location.pathname.includes('admin')) {
                e.preventDefault();
                const newProductBtn = document.querySelector('[data-action="new-product"]');
                if (newProductBtn) {
                    newProductBtn.click();
                }
            }
        }, 'Nuevo producto (admin)');

        // FE-07: Ctrl+F — do NOT intercept, let browser handle native find
        // (Removed Ctrl+F override)

        // FE-07: Ctrl+D — do NOT intercept, let browser handle native bookmark
        // (Removed Ctrl+D override)
    }

    handleKeyDown(e) {
        const key = this.getKeyString(e);
        const shortcut = this.shortcuts.get(key);

        if (shortcut) {
            e.preventDefault();
            shortcut.callback(e);
        }
    }

    getKeyString(e) {
        if (!e || !e.key) return '';
        const parts = [];

        if (e.ctrlKey) parts.push('ctrl');
        if (e.altKey) parts.push('alt');
        if (e.shiftKey) parts.push('shift');
        if (e.metaKey) parts.push('meta');

        const key = e.key.toLowerCase();
        if (key !== 'control' && key !== 'alt' && key !== 'shift' && key !== 'meta') {
            parts.push(key);
        }

        return parts.join('+');
    }

    closeModals() {
        // Cerrar modales
        const modals = document.querySelectorAll('.confirmation-modal, .modal, .drawer');
        modals.forEach(modal => {
            modal.remove();
        });

        // Cerrar dropdowns
        const dropdowns = document.querySelectorAll('.dropdown.open');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('open');
        });
    }

    showHelp() {
        const helpModal = document.createElement('div');
        helpModal.className = 'keyboard-shortcuts-help';
        helpModal.innerHTML = `
            <div class="help-overlay"></div>
            <div class="help-dialog">
                <div class="help-header">
                    <h3>Atajos de Teclado</h3>
                    <button class="btn-close-help">×</button>
                </div>
                <div class="help-body">
                    <ul class="shortcuts-list">
                        ${Array.from(this.shortcuts.entries()).map(([key, shortcut]) => `
                            <li>
                                <kbd>${this.formatKey(key)}</kbd>
                                <span>${shortcut.description}</span>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            </div>
        `;

        document.body.appendChild(helpModal);

        const closeBtn = helpModal.querySelector('.btn-close-help');
        const overlay = helpModal.querySelector('.help-overlay');

        const close = () => helpModal.remove();
        closeBtn.addEventListener('click', close);
        overlay.addEventListener('click', close);

        document.addEventListener('keydown', function escHandler(e) {
            if (e.key === 'Escape') {
                close();
                document.removeEventListener('keydown', escHandler);
            }
        });
    }

    formatKey(key) {
        return key
            .replace('ctrl', 'Ctrl')
            .replace('alt', 'Alt')
            .replace('shift', 'Shift')
            .replace('meta', '⌘')
            .split('+')
            .map(part => `<kbd>${part}</kbd>`)
            .join(' + ');
    }
}

// Estilos para ayuda de atajos
const shortcutsStyles = `
    .keyboard-shortcuts-help {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .help-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
    }

    .help-dialog {
        position: relative;
        background: #1a1a1a;
        border: 1px solid #333;
        border-radius: 16px;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        z-index: 1;
    }

    .help-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .help-header h3 {
        margin: 0;
        color: #ffffff;
        font-size: 1.5rem;
        font-weight: 700;
    }

    .btn-close-help {
        background: none;
        border: none;
        color: #888;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
    }

    .btn-close-help:hover {
        background: #333;
        color: #fff;
    }

    .shortcuts-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .shortcuts-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #222;
    }

    .shortcuts-list li:last-child {
        border-bottom: none;
    }

    .shortcuts-list kbd {
        background: #333;
        border: 1px solid #444;
        border-radius: 4px;
        padding: 0.25rem 0.5rem;
        font-family: monospace;
        font-size: 0.85rem;
        color: #fff;
    }

    .shortcuts-list span {
        color: #888;
        font-size: 0.9rem;
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = shortcutsStyles;
document.head.appendChild(styleSheet);

// Sistema global de persistencia de formularios al recargar la página
function initFormPersistence() {
    const STORAGE_PREFIX = 'truper_form_persist_';
    const currentPath = window.location.pathname;
    let isRestoring = false;

    function getInputKey(input) {
        if (!input || !input.tagName) return null;
        
        const type = (input.type || '').toLowerCase();
        if (['password', 'file', 'submit', 'button', 'reset', 'image'].includes(type)) {
            return null;
        }

        if (type === 'hidden') {
            const nameOrId = (input.name || input.id || '').toLowerCase();
            if (nameOrId.includes('csrf') || nameOrId.includes('token') || nameOrId.includes('_nocache')) {
                return null;
            }
        }

        let keyIdentifier = '';
        if (input.id) {
            keyIdentifier = `id_${input.id}`;
        } else if (input.name) {
            keyIdentifier = `name_${input.name}`;
        } else {
            const allInputs = Array.from(document.querySelectorAll('input, textarea, select'));
            const index = allInputs.indexOf(input);
            if (index !== -1) {
                keyIdentifier = `idx_${index}`;
            }
        }

        if (!keyIdentifier) return null;
        return `${STORAGE_PREFIX}${currentPath}_${keyIdentifier}`;
    }

    function saveFieldValue(input) {
        if (isRestoring) return;
        const key = getInputKey(input);
        if (!key) return;

        const type = (input.type || '').toLowerCase();
        let value = null;

        if (type === 'checkbox') {
            value = input.checked ? '1' : '0';
        } else if (type === 'radio') {
            if (input.checked) {
                value = input.value;
            } else {
                return;
            }
        } else {
            value = input.value;
        }

        try {
            if (value !== null && value !== undefined) {
                sessionStorage.setItem(key, JSON.stringify({
                    value: value,
                    type: type,
                    updated: Date.now()
                }));
            }
        } catch (e) {}
    }

    function restoreAllFields() {
        if (isRestoring) return;
        isRestoring = true;

        try {
            const inputs = document.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                const key = getInputKey(input);
                if (!key) return;

                const storedRaw = sessionStorage.getItem(key);
                if (!storedRaw) return;

                const stored = JSON.parse(storedRaw);
                const type = (input.type || '').toLowerCase();

                if (type === 'checkbox') {
                    const shouldCheck = stored.value === '1';
                    if (input.checked !== shouldCheck) {
                        input.checked = shouldCheck;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                } else if (type === 'radio') {
                    if (input.value === stored.value && !input.checked) {
                        input.checked = true;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                } else {
                    if (input.value !== stored.value && stored.value !== null && stored.value !== undefined) {
                        input.value = stored.value;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        } catch (e) {
        } finally {
            isRestoring = false;
        }
    }

    function clearSavedFields() {
        try {
            const keysToRemove = [];
            for (let i = 0; i < sessionStorage.length; i++) {
                const key = sessionStorage.key(i);
                if (key && key.startsWith(`${STORAGE_PREFIX}${currentPath}_`)) {
                    keysToRemove.push(key);
                }
            }
            keysToRemove.forEach(k => sessionStorage.removeItem(k));
        } catch (e) {}
    }

    document.addEventListener('input', (e) => {
        if (e.target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
            saveFieldValue(e.target);
        }
    }, true);

    document.addEventListener('change', (e) => {
        if (e.target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
            saveFieldValue(e.target);
        }
    }, true);

    document.addEventListener('submit', (e) => {
        clearSavedFields();
    }, true);

    restoreAllFields();

    let debounceTimeout = null;
    const observer = new MutationObserver((mutations) => {
        let hasAddedNodes = false;
        for (const mutation of mutations) {
            if (mutation.addedNodes.length > 0) {
                hasAddedNodes = true;
                break;
            }
        }
        if (hasAddedNodes) {
            if (debounceTimeout) clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                restoreAllFields();
            }, 100);
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
}

// Inicializar atajos de teclado y persistencia de formularios
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.keyboardShortcuts = new KeyboardShortcuts();
        initFormPersistence();
    });
} else {
    window.keyboardShortcuts = new KeyboardShortcuts();
    initFormPersistence();
}

/**
 * Función global de cierre de sesión
 */
function logout() {
    const logoutPath = 'api/auth.php?action=logout';
    if (typeof confirmLogout === 'function') {
        confirmLogout(logoutPath);
    } else {
        if (confirm('¿Estás seguro de que deseas cerrar tu sesión?')) {
            window.location.href = logoutPath;
        }
    }
}
