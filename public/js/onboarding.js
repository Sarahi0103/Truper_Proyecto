(function () {
    const ONBOARDING_STORAGE = 'truper_onboarding_completed';
    const ONBOARDING_VERSION = '1.0';

    function hasCompletedOnboarding() {
        try {
            const data = localStorage.getItem(ONBOARDING_STORAGE);
            if (!data) return false;
            const parsed = JSON.parse(data);
            return parsed.version === ONBOARDING_VERSION && parsed.completed;
        } catch (e) {
            return false;
        }
    }

    function markOnboardingCompleted() {
        localStorage.setItem(ONBOARDING_STORAGE, JSON.stringify({
            version: ONBOARDING_VERSION,
            completed: true,
            date: new Date().toISOString()
        }));
    }

    function createOnboardingModal() {
        const modal = document.createElement('div');
        modal.className = 'onboarding-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        `;

        const content = document.createElement('div');
        content.className = 'onboarding-content';
        content.style.cssText = `
            background: var(--bg-card);
            border-radius: 16px;
            padding: 2.5rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        `;

        const steps = [
            {
                title: '¡Bienvenido a Truper Platform!',
                content: `
                    <p style="margin-bottom: 1rem;">Estamos emocionados de tenerte con nosotros. Esta plataforma te permitirá:</p>
                    <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                        <li>Explorar nuestro catálogo completo de productos</li>
                        <li>Realizar pedidos de manera rápida y sencilla</li>
                        <li>Ver tu historial de transacciones</li>
                        <li>Acumular puntos por tus compras</li>
                    </ul>
                `,
                icon: '🎉'
            },
            {
                title: 'Explora el Catálogo',
                content: `
                    <p style="margin-bottom: 1rem;">Navega por miles de productos usando:</p>
                    <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                        <li>El buscador inteligente con autocompletado</li>
                        <li>Filtros avanzados por precio, stock y categoría</li>
                        <li>Comparación de productos (máximo 4)</li>
                        <li>Sistema de favoritos para guardar tus productos preferidos</li>
                    </ul>
                `,
                icon: '🔍'
            },
            {
                title: 'Gestiona tus Pedidos',
                content: `
                    <p style="margin-bottom: 1rem;">Desde tu panel de control puedes:</p>
                    <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                        <li>Ver el estado de tus pedidos en tiempo real</li>
                        <li>Descargar tickets en PDF</li>
                        <li>Exportar tu historial a CSV</li>
                        <li>Usar cupones de descuento disponibles</li>
                    </ul>
                `,
                icon: '📦'
            },
            {
                title: '¡Comienza Ahora!',
                content: `
                    <p style="margin-bottom: 1rem;">Ya estás listo para comenzar a explorar. Recuerda que:</p>
                    <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                        <li>Tu información está protegida con seguridad avanzada</li>
                        <li>Puedes cambiar tu contraseña cuando lo desees</li>
                        <li>El soporte está disponible para ayudarte</li>
                    </ul>
                    <p style="margin-top: 1rem; font-weight: 600; color: var(--accent);">¡Disfruta tu experiencia en Truper Platform!</p>
                `,
                icon: '🚀'
            }
        ];

        let currentStep = 0;

        function renderStep() {
            const step = steps[currentStep];
            content.innerHTML = `
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <span style="font-size: 3rem;">${step.icon}</span>
                    <h2 style="margin-top: 1rem; color: var(--text-primary);">${step.title}</h2>
                </div>
                <div style="color: var(--text-secondary); line-height: 1.6;">
                    ${step.content}
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem;">
                    <button id="onboardingPrev" class="btn btn-ghost" style="visibility: ${currentStep === 0 ? 'hidden' : 'visible'};">
                        ← Anterior
                    </button>
                    <div style="display: flex; gap: 0.5rem;">
                        ${steps.map((_, i) => `
                            <div style="
                                width: 10px;
                                height: 10px;
                                border-radius: 50%;
                                background: ${i === currentStep ? 'var(--accent)' : 'var(--border)'};
                                transition: background 0.3s ease;
                            "></div>
                        `).join('')}
                    </div>
                    <button id="onboardingNext" class="btn btn-primary">
                        ${currentStep === steps.length - 1 ? 'Comenzar' : 'Siguiente →'}
                    </button>
                </div>
            `;

            const prevBtn = document.getElementById('onboardingPrev');
            const nextBtn = document.getElementById('onboardingNext');

            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    if (currentStep > 0) {
                        currentStep--;
                        renderStep();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    if (currentStep < steps.length - 1) {
                        currentStep++;
                        renderStep();
                    } else {
                        modal.remove();
                        markOnboardingCompleted();
                    }
                });
            }
        }

        renderStep();
        modal.appendChild(content);
        document.body.appendChild(modal);

        // Cerrar al hacer clic fuera del modal
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
                markOnboardingCompleted();
            }
        });
    }

    function initOnboarding() {
        // Solo mostrar si el usuario está logueado y no ha completado el onboarding
        if (typeof isLoggedIn === 'function' && isLoggedIn() && !hasCompletedOnboarding()) {
            // Esperar un momento para que la página cargue completamente
            setTimeout(() => {
                createOnboardingModal();
            }, 1500);
        }
    }

    // Exponer función para reiniciar onboarding (útil para testing)
    window.resetOnboarding = function() {
        localStorage.removeItem(ONBOARDING_STORAGE);
        createOnboardingModal();
    };

    // Inicializar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOnboarding);
    } else {
        initOnboarding();
    }
})();
