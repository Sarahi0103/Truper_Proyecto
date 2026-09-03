# Configuración de Stripe para Pagos con Tarjetas

## Pasos para Configurar Stripe

### 1. Crear Cuenta en Stripe
1. Ve a [stripe.com](https://stripe.com) y regístrate
2. Verifica tu cuenta de negocio
3. Activa tu cuenta en modo de prueba (test mode)

### 2. Obtener las Claves API
1. En el dashboard de Stripe, ve a "Developers" > "API keys"
2. Copia las siguientes claves:
   - **Publishable key** (pk_test_...) - Para usar en el frontend
   - **Secret key** (sk_test_...) - Para usar en el backend
   - **Webhook signing secret** (whsec_...) - Para verificar webhooks

### 3. Configurar Webhooks
1. Ve a "Developers" > "Webhooks"
2. Crea un nuevo webhook endpoint:
   - URL: `https://ferreteriafox.com/api/stripe_webhook.php`
   - Eventos a escuchar:
     - `payment_intent.succeeded`
     - `payment_intent.payment_failed`
     - `charge.refunded`
3. Copia el webhook signing secret

### 4. Configurar en la Plataforma

#### Opción A: Usar Variables de Entorno (.env)
Agrega al archivo `.env`:
```env
STRIPE_SECRET_KEY=sk_test_tu_clave_secreta_aqui
STRIPE_PUBLISHABLE_KEY=pk_test_tu_clave_publica_aqui
STRIPE_WEBHOOK_SECRET=whsec_tu_webhook_secret_aqui
```

#### Opción B: Usar Base de Datos
Ejecuta el siguiente SQL:
```sql
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('stripe_secret_key', 'sk_test_tu_clave_secreta_aqui', 'Stripe Secret Key para pagos'),
('stripe_publishable_key', 'pk_test_tu_clave_publica_aqui', 'Stripe Publishable Key para frontend'),
('stripe_webhook_secret', 'whsec_tu_webhook_secret_aqui', 'Webhook signing secret de Stripe');
```

### 5. Integrar Stripe.js en el Frontend

Agrega el script de Stripe en `public/checkout.php`:
```html
<script src="https://js.stripe.com/v3/"></script>
<script>
    const stripe = Stripe('pk_test_tu_clave_publica_aqui');
    const elements = stripe.elements();
    
    // Crear elemento de tarjeta
    const cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#32325d',
                fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
            }
        }
    });
    cardElement.mount('#card-element');
    
    // Procesar pago
    async function processPayment() {
        const { paymentMethod, error } = await stripe.createPaymentMethod({
            type: 'card',
            card: cardElement,
        });
        
        if (error) {
            console.error(error);
            return;
        }
        
        // Enviar paymentMethodId al backend
        const response = await fetch('/api/checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                // ... otros datos del checkout
                paymentMethodId: paymentMethod.id,
                paymentMethod: 'credit_card'
            })
        });
        
        const result = await response.json();
        if (result.success) {
            window.location.href = result.redirect;
        }
    }
</script>
```

### 6. Probar en Modo de Prueba
1. Usa las tarjetas de prueba de Stripe:
   - **Número**: 4242 4242 4242 4242
   - **Fecha**: Cualquier fecha futura
   - **CVC**: Cualquier código de 3 dígitos
   - **ZIP**: Cualquier código postal

### 7. Pasar a Producción
1. Cambia a modo live en Stripe
2. Obtén las claves de producción (pk_live_..., sk_live_...)
3. Actualiza las claves en tu configuración
4. Reconfigura el webhook con la URL de producción

## Solución de Problemas Comunes

### Error: "Stripe no está configurado"
- Verifica que la clave secreta esté configurada correctamente
- Revisa el archivo `.env` o la tabla `system_settings`

### Error: "Invalid API Key"
- Verifica que estés usando la clave correcta (test vs production)
- Asegúrate de no incluir espacios ni caracteres extra

### Webhook no funciona
- Verifica que la URL del webhook sea accesible públicamente
- Revisa que el webhook signing secret sea correcto
- Revisa los logs del webhook en el dashboard de Stripe

## Recursos
- [Documentación de Stripe](https://stripe.com/docs)
- [Stripe.js Reference](https://stripe.com/docs/js)
- [Webhooks Guide](https://stripe.com/docs/webhooks)
