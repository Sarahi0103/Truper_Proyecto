/**
 * Sistema de confirmación para acciones destructivas
 * Previene eliminaciones accidentales con confirmaciones claras
 */

class ConfirmationManager {
    constructor() {
        this.init();
    }

    init() {
        // Interceptar todos los botones y enlaces con data-confirm
        this.setupConfirmations();
        this.setupFormConfirmations();
        this.setupBulkActions();
    }

    setupConfirmations() {
        // Confirmaciones para botones de eliminación
        document.addEventListener('click', (e) => {
            const button = e.target.closest('[data-confirm]');
            if (!button) return;

            const message = button.dataset.confirm;
            const title = button.dataset.confirmTitle || 'Confirmar acción';
            const danger = button.dataset.confirmDanger === 'true';

            e.preventDefault();

            this.showConfirmation(title, message, danger)
                .then(confirmed => {
                    if (confirmed) {
                        this.executeAction(button);
                    }
                });
        });
    }

    setupFormConfirmations() {
        // Confirmaciones para formularios con data-confirm
        document.addEventListener('submit', (e) => {
            const form = e.target;
            const message = form.dataset.confirm;
            
            if (!message) return;

            e.preventDefault();

            const title = form.dataset.confirmTitle || 'Confirmar acción';
            const danger = form.dataset.confirmDanger === 'true';

            this.showConfirmation(title, message, danger)
                .then(confirmed => {
                    if (confirmed) {
                        form.submit();
                    }
                });
        });
    }

    setupBulkActions() {
        // Confirmaciones para acciones en lote
        document.addEventListener('click', (e) => {
            const button = e.target.closest('[data-bulk-confirm]');
            if (!button) return;

            const message = button.dataset.bulkConfirm;
            const count = this.getSelectedCount(button);
            
            if (count === 0) {
                this.showToast('No hay elementos seleccionados', 'warning');
                return;
            }

            const fullMessage = message.replace('{count}', count);
            e.preventDefault();

            this.showConfirmation('Confirmar acción en lote', fullMessage, true)
                .then(confirmed => {
                    if (confirmed) {
                        this.executeAction(button);
                    }
                });
        });
    }

    getSelectedCount(button) {
        // Buscar checkboxes seleccionados en el contexto del botón
        const container = button.closest('table, .list-container, .grid-container');
        if (!container) return 0;

        const checkboxes = container.querySelectorAll('input[type="checkbox"]:checked');
        return checkboxes.length;
    }

    showConfirmation(title, message, danger = false) {
        return new Promise((resolve) => {
            // Crear modal de confirmación
            const modal = document.createElement('div');
            modal.className = 'confirmation-modal';
            modal.innerHTML = `
                <div class="confirmation-overlay"></div>
                <div class="confirmation-dialog ${danger ? 'danger' : ''}">
                    <div class="confirmation-header">
                        <h3>${this.escapeHtml(title)}</h3>
                    </div>
                    <div class="confirmation-body">
                        <p>${this.escapeHtml(message)}</p>
                        ${danger ? '<p class="confirmation-warning">⚠️ Esta acción no se puede deshacer</p>' : ''}
                    </div>
                    <div class="confirmation-footer">
                        <button type="button" class="btn btn-secondary confirmation-cancel">Cancelar</button>
                        <button type="button" class="btn ${danger ? 'btn-danger' : 'btn-primary'} confirmation-confirm">
                            ${danger ? 'Eliminar' : 'Confirmar'}
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Animación de entrada
            requestAnimationFrame(() => {
                modal.classList.add('active');
            });

            // Event listeners
            const cancelBtn = modal.querySelector('.confirmation-cancel');
            const confirmBtn = modal.querySelector('.confirmation-confirm');
            const overlay = modal.querySelector('.confirmation-overlay');

            const cleanup = () => {
                modal.classList.remove('active');
                setTimeout(() => modal.remove(), 300);
            };

            cancelBtn.addEventListener('click', () => {
                cleanup();
                resolve(false);
            });

            confirmBtn.addEventListener('click', () => {
                cleanup();
                resolve(true);
            });

            overlay.addEventListener('click', () => {
                cleanup();
                resolve(false);
            });

            // Cerrar con ESC
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    cleanup();
                    resolve(false);
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        });
    }

    executeAction(button) {
        // Ejecutar la acción original
        if (button.tagName === 'A') {
            // Es un enlace
            window.location.href = button.href;
        } else if (button.tagName === 'BUTTON' || button.tagName === 'INPUT') {
            // Es un botón
            if (button.type === 'submit') {
                button.form.submit();
            } else {
                // Click programático
                button.click();
            }
        }
    }

    showToast(message, type = 'info') {
        // Mostrar toast de notificación
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Estilos CSS para el modal de confirmación
const confirmationStyles = `
    .confirmation-modal {
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

    .confirmation-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
    }

    .confirmation-dialog {
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

    .confirmation-dialog.danger {
        border-color: #e74c3c;
    }

    .confirmation-header h3 {
        margin: 0 0 1rem 0;
        color: #ffffff;
        font-size: 1.5rem;
        font-weight: 700;
    }

    .confirmation-body p {
        color: #aaaaaa;
        line-height: 1.6;
        margin: 0 0 1rem 0;
    }

    .confirmation-warning {
        color: #e74c3c;
        font-weight: 600;
        margin-top: 1rem !important;
    }

    .confirmation-footer {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 1.5rem;
    }

    .confirmation-modal:not(.active) {
        opacity: 0;
        pointer-events: none;
    }

    .confirmation-modal.active {
        opacity: 1;
        pointer-events: auto;
    }

    .confirmation-dialog {
        transform: scale(0.9);
        transition: transform 0.3s ease;
    }

    .confirmation-modal.active .confirmation-dialog {
        transform: scale(1);
    }

    .toast {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: #ffffff;
        font-weight: 600;
        z-index: 10001;
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.3s ease;
    }

    .toast.show {
        opacity: 1;
        transform: translateY(0);
    }

    .toast-info {
        background: #3498db;
    }

    .toast-warning {
        background: #f39c12;
    }

    .toast-error {
        background: #e74c3c;
    }

    .toast-success {
        background: #2ecc71;
    }
`;

// Inyectar estilos
const styleSheet = document.createElement('style');
styleSheet.textContent = confirmationStyles;
document.head.appendChild(styleSheet);

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new ConfirmationManager();
    });
} else {
    new ConfirmationManager();
}

// Exportar para uso global
window.ConfirmationManager = ConfirmationManager;
