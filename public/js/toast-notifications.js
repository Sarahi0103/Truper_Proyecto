/**
 * Toast Notification Component - Truper Platform
 * Toasts no intrusivos, modernos y reactivos
 */

(function() {
    class TruperToast {
        constructor() {
            this.container = null;
            this.init();
        }

        init() {
            if (!document.getElementById('truper-toast-container')) {
                this.container = document.createElement('div');
                this.container.id = 'truper-toast-container';
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            } else {
                this.container = document.getElementById('truper-toast-container');
            }
        }

        show(type, title, message, duration = 4000) {
            if (!this.container) this.init();

            const icons = {
                success: '🟢',
                error: '🔴',
                warning: '🟡',
                info: '🟠'
            };

            const toast = document.createElement('div');
            toast.className = `truper-toast ${type}`;
            toast.innerHTML = `
                <div class="truper-toast-icon">${icons[type] || 'ℹ️'}</div>
                <div class="truper-toast-content">
                    <div class="truper-toast-title">${title}</div>
                    <div class="truper-toast-msg">${message}</div>
                </div>
                <button class="truper-toast-close" title="Cerrar">&times;</button>
            `;

            const closeBtn = toast.querySelector('.truper-toast-close');
            const removeToast = () => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 350);
            };

            closeBtn.addEventListener('click', removeToast);

            this.container.appendChild(toast);
            // Trigger reflow for CSS animation
            setTimeout(() => toast.classList.add('show'), 10);

            if (duration > 0) {
                setTimeout(removeToast, duration);
            }
        }

        success(title, message, duration) { this.show('success', title, message, duration); }
        error(title, message, duration) { this.show('error', title, message, duration); }
        warning(title, message, duration) { this.show('warning', title, message, duration); }
        info(title, message, duration) { this.show('info', title, message, duration); }
    }

    window.TruperToast = new TruperToast();
    // Shorthand global helper
    window.showToast = (type, title, msg, duration) => window.TruperToast.show(type, title, msg, duration);
})();
