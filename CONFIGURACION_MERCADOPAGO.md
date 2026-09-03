# Configuración de Mercado Pago - Guía Completa

Esta guía te explica paso a paso cómo configurar Mercado Pago para procesar pagos en tu plataforma Truper.

## Requisitos Previos

1. **Cuenta de Mercado Pago**
   - Regístrate en [mercadopago.com.mx](https://www.mercadopago.com.mx)
   - Completa el proceso de verificación de identidad
   - Verifica tu cuenta de negocio (si aplica)

2. **Acceso a Credenciales**
   - Necesitas acceso al panel de desarrolladores de Mercado Pago
   - Permisos para crear aplicaciones y obtener tokens

---

## Paso 1: Obtener Access Token

### 1.1 Iniciar Sesión en Mercado Pago

1. Ve a [mercadopago.com.mx](https://www.mercadopago.com.mx)
2. Inicia sesión con tu cuenta
3. Navega al panel de desarrolladores

### 1.2 Crear Nueva Aplicación

1. En el panel, ve a **"Tus integraciones"** o **"Mis aplicaciones"**
2. Haz clic en **"Crear nueva aplicación"**
3. Completa los campos:
   - **Nombre de la aplicación**: `Truper Platform` (o el nombre que prefieras)
   - **Descripción**: `Plataforma de comercio electrónico Ferretería FOX`
4. Selecciona el tipo de aplicación: **"Web / E-commerce"**

### 1.3 Configurar URLs de Redirección

1. En la configuración de la aplicación, busca **"URLs de redirección"**
2. Agrega las siguientes URLs (cambia `tu-dominio.com` por tu dominio real):

   ```
   URL de éxito: https://tu-dominio.com/checkout.php?status=success
   URL de pendiente: https://tu-dominio.com/checkout.php?status=pending
   URL de fallo: https://tu-dominio.com/checkout.php?status=failure
   ```

3. Si estás en desarrollo local, puedes usar:
   ```
   http://localhost:8000/checkout.php?status=success
   http://localhost:8000/checkout.php?status=pending
   http://localhost:8000/checkout.php?status=failure
   ```

### 1.4 Obtener Access Token

1. En la configuración de tu aplicación, busca la sección **"Credenciales"**
2. Copia el **Access Token de Producción** (para pagos reales)
3. También puedes obtener el **Access Token de Prueba** (para pruebas)

**Importante**: Guarda estos tokens de forma segura. No los compartas en código público.

---

## Paso 2: Configurar Webhook

### 2.1 Configurar URL de Webhook

1. En el panel de Mercado Pago, ve a **"Notificaciones"** o **"Webhooks"**
2. Crea un nuevo webhook con la siguiente URL:
   ```
   https://tu-dominio.com/api/mercadopago_webhook.php
   ```

3. Para desarrollo local, puedes usar ngrok o similar:
   ```
   https://tu-ngrok-url.ngrok.io/api/mercadopago_webhook.php
   ```

### 2.2 Seleccionar Eventos

Configura el webhook para recibir los siguientes eventos:
- ✅ `payment` - Notificaciones de pagos
- ✅ `merchant_order` - Notificaciones de órdenes

### 2.3 Verificar Webhook

1. Mercado Pago enviará una notificación de prueba
2. Verifica que tu servidor reciba la notificación correctamente
3. Revisa los logs de tu aplicación para confirmar

---

## Paso 3: Configurar en la Plataforma

### 3.1 Configurar Variables de Entorno

Edita tu archivo `.env` en la raíz del proyecto:

```bash
# Mercado Pago Configuration
MERCADOPAGO_ACCESS_TOKEN=tu_access_token_aqui
MERCADOPAGO_PUBLIC_KEY=tu_public_key_aqui
```

**Opcional**: Si usas tokens de prueba para desarrollo:
```bash
MERCADOPAGO_ACCESS_TOKEN=test_tu_token_de_prueba
MERCADOPAGO_PUBLIC_KEY=test_tu_public_key
```

### 3.2 Configurar en Base de Datos (Alternativa)

Si prefieres configurar en la base de datos en lugar de `.env`:

```sql
-- Insertar configuración de Mercado Pago
INSERT INTO system_settings (setting_key, setting_value, description, created_at, updated_at)
VALUES 
    ('mercadopago_access_token', 'tu_access_token_aqui', 'Access Token de Mercado Pago', NOW(), NOW()),
    ('mercadopago_public_key', 'tu_public_key_aqui', 'Public Key de Mercado Pago', NOW(), NOW());
```

### 3.3 Instalar SDK de Mercado Pago

Si aún no tienes instalado el SDK de Mercado Pago, ejecuta:

```bash
composer require mercadopago/dx-php
```

Si no tienes Composer instalado, descarga el SDK desde:
https://github.com/mercadopago/sdk-php

---

## Paso 4: Probar la Configuración

### 4.1 Prueba de Pago en Sandbox

1. Mercado Pago ofrece un entorno de pruebas (Sandbox)
2. Usa el **Access Token de Prueba** para realizar pagos de prueba
3. Simula diferentes escenarios:
   - Pago aprobado
   - Pago rechazado
   - Pago pendiente

### 4.2 Verificar Webhook

1. Realiza un pago de prueba
2. Verifica que el webhook reciba la notificación
3. Revisa los logs en tu servidor:
   ```bash
   tail -f logs/app.log
   ```

### 4.3 Verificar Integración

1. Agrega un producto al carrito
2. Ve al checkout
3. Selecciona "Mercado Pago" como método de pago
4. Completa el proceso de pago
5. Verifica que el pago se procese correctamente

---

## Paso 5: Pasar a Producción

### 5.1 Cambiar a Token de Producción

1. En tu archivo `.env`, cambia el token de prueba por el de producción:
   ```bash
   MERCADOPAGO_ACCESS_TOKEN=APP_USR-tu_token_de_produccion
   ```

2. O actualiza en base de datos:
   ```sql
   UPDATE system_settings 
   SET setting_value = 'APP_USR-tu_token_de_produccion'
   WHERE setting_key = 'mercadopago_access_token';
   ```

### 5.2 Actualizar Webhook URL

1. En el panel de Mercado Pago, actualiza la URL del webhook
2. Cambia de URL de desarrollo a URL de producción:
   ```
   https://tu-dominio-produccion.com/api/mercadopago_webhook.php
   ```

### 5.3 Verificar Pagos Reales

1. Realiza un pago de prueba con una tarjeta real (monto pequeño)
2. Verifica que el pago se procese correctamente
3. Confirma que el webhook reciba la notificación
4. Verifica que el pedido se actualice en tu base de datos

---

## Solución de Problemas

### Error: "Access Token no configurado"

**Causa**: El token no está configurado en `.env` o en la base de datos.

**Solución**:
1. Verifica que `MERCADOPAGO_ACCESS_TOKEN` esté en tu archivo `.env`
2. O verifica que exista en `system_settings`
3. Reinicia el servidor después de cambiar el `.env`

### Error: "Webhook no configurado"

**Causa**: El webhook no está configurado en el panel de Mercado Pago.

**Solución**:
1. Ve al panel de Mercado Pago
2. Configura la URL del webhook
3. Selecciona los eventos a recibir
4. Verifica que la URL sea accesible desde internet

### Error: "Pago no encontrado"

**Causa**: El ID del pago no existe o el token es incorrecto.

**Solución**:
1. Verifica que el Access Token sea correcto
2. Verifica que el ID del pago sea válido
3. Revisa los logs para más detalles

### Error: "Firma inválida" (en webhook)

**Causa**: La notificación no viene de Mercado Pago.

**Solución**:
1. Verifica que la URL del webhook esté configurada correctamente
2. Mercado Pago no usa firma como Stripe, pero valida el origen
3. Revisa los logs para ver el payload recibido

---

## Información Adicional

### Documentación Oficial

- [Documentación de Mercado Pago](https://www.mercadopago.com.mx/developers)
- [SDK de Mercado Pago para PHP](https://github.com/mercadopago/sdk-php)
- [Webhooks de Mercado Pago](https://www.mercadopago.com.mx/developers/es/docs/integrations/webhooks)

### Tipos de Pago Soportados

Mercado Pago soporta múltiples métodos de pago:
- Tarjetas de crédito/débito
- Efectivo (OXXO, 7-Eleven)
- Transferencia bancaria (SPEI)
- Pago en tiendas

### Monedas

Mercado Pago procesa pagos en:
- MXN (Pesos Mexicanos)
- Otras monedas según configuración de tu cuenta

### Comisiones

Las comisiones de Mercado Pago varían según:
- Tipo de pago (tarjeta, efectivo, etc.)
- Volumen de transacciones
- Tipo de cuenta (personal, negocio)

Consulta las comisiones actuales en tu panel de Mercado Pago.

---

## Checklist de Configuración

- [ ] Cuenta de Mercado Pago creada y verificada
- [ ] Aplicación creada en el panel de desarrolladores
- [ ] Access Token obtenido (producción y prueba)
- [ ] URLs de redirección configuradas
- [ ] Webhook configurado con URL correcta
- [ ] Eventos seleccionados (payment, merchant_order)
- [ ] Variables de entorno configuradas
- [ ] SDK de Mercado Pago instalado
- [ ] Pagos de prueba realizados en Sandbox
- [ ] Webhook verificado con notificaciones de prueba
- [ ] Cambio a token de producción
- [ ] Webhook actualizado a URL de producción
- [ ] Pagos reales verificados

---

## Soporte

Si encuentras problemas durante la configuración:

1. Revisa los logs de tu aplicación en `logs/app.log`
2. Consulta la documentación oficial de Mercado Pago
3. Verifica que tu servidor tenga acceso a internet
4. Confirma que las URLs sean accesibles externamente

Para soporte técnico de Mercado Pago, visita su centro de ayuda oficial.
