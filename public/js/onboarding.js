/**
 * Sistema de Onboarding para Usuarios
 * Guía interactiva para nuevos usuarios
 */

(function() {
    const ONBOARDING_STORAGE = 'truper_onboarding_completed';
    const ONBOARDING_STEPS = [
        {
            target: '#catalogSearch',
            title: 'Busca Productos',
            description: 'Utiliza la barra de búsqueda para encontrar productos por nombre, código o categoría. La búsqueda es en tiempo real.',
            position: 'bottom'
        },
        {
            target: '.catalog-categories-actions button:first-child',
            title: 'Filtrar por Categoría',
            description: 'Selecciona una categoría para filtrar rápidamente los productos que necesitas.',
            position: 'bottom'
        },
        {
            target: '#filterMinPrice',
            title: 'Rango de Precios',
            description: 'Establece un precio mínimo y máximo para filtrar productos según tu presupuesto.',
            position: 'bottom'
        },
        {
            target: '[data-add-product]',
            title: 'Agregar al Carrito',
            description: 'Haz clic en este botón para agregar productos a tu carrito de compras.',
            position: 'top'
        },
        {
            target: '#openCart',
            title: 'Ver Carrito',
            description: 'Haz clic aquí para ver tu carrito, modificar cantidades o generar un ticket de pedido.',
            position: 'left'
        },
        {
            target: '[data-compare-product]',
            title: 'Comparar Productos',
            description: 'Agrega productos a la lista de comparación para analizar sus características lado a lado.',
            position: 'top'
        }
    ];

    let currentStep = 0;
    let overlay = null;
    let tooltip = null;
    let skipButton = null;

    function hasCompletedOnboarding() {
        try {
            return localStorage.getItem(ONBOARDING_STORAGE) === 'true';
        } catch (e) {
            return false;
        }
    }

    function markOnboardingAsCompleted() {
        try {
            localStorage.setItem(ONBOARDING_STORAGE, 'true');
        } catch (e) {
            console.error('Error saving onboarding status:', e);
        }
    }

    function resetOnboarding() {
        try {
            localStorage.removeItem(ONBOARDING_STORAGE);
        } catch (e) {
            console.error('Error resetting onboarding:', e);
        }
    }

    function createOverlay() {
        if (overlay) return;
        
        overlay = document.createElement('div');
        overlay.className = 'onboarding-overlay';
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                // No hacer nada al hacer clic en el overlay
            }
        });
        document.body.appendChild(overlay);
    }

    function createTooltip(step) {
        if (tooltip) {
            tooltip.remove();
        }

        tooltip = document.createElement('div');
        tooltip.className = `onboarding-tooltip ${step.position}`;
        
        // Progress dots
        let progressHTML = '<div class="onboarding-progress">';
        ONBOARDING_STEPS.forEach((_, index) => {
            let dotClass = '';
            if (index < currentStep) dotClass = 'completed';
            else if (index === currentStep) dotClass = 'active';
            progressHTML += `<div class="onboarding-progress-dot ${dotClass}"></div>`;
        });
        progressHTML += '</div>';

        tooltip.innerHTML = `
            ${progressHTML}
            <h3>${step.title}</h3>
            <p>${step.description}</p>
            <div class="onboarding-actions">
                <button class="btn-skip" onclick="window.TruperOnboarding.skip()">Saltar</button>
                ${currentStep < ONBOARDING_STEPS.length - 1 
                    ? '<button class="btn-next" onclick="window.TruperOnboarding.next()">Siguiente</button>'
                    : '<button class="btn-finish" onclick="window.TruperOnboarding.finish()">Completar</button>'
                }
            </div>
        `;

        document.body.appendChild(tooltip);
        positionTooltip(step);
    }

    function positionTooltip(step) {
        const target = document.querySelector(step.target);
        if (!target) return;

        const targetRect = target.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const scrollX = window.pageXOffset || document.documentElement.scrollLeft;
        const scrollY = window.pageYOffset || document.documentElement.scrollTop;

        let top, left;
        let position = step.position;

        // Auto-adjust vertical position if screen height is constrained
        if (position === 'bottom' && targetRect.bottom + tooltipRect.height + 40 > window.innerHeight) {
            position = 'top';
        } else if (position === 'top' && targetRect.top - tooltipRect.height - 40 < 0) {
            position = 'bottom';
        }

        // Apply updated position class to keep arrow matches
        tooltip.className = `onboarding-tooltip ${position}`;

        switch (position) {
            case 'top':
                top = targetRect.top + scrollY - tooltipRect.height - 20;
                left = targetRect.left + scrollX + (targetRect.width / 2) - (tooltipRect.width / 2);
                break;
            case 'bottom':
                top = targetRect.bottom + scrollY + 20;
                left = targetRect.left + scrollX + (targetRect.width / 2) - (tooltipRect.width / 2);
                break;
            case 'left':
                top = targetRect.top + scrollY + (targetRect.height / 2) - (tooltipRect.height / 2);
                left = targetRect.left + scrollX - tooltipRect.width - 20;
                break;
            case 'right':
                top = targetRect.top + scrollY + (targetRect.height / 2) - (tooltipRect.height / 2);
                left = targetRect.right + scrollX + 20;
                break;
            default:
                top = targetRect.bottom + scrollY + 20;
                left = targetRect.left + scrollX;
        }

        // Asegurar que el tooltip no se salga de la pantalla
        const padding = 20;
        const maxLeft = window.innerWidth - tooltipRect.width - padding;
        const maxTop = window.innerHeight - tooltipRect.height - padding;

        left = Math.max(padding, Math.min(left, maxLeft));
        top = Math.max(padding, Math.min(top, maxTop));

        tooltip.style.top = top + 'px';
        tooltip.style.left = left + 'px';
    }

    function highlightTarget(target) {
        // Remover highlight anterior
        document.querySelectorAll('.onboarding-highlight').forEach(el => {
            el.classList.remove('onboarding-highlight');
        });

        // Agregar highlight al target actual
        target.classList.add('onboarding-highlight');
    }

    function showStep(stepIndex) {
        if (stepIndex >= ONBOARDING_STEPS.length) {
            finish();
            return;
        }

        currentStep = stepIndex;
        const step = ONBOARDING_STEPS[stepIndex];
        const target = document.querySelector(step.target);

        if (!target) {
            // Si no se encuentra el target, saltar al siguiente paso
            next();
            return;
        }

        createOverlay();
        highlightTarget(target);
        createTooltip(step);

        overlay.classList.add('active');
        if (skipButton) skipButton.classList.add('active');
    }

    function next() {
        if (currentStep < ONBOARDING_STEPS.length - 1) {
            showStep(currentStep + 1);
        } else {
            finish();
        }
    }

    function skip() {
        finish();
    }

    function finish() {
        if (overlay) {
            overlay.classList.remove('active');
            setTimeout(() => {
                if (overlay) {
                    overlay.remove();
                    overlay = null;
                }
            }, 300);
        }

        if (tooltip) {
            tooltip.remove();
            tooltip = null;
        }

        document.querySelectorAll('.onboarding-highlight').forEach(el => {
            el.classList.remove('onboarding-highlight');
        });

        if (skipButton) {
            skipButton.classList.remove('active');
        }

        markOnboardingAsCompleted();
    }

    function start() {
        if (hasCompletedOnboarding()) {
            return;
        }

        // Esperar a que el DOM esté completamente cargado
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(() => showStep(0), 1000);
            });
        } else {
            setTimeout(() => showStep(0), 1000);
        }
    }

    function createSkipButton() {
        if (skipButton) return;

        skipButton = document.createElement('button');
        skipButton.className = 'onboarding-skip-button hidden';
        skipButton.textContent = 'Saltar Tour';
        skipButton.addEventListener('click', skip);
        document.body.appendChild(skipButton);
    }

    // Exponer funciones globalmente
    window.TruperOnboarding = {
        start,
        next,
        skip,
        finish,
        reset: resetOnboarding
    };

    // Inicializar
    createSkipButton();
    start();

})();
