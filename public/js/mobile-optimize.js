/**
 * Mobile & Touch Optimization Script
 * Mejora la experiencia en dispositivos móviles y táctiles
 */

(function() {
    'use strict';

    // Detectar dispositivo
    const isMobile = () => {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
               window.innerWidth <= 768;
    };

    const isTouchDevice = () => {
        return (('ontouchstart' in window) ||
                (navigator.maxTouchPoints > 0) ||
                (navigator.msMaxTouchPoints > 0));
    };

    // Agregar clase al body para CSS
    const addDeviceClasses = () => {
        if (isMobile()) {
            document.body.classList.add('is-mobile', 'touch-optimized');
        }
        if (isTouchDevice()) {
            document.body.classList.add('is-touch-device');
        }
    };

    // Optimizar viewport en tiempo real
    const handleViewportResize = () => {
        const viewport = document.querySelector('meta[name="viewport"]');
        if (viewport) {
            const width = window.innerWidth;
            if (width < 480) {
                viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover');
            }
        }
    };

    // Mejorar touch targets (mínimo 44x44px)
    const optimizeTouchTargets = () => {
        if (!isTouchDevice()) return;

        const buttons = document.querySelectorAll('button, a.btn, input[type="submit"], input[type="button"]');
        buttons.forEach(btn => {
            const rect = btn.getBoundingClientRect();
            if (rect.height < 44 || rect.width < 44) {
                btn.style.padding = 'clamp(0.5rem, 2vw, 1rem)';
                btn.style.minHeight = '44px';
                btn.style.minWidth = '44px';
            }
        });
    };

    // Prevenir zoom en inputs
    const fixInputZoom = () => {
        if (!isMobile()) return;

        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                document.activeElement.style.fontSize = '16px';
            }, false);
        });
    };

    // Optimizar scroll suave
    const enableSmoothScroll = () => {
        if (isMobile()) {
            document.documentElement.style.scrollBehavior = 'smooth';
            // Usar -webkit-overflow-scrolling para iOS
            document.documentElement.style.webkitOverflowScrolling = 'touch';
        }
    };

    // Manejar orientación
    const handleOrientationChange = () => {
        window.addEventListener('orientationchange', () => {
            setTimeout(() => {
                window.scrollTo(0, 0);
                optimizeTouchTargets();
            }, 100);
        });
    };

    // Prevenir pinch zoom en desktop pero permitir en mobile
    const handlePinchZoom = () => {
        if (isMobile() && isTouchDevice()) {
            document.addEventListener('touchmove', function(e) {
                if (e.touches.length > 1) {
                    e.preventDefault();
                }
            }, { passive: false });
        }
    };

    // Optimizar performance de scrolling
    const optimizeScrolling = () => {
        let scrolling = false;
        let scrollTimeout;

        window.addEventListener('scroll', () => {
            if (!scrolling) {
                scrolling = true;
                document.body.classList.add('is-scrolling');
            }

            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                scrolling = false;
                document.body.classList.remove('is-scrolling');
            }, 150);
        }, { passive: true });
    };

    // Mejorar contraste en modo oscuro
    const enhanceDarkMode = () => {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.body.classList.add('dark-mode-optimized');
        }

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (e.matches) {
                document.body.classList.add('dark-mode-optimized');
            } else {
                document.body.classList.remove('dark-mode-optimized');
            }
        });
    };

    // Lazy load images en mobile
    const lazyLoadImages = () => {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src || img.src;
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px'
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    };

    // Gestionar teclado virtual en mobile
    const handleMobileKeyboard = () => {
        if (!isMobile()) return;

        const inputs = document.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                setTimeout(() => {
                    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            });
        });
    };

    // Mejorar rendimiento en conexiones lentas
    const optimizeForSlowNetwork = () => {
        const connection = (navigator.connection || navigator.mozConnection || navigator.webkitConnection);
        if (connection) {
            const effectiveType = connection.effectiveType;
            if (effectiveType === '3g' || effectiveType === '4g') {
                document.body.classList.add('slow-network-optimized');
            }
        }
    };

    // Gestionar estado de batería
    const monitorBattery = () => {
        if ('getBattery' in navigator || 'battery' in navigator) {
            const promise = navigator.getBattery ? navigator.getBattery() : Promise.resolve(navigator.battery);
            promise.then(battery => {
                const updateBatteryStatus = () => {
                    const level = battery.level;
                    if (level < 0.2) {
                        document.body.classList.add('low-battery-mode');
                    } else {
                        document.body.classList.remove('low-battery-mode');
                    }
                };

                updateBatteryStatus();
                battery.addEventListener('levelchange', updateBatteryStatus);
                battery.addEventListener('chargingchange', updateBatteryStatus);
            });
        }
    };

    // Accessibility: Respecto a preferencias de movimiento reducido
    const respectMotionPreferences = () => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.style.scrollBehavior = 'auto';
            document.body.classList.add('reduce-motion');
        }
    };

    // Event delegation para mejor rendimiento
    const enableEventDelegation = () => {
        document.addEventListener('click', (e) => {
            // Manejar cliks en botones con el atributo data-action
            const button = e.target.closest('[data-action]');
            if (button) {
                const action = button.getAttribute('data-action');
                // Trigger custom event
                const event = new CustomEvent('action-click', { detail: { action } });
                document.dispatchEvent(event);
            }
        });
    };

    // Inicializar todo cuando el DOM esté listo
    const init = () => {
        // Ejecutar funciones en orden
        addDeviceClasses();
        handleViewportResize();
        optimizeTouchTargets();
        fixInputZoom();
        enableSmoothScroll();
        handleOrientationChange();
        handlePinchZoom();
        optimizeScrolling();
        enhanceDarkMode();
        lazyLoadImages();
        handleMobileKeyboard();
        optimizeForSlowNetwork();
        monitorBattery();
        respectMotionPreferences();
        enableEventDelegation();

        // Re-optimizar cuando cambie el tamaño
        window.addEventListener('resize', debounce(() => {
            handleViewportResize();
            optimizeTouchTargets();
        }, 250));
    };

    // Debounce helper
    const debounce = (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    // Iniciar cuando DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

// CSS clases adicionales para mobile
const mobileOptimizationStyles = document.createElement('style');
mobileOptimizationStyles.textContent = `
    /* Mobile optimizations */
    body.is-mobile {
        font-size: 16px; /* Prevenir zoom en inputs */
        -webkit-text-size-adjust: 100%;
        text-size-adjust: 100%;
    }

    body.is-touch-device {
        -webkit-user-select: none;
        user-select: none;
    }

    body.is-touch-device input,
    body.is-touch-device textarea,
    body.is-touch-device select {
        -webkit-user-select: text;
        user-select: text;
    }

    body.is-scrolling {
        pointer-events: none;
    }

    /* Reducir movimiento si lo prefieren */
    body.reduce-motion * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }

    /* Optimizaciones de bajo recuso (batería baja) */
    body.low-battery-mode * {
        animation-duration: 0.01ms !important;
    }

    /* Optimizaciones de red lenta */
    body.slow-network-optimized img {
        max-width: 100%;
        height: auto;
    }

    /* Mejora visual en modo oscuro */
    body.dark-mode-optimized {
        background: #0a0a0a;
        color: #f5f5f5;
    }

    /* Fix para keyboards virtuales */
    @media (max-height: 500px) and (orientation: landscape) {
        input:focus,
        textarea:focus,
        select:focus {
            transform: scale(1.1);
            transform-origin: top center;
        }
    }
`;
document.head.appendChild(mobileOptimizationStyles);
