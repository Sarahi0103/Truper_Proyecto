/**
 * Loader de estado para operaciones largas
 * Proporciona feedback visual durante cargas y operaciones asíncronas
 */

class Loader {
    constructor() {
        this.overlay = null;
        this.spinner = null;
        this.text = null;
        this.progress = null;
        this.progressBar = null;
        this.init();
    }

    init() {
        // Crear elementos del loader si no existen
        if (!document.querySelector('.loading-overlay')) {
            this.createLoader();
        } else {
            this.overlay = document.querySelector('.loading-overlay');
            this.spinner = this.overlay.querySelector('.loading-spinner');
            this.text = this.overlay.querySelector('.loading-text');
            this.progress = this.overlay.querySelector('.loading-progress');
            this.progressBar = this.overlay.querySelector('.loading-progress-bar');
        }
    }

    createLoader() {
        this.overlay = document.createElement('div');
        this.overlay.className = 'loading-overlay';
        this.overlay.innerHTML = `
            <div class="loading-container">
                <div class="loading-spinner"></div>
                <div class="loading-text">Cargando...</div>
                <div class="loading-progress">
                    <div class="loading-progress-bar"></div>
                </div>
            </div>
        `;
        document.body.appendChild(this.overlay);

        this.spinner = this.overlay.querySelector('.loading-spinner');
        this.text = this.overlay.querySelector('.loading-text');
        this.progress = this.overlay.querySelector('.loading-progress');
        this.progressBar = this.overlay.querySelector('.loading-progress-bar');
    }

    show(message = 'Cargando...', showProgress = false) {
        if (this.text) {
            this.text.textContent = message;
        }
        if (this.progress) {
            this.progress.style.display = showProgress ? 'block' : 'none';
        }
        if (this.progressBar) {
            this.progressBar.style.width = '0%';
        }
        this.overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    hide() {
        this.overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    setText(message) {
        if (this.text) {
            this.text.textContent = message;
        }
    }

    setProgress(percent) {
        if (this.progressBar) {
            this.progressBar.style.width = `${Math.min(100, Math.max(0, percent))}%`;
        }
    }

    incrementProgress(amount = 10) {
        if (this.progressBar) {
            const currentWidth = parseFloat(this.progressBar.style.width) || 0;
            this.setProgress(currentWidth + amount);
        }
    }
}

// Instancia global del loader
const loader = new Loader();

// Funciones helper para uso global
window.showLoader = (message, showProgress) => loader.show(message, showProgress);
window.hideLoader = () => loader.hide();
window.setLoaderText = (message) => loader.setText(message);
window.setLoaderProgress = (percent) => loader.setProgress(percent);

// Loader para botones específicos
function setButtonLoading(button, loading = true, originalText = '') {
    if (loading) {
        button.dataset.originalText = button.textContent;
        button.classList.add('btn-loading');
        button.disabled = true;
    } else {
        button.classList.remove('btn-loading');
        button.disabled = false;
        if (button.dataset.originalText) {
            button.textContent = button.dataset.originalText;
        } else if (originalText) {
            button.textContent = originalText;
        }
    }
}

// Loader para formularios
function setFormLoading(form, loading = true) {
    const buttons = form.querySelectorAll('button[type="submit"]');
    buttons.forEach(button => {
        setButtonLoading(button, loading);
    });

    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.disabled = loading;
    });
}

// Auto-loader para fetch requests
function fetchWithLoader(url, options = {}, loaderMessage = 'Procesando...') {
    loader.show(loaderMessage);
    
    return fetch(url, options)
        .finally(() => {
            loader.hide();
        });
}

// Interceptar peticiones fetch para mostrar loader automáticamente
if (typeof window !== 'undefined') {
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        // No mostrar loader para peticiones en background
        if (options && options.background) {
            return originalFetch(url, options);
        }

        // Mostrar loader para peticiones que tomen más de 300ms
        const loaderTimeout = setTimeout(() => {
            loader.show('Cargando...');
        }, 300);

        return originalFetch(url, options)
            .finally(() => {
                clearTimeout(loaderTimeout);
                loader.hide();
            });
    };
}

// Exportar para uso en módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { Loader, loader, showLoader, hideLoader, setButtonLoading, setFormLoading };
}
