// Sistema de Modales Premium
// Funciones para mostrar y ocultar modales de confirmación

function showPremiumModal(title, message, icon, onConfirm, onCancel) {
    // Crear overlay del modal
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.id = 'premiumModal';
    
    // Crear contenido del modal
    const modal = document.createElement('div');
    modal.className = 'modal-premium';
    
    modal.innerHTML = `
        <div class="modal-premium-header">
            <div class="modal-premium-icon">${icon}</div>
            <h3 class="modal-premium-title">${title}</h3>
        </div>
        <div class="modal-premium-content">
            ${message}
        </div>
        <div class="modal-premium-actions">
            <button class="modal-premium-btn modal-premium-btn-cancel" id="modalCancel">Cancelar</button>
            <button class="modal-premium-btn modal-premium-btn-confirm" id="modalConfirm">Confirmar</button>
        </div>
    `;
    
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    
    // Forzar reflow para animación
    overlay.offsetHeight;
    overlay.classList.add('active');
    
    // Event listeners
    const cancelBtn = document.getElementById('modalCancel');
    const confirmBtn = document.getElementById('modalConfirm');
    
    cancelBtn.addEventListener('click', () => {
        hidePremiumModal();
        if (onCancel) onCancel();
    });
    
    confirmBtn.addEventListener('click', () => {
        hidePremiumModal();
        if (onConfirm) onConfirm();
    });
    
    // Cerrar al hacer clic fuera del modal
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            hidePremiumModal();
            if (onCancel) onCancel();
        }
    });
    
    // Cerrar con tecla ESC
    const handleEscape = (e) => {
        if (e.key === 'Escape') {
            hidePremiumModal();
            if (onCancel) onCancel();
            document.removeEventListener('keydown', handleEscape);
        }
    };
    document.addEventListener('keydown', handleEscape);
}

function hidePremiumModal() {
    const overlay = document.getElementById('premiumModal');
    if (overlay) {
        overlay.classList.remove('active');
        setTimeout(() => {
            overlay.remove();
        }, 300);
    }
}

// Función específica para confirmar cierre de sesión
function confirmLogout(logoutUrl) {
    showPremiumModal(
        'Cerrar Sesión',
        '¿Estás seguro de que deseas cerrar tu sesión? Tendrás que iniciar sesión nuevamente para acceder a tu cuenta.',
        '🔒',
        () => {
            window.location.href = logoutUrl;
        }
    );
}

// Función para confirmar eliminación
function confirmDelete(itemName, onConfirm) {
    showPremiumModal(
        'Eliminar Elemento',
        `¿Estás seguro de que deseas eliminar "${itemName}"? Esta acción no se puede deshacer.`,
        '🗑️',
        onConfirm
    );
}

// Función para confirmar acción importante
function confirmAction(title, message, icon, onConfirm) {
    showPremiumModal(title, message, icon, onConfirm);
}
