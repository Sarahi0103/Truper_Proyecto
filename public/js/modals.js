// Sistema de Modales Premium - Ferretería FOX / Truper Platform
// Proporciona modales elegantes, interactivos y con cierre inmediato en todas las interfaces

function hidePremiumModal() {
    const overlays = document.querySelectorAll('.modal-overlay, #premiumModal');
    overlays.forEach(overlay => {
        overlay.classList.remove('active');
        overlay.style.display = 'none';
        try { overlay.remove(); } catch (e) {}
    });
}

function showPremiumModal(title, message, icon, onConfirm, onCancel, confirmText = 'Aceptar', cancelText = 'Cancelar') {
    // Eliminar cualquier modal previo
    hidePremiumModal();

    let iconDisplay = icon;
    if (icon === 'warning') iconDisplay = '⚠️';
    else if (icon === 'error' || icon === 'danger') iconDisplay = '❌';
    else if (icon === 'success') iconDisplay = '✅';
    else if (icon === 'info') iconDisplay = 'ℹ️';

    // Crear overlay del modal
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay active';
    overlay.id = 'premiumModal';
    overlay.style.cssText = `
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.75);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 999999;
        padding: 1rem;
        box-sizing: border-box;
    `;

    // Crear contenido del modal
    const modal = document.createElement('div');
    modal.className = 'modal-premium';
    modal.style.cssText = `
        background: #141419;
        border: 1px solid rgba(255, 127, 0, 0.35);
        border-radius: 16px;
        max-width: 480px;
        width: 100%;
        padding: 1.75rem;
        box-shadow: 0 25px 60px rgba(0,0,0,0.8), 0 0 30px rgba(255, 127, 0, 0.15);
        color: #ffffff;
        position: relative;
        animation: modalScaleIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        box-sizing: border-box;
    `;

    const showCancel = onCancel !== null && cancelText !== null && cancelText !== '';

    modal.innerHTML = `
        <button type="button" class="modal-close-x" style="
            position: absolute; top: 14px; right: 16px;
            background: none; border: none; color: #888;
            font-size: 1.5rem; line-height: 1; cursor: pointer;
            padding: 4px 8px; border-radius: 6px; transition: all 0.2s;
        " onmouseenter="this.style.color='#fff'; this.style.background='rgba(255,255,255,0.1)';" onmouseleave="this.style.color='#888'; this.style.background='none';">&times;</button>
        
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:1rem;">
            ${iconDisplay ? `<span style="font-size:1.8rem; line-height:1;">${iconDisplay}</span>` : ''}
            <h3 style="margin:0; font-size:1.25rem; font-weight:800; color:#ffffff; letter-spacing:-0.01em;">${title}</h3>
        </div>
        
        <div style="font-size:0.95rem; color:#cbd5e1; line-height:1.6; margin-bottom:1.5rem; word-break:break-word;">
            ${message}
        </div>
        
        <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
            ${showCancel ? `<button type="button" class="btn-modal-cancel" style="
                background: #22222a; color: #e2e8f0; border: 1px solid #3a3a48;
                padding: 10px 20px; border-radius: 8px; font-weight: 700;
                font-size: 0.9rem; cursor: pointer; transition: all 0.2s;
            ">${cancelText}</button>` : ''}
            <button type="button" class="btn-modal-confirm" style="
                background: linear-gradient(135deg, #ff8f00, #ff6600);
                color: #ffffff; border: none; padding: 10px 24px;
                border-radius: 8px; font-weight: 800; font-size: 0.9rem;
                cursor: pointer; box-shadow: 0 4px 15px rgba(255,102,0,0.35);
                transition: all 0.2s;
            ">${confirmText}</button>
        </div>
    `;

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    const closeBtn = modal.querySelector('.modal-close-x');
    const cancelBtn = modal.querySelector('.btn-modal-cancel');
    const confirmBtn = modal.querySelector('.btn-modal-confirm');

    const handleClose = () => {
        hidePremiumModal();
        if (onCancel) onCancel();
    };

    const handleConfirm = () => {
        hidePremiumModal();
        if (onConfirm) onConfirm();
    };

    if (closeBtn) closeBtn.onclick = handleClose;
    if (cancelBtn) cancelBtn.onclick = handleClose;
    if (confirmBtn) confirmBtn.onclick = handleConfirm;

    overlay.onclick = (e) => {
        if (e.target === overlay) handleClose();
    };

    const handleEsc = (e) => {
        if (e.key === 'Escape') {
            handleClose();
            document.removeEventListener('keydown', handleEsc);
        }
    };
    document.addEventListener('keydown', handleEsc);

    // Focus en botón de confirmación
    if (confirmBtn) confirmBtn.focus();
}

// Función universal showAlert para reemplazar alert() con modal elegante
function showAlert(message, type = 'info', onOk = null) {
    let title = 'Notificación';
    let icon = 'ℹ️';

    if (type === 'error' || type === 'danger') {
        title = 'Error';
        icon = '❌';
    } else if (type === 'warning') {
        title = 'Atención';
        icon = '⚠️';
    } else if (type === 'success') {
        title = 'Éxito';
        icon = '✅';
    } else if (type === 'info') {
        title = 'Información';
        icon = 'ℹ️';
    }

    showPremiumModal(title, message, icon, onOk, null, 'Aceptar', null);
}

// Función para confirmar acciones (reemplazo de confirm())
function confirmAction(title, message, icon, onConfirm, onCancel) {
    showPremiumModal(title, message, icon || '❓', onConfirm, onCancel, 'Confirmar', 'Cancelar');
}

// Función específica para confirmar cierre de sesión
function confirmLogout(logoutUrl) {
    showPremiumModal(
        'Cerrar Sesión',
        '¿Estás seguro de que deseas cerrar tu sesión? Tendrás que iniciar sesión nuevamente para acceder a tu cuenta.',
        '🔒',
        () => {
            window.location.href = logoutUrl;
        },
        null,
        'Cerrar Sesión',
        'Cancelar'
    );
}

// Función específica para confirmar eliminación
function confirmDelete(itemName, onConfirm, onCancel) {
    showPremiumModal(
        'Eliminar Elemento',
        `¿Estás seguro de que deseas eliminar "${itemName}"? Esta acción no se puede deshacer.`,
        '🗑️',
        onConfirm,
        onCancel,
        'Eliminar',
        'Cancelar'
    );
}

// Función showPrompt para reemplazar prompt() nativo con modal oscuro elegante
function showPrompt(title, message, defaultValue = '', onConfirm, onCancel) {
    hidePremiumModal();

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay active';
    overlay.id = 'premiumModal';
    overlay.style.cssText = `
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.75);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 999999;
        padding: 1rem;
        box-sizing: border-box;
    `;

    const modal = document.createElement('div');
    modal.className = 'modal-premium';
    modal.style.cssText = `
        background: #141419;
        border: 1px solid rgba(255, 127, 0, 0.35);
        border-radius: 16px;
        max-width: 480px;
        width: 100%;
        padding: 1.75rem;
        box-shadow: 0 25px 60px rgba(0,0,0,0.8), 0 0 30px rgba(255, 127, 0, 0.15);
        color: #ffffff;
        position: relative;
        box-sizing: border-box;
    `;

    modal.innerHTML = `
        <button type="button" class="modal-close-x" style="
            position: absolute; top: 14px; right: 16px;
            background: none; border: none; color: #888;
            font-size: 1.5rem; line-height: 1; cursor: pointer;
            padding: 4px 8px; border-radius: 6px;
        ">&times;</button>
        
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:1rem;">
            <span style="font-size:1.8rem; line-height:1;">✏️</span>
            <h3 style="margin:0; font-size:1.25rem; font-weight:800; color:#ffffff;">${title}</h3>
        </div>
        
        <div style="font-size:0.95rem; color:#cbd5e1; line-height:1.5; margin-bottom:1.25rem;">
            ${message ? `<p style="margin:0 0 1rem 0;">${message}</p>` : ''}
            <input type="text" class="modal-prompt-input" value="${defaultValue}" style="
                width: 100%;
                background: #0b0b0e;
                border: 1px solid rgba(255, 127, 0, 0.5);
                color: #ffffff;
                padding: 0.85rem 1rem;
                border-radius: 8px;
                font-size: 1rem;
                outline: none;
                box-sizing: border-box;
                box-shadow: inset 0 2px 4px rgba(0,0,0,0.4);
            ">
        </div>
        
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" class="btn-modal-cancel" style="
                background: #22222a; color: #e2e8f0; border: 1px solid #3a3a48;
                padding: 10px 20px; border-radius: 8px; font-weight: 700;
                font-size: 0.9rem; cursor: pointer;
            ">Cancelar</button>
            <button type="button" class="btn-modal-confirm" style="
                background: linear-gradient(135deg, #ff8f00, #ff6600);
                color: #ffffff; border: none; padding: 10px 24px;
                border-radius: 8px; font-weight: 800; font-size: 0.9rem;
                cursor: pointer; box-shadow: 0 4px 15px rgba(255,102,0,0.35);
            ">Aceptar</button>
        </div>
    `;

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    const input = modal.querySelector('.modal-prompt-input');
    const closeBtn = modal.querySelector('.modal-close-x');
    const cancelBtn = modal.querySelector('.btn-modal-cancel');
    const confirmBtn = modal.querySelector('.btn-modal-confirm');

    const handleClose = () => {
        hidePremiumModal();
        if (onCancel) onCancel();
    };

    const handleConfirm = () => {
        const val = input ? input.value : '';
        hidePremiumModal();
        if (onConfirm) onConfirm(val);
    };

    if (closeBtn) closeBtn.onclick = handleClose;
    if (cancelBtn) cancelBtn.onclick = handleClose;
    if (confirmBtn) confirmBtn.onclick = handleConfirm;

    overlay.onclick = (e) => {
        if (e.target === overlay) handleClose();
    };

    if (input) {
        input.focus();
        input.select();
        input.onkeydown = (e) => {
            if (e.key === 'Enter') handleConfirm();
            if (e.key === 'Escape') handleClose();
        };
    }
}
