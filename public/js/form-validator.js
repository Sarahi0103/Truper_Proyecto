/**
 * FormValidator - Motor Central de Validación y Sanitización de Formularios
 * Plataforma Truper (Ferretería FOX)
 * Proporciona validación estándar, sanitización en vivo, máscaras reactivas y feedback visual no intrusivo.
 */

(function (window, document) {
    'use strict';

    // Algoritmo de Luhn para números de tarjeta
    function checkLuhn(number) {
        const clean = String(number).replace(/\D/g, '');
        if (clean.length < 13 || clean.length > 19) return false;
        // Tarjetas de prueba comunes para entorno de pruebas / demo
        const testCards = ['4000123456789010', '4242424242424242', '5555555555554444', '378282246310005', '1111111111111111'];
        if (testCards.includes(clean)) return true;
        let sum = 0;
        let shouldDouble = false;
        for (let i = clean.length - 1; i >= 0; i--) {
            let digit = parseInt(clean.charAt(i), 10);
            if (shouldDouble) {
                digit *= 2;
                if (digit > 9) digit -= 9;
            }
            sum += digit;
            shouldDouble = !shouldDouble;
        }
        return (sum % 10) === 0;
    }

    // Detección de marca de tarjeta
    function detectCardBrand(num) {
        const clean = String(num).replace(/\D/g, '');
        if (/^4/.test(clean)) return { name: 'Visa', icon: '💳' };
        if (/^(5[1-5]|2[2-7])/.test(clean)) return { name: 'Mastercard', icon: '💳' };
        if (/^3[47]/.test(clean)) return { name: 'American Express', icon: '💳' };
        if (/^(50|58|6[0-9])/.test(clean)) return { name: 'Carnet', icon: '💳' };
        return { name: 'Desconocida', icon: '💳' };
    }

    // Validación de dígito verificador de CLABE (Banxico / ABM)
    function checkClabe(clabe) {
        const clean = String(clabe).replace(/\D/g, '');
        if (clean.length !== 18) return false;
        const weights = [3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7];
        let sum = 0;
        for (let i = 0; i < 17; i++) {
            sum += (parseInt(clean[i], 10) * weights[i]) % 10;
        }
        const expected = (10 - (sum % 10)) % 10;
        return expected === parseInt(clean[17], 10);
    }

    // Validador de RFC mexicano (SAT)
    function checkRFC(rfc) {
        const clean = String(rfc).trim().toUpperCase();
        if (clean === 'XAXX010101000' || clean === 'XEXX010101000') return true;
        // Persona Física (13) o Moral (12)
        const regex = /^[A-Z&Ñ]{3,4}[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])[A-Z0-9]{3}$/;
        return regex.test(clean);
    }

    // Inyección de estilos de error si no existen
    function injectStyles() {
        if (document.getElementById('form-validator-styles')) return;
        const style = document.createElement('style');
        style.id = 'form-validator-styles';
        style.textContent = `
            .form-input-invalid {
                border-color: #ef4444 !important;
                box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.25) !important;
                animation: fv-shake 0.3s ease-in-out;
            }
            .form-input-valid {
                border-color: rgba(34, 197, 94, 0.5) !important;
            }
            .fv-error-text {
                color: #ef4444 !important;
                font-size: 0.8rem !important;
                margin-top: 4px !important;
                display: block !important;
                line-height: 1.3 !important;
                font-weight: 500 !important;
            }
            @keyframes fv-shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-4px); }
                75% { transform: translateX(4px); }
            }
        `;
        document.head.appendChild(style);
    }

    const FormValidator = {
        /**
         * Sanitiza una cadena según la regla especificada
         */
        sanitize: function (val, type) {
            if (val === null || val === undefined) return '';
            let s = String(val);

            // Eliminar caracteres nulos y secuencias de control peligrosas
            s = s.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');

            switch (type) {
                case 'digits':
                    return s.replace(/\D/g, '');
                case 'phone':
                    return s.replace(/\D/g, '').slice(0, 10);
                case 'postal_code':
                    return s.replace(/\D/g, '').slice(0, 5);
                case 'clabe':
                    return s.replace(/\D/g, '').slice(0, 18);
                case 'last4':
                    return s.replace(/\D/g, '').slice(0, 4);
                case 'rfc':
                    return s.replace(/[^a-zA-Z0-9&ñÑ]/g, '').toUpperCase().slice(0, 13);
                case 'card_number': {
                    const digits = s.replace(/\D/g, '').slice(0, 19);
                    return digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
                }
                case 'card_expiry': {
                    const clean = s.replace(/\D/g, '').slice(0, 4);
                    if (clean.length >= 3) {
                        return clean.slice(0, 2) + '/' + clean.slice(2);
                    }
                    return clean;
                }
                case 'card_cvv':
                    return s.replace(/\D/g, '').slice(0, 4);
                case 'alphanumeric':
                    return s.replace(/[^a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑüÜ\-_\.]/g, '');
                case 'text':
                default:
                    // Quitar tags <script>, <iframe>, HTML tags en general
                    return s.replace(/<\/?[^>]+(>|$)/g, '').trim();
            }
        },

        /**
         * Muestra mensaje de error en un campo
         */
        showFieldError: function (input, message) {
            if (!input) return;
            input.classList.remove('form-input-valid');
            input.classList.add('form-input-invalid');

            let err = input.parentElement ? input.parentElement.querySelector('.fv-error-text') : null;
            if (!err && input.id) {
                err = document.getElementById(input.id + '-fv-error');
            }
            if (!err) {
                err = document.createElement('small');
                err.className = 'fv-error-text';
                if (input.id) err.id = input.id + '-fv-error';
                if (input.nextSibling) {
                    input.parentNode.insertBefore(err, input.nextSibling);
                } else {
                    input.parentNode.appendChild(err);
                }
            }
            err.textContent = message;
            err.style.display = 'block';
        },

        /**
         * Limpia el mensaje de error de un campo
         */
        clearFieldError: function (input) {
            if (!input) return;
            input.classList.remove('form-input-invalid');
            const err = input.parentElement ? input.parentElement.querySelector('.fv-error-text') : null;
            if (err) {
                err.textContent = '';
                err.style.display = 'none';
            }
        },

        /**
         * Valida un campo individual según sus atributos y reglas
         */
        validateField: function (input) {
            if (!input || input.disabled || input.type === 'hidden' || input.type === 'submit') {
                return { valid: true };
            }

            const val = (input.value || '').trim();
            const isRequired = input.required || input.getAttribute('aria-required') === 'true' || input.hasAttribute('data-required');

            // 1. Campo requerido vacío
            if (isRequired && val === '') {
                const label = input.getAttribute('placeholder') || input.name || input.id || 'Este campo';
                const msg = `El campo es obligatorio.`;
                this.showFieldError(input, msg);
                return { valid: false, message: msg };
            }

            if (val === '') {
                this.clearFieldError(input);
                return { valid: true };
            }

            // 2. Email
            if (input.type === 'email' || input.getAttribute('data-type') === 'email' || input.id === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(val) || val.length > 254) {
                    const msg = 'Ingresa un correo electrónico válido (ej: usuario@correo.com)';
                    this.showFieldError(input, msg);
                    return { valid: false, message: msg };
                }
            }

            // 3. Teléfono
            if (input.type === 'tel' || input.getAttribute('data-type') === 'phone' || input.id === 'phone') {
                const digits = val.replace(/\D/g, '');
                if (digits.length !== 10) {
                    const msg = 'El teléfono debe contener exactamente 10 dígitos';
                    this.showFieldError(input, msg);
                    return { valid: false, message: msg };
                }
            }

            // 4. Código Postal
            if (input.getAttribute('data-type') === 'postal_code' || input.id === 'postalCode' || input.id === 'zipCodeFiscal' || input.id === 'company_zip_code') {
                const digits = val.replace(/\D/g, '');
                if (digits.length !== 5) {
                    const msg = 'El código postal debe ser de 5 dígitos';
                    this.showFieldError(input, msg);
                    return { valid: false, message: msg };
                }
            }

            // 5. RFC
            if (input.getAttribute('data-type') === 'rfc' || input.id === 'rfc' || input.id === 'company_rfc' || input.id === 'accountRfc') {
                if (!checkRFC(val)) {
                    const msg = 'RFC inválido. Formato: 12 caracteres (moral) o 13 caracteres (física)';
                    this.showFieldError(input, msg);
                    return { valid: false, message: msg };
                }
            }

            // 6. Tarjeta de crédito / débito
            if (input.getAttribute('data-type') === 'card_number' || input.id === 'cardNumber') {
                const digits = val.replace(/\D/g, '');
                if (digits.length < 13 || digits.length > 19 || !checkLuhn(digits)) {
                    const msg = 'Número de tarjeta inválido. Verifica los dígitos.';
                    this.showFieldError(input, msg);
                    return { valid: false, message: msg };
                }
            }

            // 7. Vencimiento de tarjeta
            if (input.getAttribute('data-type') === 'card_expiry' || input.id === 'cardExpiry') {
                if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(val)) {
                    const msg = 'Formato inválido. Ingresa MM/AA';
                    this.showFieldError(input, msg);
                    return { valid: false, message: msg };
                }
                const parts = val.split('/');
                const month = parseInt(parts[0], 10);
                const year = 2000 + parseInt(parts[1], 10);
                const now = new Date();
                const curYear = now.getFullYear();
                const curMonth = now.getMonth() + 1;
                if (year < curYear || (year === curYear && month < curMonth)) {
                    const msg = 'La tarjeta está vencida.';
                    this.showFieldError(input, msg);
                    return { valid: false, message: msg };
                }
            }

            // 8. CVV
            if (input.getAttribute('data-type') === 'card_cvv' || input.id === 'cardCvv') {
                const digits = val.replace(/\D/g, '');
                if (digits.length < 3 || digits.length > 4) {
                    const msg = 'El CVV debe tener 3 o 4 dígitos';
                    this.showFieldError(input, msg);
                    return { valid: false, message: msg };
                }
            }

            // 9. CLABE
            if (input.getAttribute('data-type') === 'clabe' || input.id === 'bank_clabe' || input.id === 'bankClabe') {
                const digits = val.replace(/\D/g, '');
                if (digits.length !== 18) {
                    const msg = 'La CLABE debe tener exactamente 18 dígitos';
                    this.showFieldError(input, msg);
                    return { valid: false, message: msg };
                }
                if (!checkClabe(digits)) {
                    const msg = 'Dígito de control de CLABE no coincide. Verifica los 18 dígitos.';
                    this.showFieldError(input, msg);
                    return { valid: false, message: msg };
                }
            }

            // 10. Últimos 4 dígitos
            if (input.getAttribute('data-type') === 'last4' || input.id === 'last4') {
                const digits = val.replace(/\D/g, '');
                if (digits.length !== 4) {
                    const msg = 'Deben ser exactamente 4 dígitos';
                    this.showFieldError(input, msg);
                    return { valid: false, message: msg };
                }
            }

            // 11. Minlength / Maxlength
            const minLen = parseInt(input.getAttribute('minlength'), 10);
            if (minLen && val.length < minLen) {
                const msg = `Debe tener al menos ${minLen} caracteres`;
                this.showFieldError(input, msg);
                return { valid: false, message: msg };
            }

            // Todo válido
            this.clearFieldError(input);
            input.classList.add('form-input-valid');
            return { valid: true };
        },

        /**
         * Valida un formulario completo
         */
        validateForm: function (form) {
            if (!form) return true;
            let isValid = true;
            let firstInvalid = null;

            const inputs = form.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea');
            inputs.forEach(input => {
                // Si el contenedor padre está oculto (display: none), omitir validación (ej: campos condicionales de facturación o pasarelas)
                if (input.offsetParent === null && input.type !== 'radio' && input.type !== 'checkbox') {
                    return;
                }
                const res = this.validateField(input);
                if (!res.valid) {
                    isValid = false;
                    if (!firstInvalid) firstInvalid = input;
                }
            });

            if (!isValid && firstInvalid) {
                firstInvalid.focus();
                if (typeof firstInvalid.scrollIntoView === 'function') {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }

            return isValid;
        },

        /**
         * Configura máscaras y eventos reactivos en un formulario
         */
        initForm: function (form) {
            if (!form || form.__fv_initialized) return;
            form.__fv_initialized = true;

            const self = this;

            form.querySelectorAll('input, select, textarea').forEach(input => {
                const id = input.id || '';
                const name = input.name || '';
                const type = input.getAttribute('data-type') || input.type;

                // Aplicar tipos inferidos si no están explícitos
                if (id.includes('rfc') || name.includes('rfc')) input.setAttribute('data-type', 'rfc');
                else if (id.includes('clabe') || name.includes('clabe')) input.setAttribute('data-type', 'clabe');
                else if (id.includes('postalCode') || id.includes('zip') || name.includes('zip')) input.setAttribute('data-type', 'postal_code');
                else if (id === 'cardNumber' || name === 'cardNumber') input.setAttribute('data-type', 'card_number');
                else if (id === 'cardExpiry' || name === 'cardExpiry') input.setAttribute('data-type', 'card_expiry');
                else if (id === 'cardCvv' || name === 'cardCvv') input.setAttribute('data-type', 'card_cvv');
                else if (id === 'phone' || name === 'phone') input.setAttribute('data-type', 'phone');
                else if (id === 'last4') input.setAttribute('data-type', 'last4');

                const activeType = input.getAttribute('data-type');

                // Evento input: sanitización / máscara en vivo
                input.addEventListener('input', function () {
                    if (activeType === 'card_number') {
                        input.value = self.sanitize(input.value, 'card_number');
                    } else if (activeType === 'card_expiry') {
                        input.value = self.sanitize(input.value, 'card_expiry');
                    } else if (activeType === 'card_cvv') {
                        input.value = self.sanitize(input.value, 'card_cvv');
                    } else if (activeType === 'clabe') {
                        input.value = self.sanitize(input.value, 'clabe');
                    } else if (activeType === 'last4') {
                        input.value = self.sanitize(input.value, 'last4');
                    } else if (activeType === 'rfc') {
                        input.value = self.sanitize(input.value, 'rfc');
                    } else if (activeType === 'postal_code') {
                        input.value = self.sanitize(input.value, 'postal_code');
                    } else if (activeType === 'phone') {
                        input.value = self.sanitize(input.value, 'phone');
                    } else if (input.type === 'text' || input.tagName === 'TEXTAREA') {
                        // Eliminar secuencias nulas en tiempo real
                        input.value = input.value.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
                    }

                    // Limpiar error al escribir si ya se corrigió
                    if (input.classList.contains('form-input-invalid')) {
                        self.validateField(input);
                    }
                });

                // Evento blur: validación y trim final
                input.addEventListener('blur', function () {
                    if (input.type !== 'password' && typeof input.value === 'string') {
                        input.value = input.value.trim();
                    }
                    if (input.value !== '' || input.required) {
                        self.validateField(input);
                    }
                });
            });
        },

        /**
         * Inicializa todos los formularios de la página y observa nuevos agregados dinámicamente
         */
        initAll: function () {
            injectStyles();
            document.querySelectorAll('form').forEach(form => this.initForm(form));

            // MutationObserver para modales agregados dinámicamente al DOM
            const observer = new MutationObserver(mutations => {
                mutations.forEach(m => {
                    m.addedNodes.forEach(node => {
                        if (node.nodeType === 1) {
                            if (node.tagName === 'FORM') {
                                FormValidator.initForm(node);
                            } else {
                                node.querySelectorAll('form').forEach(form => FormValidator.initForm(form));
                            }
                        }
                    });
                });
            });

            if (document.body) {
                observer.observe(document.body, { childList: true, subtree: true });
            }
        }
    };

    // Auto-inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => FormValidator.initAll());
    } else {
        FormValidator.initAll();
    }

    window.FormValidator = FormValidator;

})(window, document);
