# Cómo Configurar Facturación y Pagos

## Manual de Administración - Ferretería FOX

---

## Índice

1. [¿Qué es la Configuración de Facturación?](#qué-es-la-configuración-de-facturación)
2. [Cómo Acceder a Facturación](#cómo-acceder-a-facturación)
3. [Sección 1: Pasarelas de Cobro en Línea](#sección-1-pasarelas-de-cobro-en-línea)
4. [Sección 2: Cuentas y Tarjetas para Recibir Pagos](#sección-2-cuentas-y-tarjetas-para-recibir-pagos)
5. [Sección 3: Configuración Fiscal CFDI 4.0](#sección-3-configuración-fiscal-cfdi-40)
6. [Sección 4: Monitor de Facturas](#sección-4-monitor-de-facturas)
7. [Solución de Problemas](#solución-de-problemas)

---

## ¿Qué es la Configuración de Facturación?

Es la sección donde se configuran todos los aspectos relacionados con la emisión de facturas electrónicas CFDI 4.0 conforme a las normas del SAT (Servicio de Administración Tributaria) y las pasarelas de pago para procesar pagos en línea.

**Funciones principales**:
- Configurar pasarelas de pago (Stripe, Mercado Pago)
- Configurar cuentas bancarias para recibir pagos
- Configurar datos fiscales de la empresa
- Emitir facturas CFDI 4.0
- Cancelar facturas ante el SAT
- Monitorear todas las facturas emitidas

---

## Cómo Acceder a Facturación

1. Inicia sesión como administrador
2. Haz clic en "Admin Tienda" en el menú superior
3. Selecciona "Facturación & Pagos SAT" del menú desplegable
4. Verás la página de configuración de facturación y pagos

---

## Sección 1: Pasarelas de Cobro en Línea

### Qué Configurar Aquí

Las claves de API y configuración para procesar pagos en línea a través de:
- Mercado Pago
- Stripe
- SPEI (transferencias bancarias)

### Configuración de Mercado Pago

**Paso 1: Obtener credenciales**
1. Ve a www.mercadopago.com.mx
2. Inicia sesión con tu cuenta
3. Ve a "Credenciales" o "Desarrolladores"
4. Copia tu "Public Key"
5. Copia tu "Access Token"

**Paso 2: Configurar en el sistema**
1. En la sección "Pasarelas de Cobro", busca "Mercado Pago"
2. Pega la "Public Key" en el campo correspondiente
3. Pega el "Access Token" en el campo correspondiente
4. Selecciona el entorno:
   - **Producción**: Para procesar pagos reales
   - **Sandbox**: Para pruebas (no procesa pagos reales)
5. Haz clic en "Guardar"

### Configuración de Stripe

**Paso 1: Obtener credenciales**
1. Ve a dashboard.stripe.com
2. Inicia sesión con tu cuenta
3. Ve a "API Keys"
4. Copia tu "Publishable Key" (empieza con pk_)
5. Copia tu "Secret Key" (empieza con sk_)

**Paso 2: Configurar en el sistema**
1. En la sección "Pasarelas de Cobro", busca "Stripe"
2. Pega la "Publishable Key" en el campo correspondiente
3. Pega la "Secret Key" en el campo correspondiente
4. Selecciona el entorno (Producción o Sandbox)
5. Haz clic en "Guardar"

### Configuración de SPEI

**Qué es SPEI**:
Sistema de Pagos Electrónicos Interbancarios. Permite que los clientes hagan transferencias bancarias directamente a tu cuenta.

**Paso 1: Configurar datos bancarios**
1. En la sección "Configuración SPEI", ingresa:
   - **Banco Emisor**: Nombre de tu banco (ej: BBVA Bancomer)
   - **CLABE Interbancaria**: Tu CLABE (18 dígitos)
   - **Titular de la Cuenta**: Nombre o razón social del titular

**Paso 2: Validar CLABE**
- El sistema validará automáticamente que la CLABE sea correcta
- Si hay error, verás un mensaje en rojo
- Corrige la CLABE y vuelve a intentar

**Paso 3: Guardar**
1. Haz clic en "Guardar"
2. Los clientes verán estos datos cuando paguen con SPEI

---

## Sección 2: Cuentas y Tarjetas para Recibir Pagos

### Qué es Esta Sección

Aquí configuras las cuentas bancarias y tarjetas donde recibirás el dinero de los clientes que pagan en línea.

### Cómo Agregar una Cuenta de Stripe

**Paso 1: Crear cuenta en Stripe**
1. En Stripe Dashboard, crea una "Connected Account"
2. Obtén el ID de la cuenta (empieza con acct_)
3. Esta es la cuenta donde recibirás los pagos

**Paso 2: Agregar en el sistema**
1. Haz clic en "+ Agregar Nueva Cuenta/Tarjeta"
2. Ingresa un nombre descriptivo (ej: "Cuenta Principal Stripe")
3. Selecciona "Stripe" como pasarela de pago
4. Ingresa el ID de tu cuenta de Stripe
5. Haz clic en "Guardar"
6. Marca como "Principal" si quieres que sea la cuenta por defecto

### Cómo Agregar una Cuenta de Mercado Pago

**Paso 1: Crear cuenta en Mercado Pago**
1. En Mercado Pago, crea una cuenta de cobro
2. Obtén el ID de la cuenta (collector ID)

**Paso 2: Agregar en el sistema**
1. Haz clic en "+ Agregar Nueva Cuenta/Tarjeta"
2. Ingresa un nombre descriptivo
3. Selecciona "Mercado Pago" como pasarela de pago
4. Ingresa el ID de tu cuenta de Mercado Pago
5. Haz clic en "Guardar"

### Cómo Agregar una Cuenta Bancaria Directa

**Paso 1: Preparar datos bancarios**
- CLABE de tu cuenta (18 dígitos)
- Nombre del banco
- Últimos 4 dígitos de la cuenta
- Titular de la cuenta
- RFC del titular (opcional)

**Paso 2: Agregar en el sistema**
1. Haz clic en "+ Agregar Nueva Cuenta/Tarjeta"
2. Ingresa un nombre descriptivo (ej: "Cuenta BBVA")
3. Selecciona "Cuenta Bancaria Directa"
4. Ingresa el nombre del banco
5. Ingresa tu CLABE (18 dígitos)
6. Ingresa los últimos 4 dígitos
7. Ingresa el titular de la cuenta
8. Ingresa el RFC del titular (opcional)
9. Haz clic en "Guardar"

### Cómo Establecer una Cuenta como Principal

1. En la lista de cuentas, busca la que quieres como principal
2. Haz clic en "Establecer Principal"
3. Esta cuenta se usará por defecto para recibir pagos
4. Solo puede haber una cuenta principal a la vez

### Cómo Editar o Eliminar una Cuenta

**Editar**:
1. Haz clic en "Editar" de la cuenta
2. Modifica la información necesaria
3. Haz clic en "Guardar"

**Eliminar**:
1. Haz clic en "Eliminar" de la cuenta
2. Confirma la eliminación
3. La cuenta ya no estará disponible para recibir pagos

---

## Sección 3: Configuración Fiscal CFDI 4.0

### Qué Configurar Aquí

Los datos fiscales de tu empresa para emitir facturas válidas ante el SAT.

### Paso 1: Facturapi API Key

**Qué es Facturapi**:
Es un PAC (Proveedor Autorizado de Certificación) que se encarga de timbrar las facturas ante el SAT.

**Cómo obtener API Key**:
1. Ve a www.facturapi.mx
2. Crea una cuenta
3. Ve a "Configuración" → "API Keys"
4. Crea una nueva API Key
5. Copia la API Key (empieza con sk_live_ o sk_test_)

**Configurar en el sistema**:
1. En la sección "Configuración Fiscal CFDI 4.0"
2. Pega la API Key en el campo correspondiente
3. Selecciona el entorno (Producción o Sandbox)

### Paso 2: RFC de la Empresa

**Qué es el RFC**:
Registro Federal de Contribuyentes. Es el identificador fiscal de tu empresa.

**Formato**:
- 13 caracteres
- Ejemplo: ABCD123456XYZ

**Configurar**:
1. Ingresa tu RFC en el campo "RFC de la Sucursal/Empresa"
2. El sistema validará que el formato sea correcto
3. Si hay error, corrige y vuelve a intentar

### Paso 3: Razón Social Exacta

**Qué es**:
Nombre fiscal de tu empresa tal como aparece en tu constancia fiscal.

**Importante**:
Debe coincidir exactamente con tu constancia fiscal. Cualquier diferencia puede causar rechazo del SAT.

**Configurar**:
1. Ingresa la razón social exacta
2. Incluye "S.A. de C.V.", "S. de R.L.", etc. si aplica
3. Verifica que coincida con tu constancia

### Paso 4: Régimen Fiscal

**Qué es**:
El régimen fiscal bajo el cual opera tu empresa según el SAT.

**Opciones comunes**:
- 601: General de Ley Personas Morales
- 603: Personas Morales con Fines no Lucrativos
- 606: Régimen Simplificado de Confianza
- 612: Personas Físicas con Actividades Empresariales
- 626: Régimen Simplificado de Confianza (RESICO)

**Configurar**:
1. Selecciona tu régimen fiscal del menú
2. Si no estás seguro, revisa tu constancia fiscal
3. Consulta a tu contador si tienes dudas

### Paso 5: Código Postal Fiscal

**Qué es**:
Código postal del domicilio fiscal de tu empresa.

**Importante**:
Debe ser el código postal donde está tu domicilio fiscal, no necesariamente donde está la tienda.

**Configurar**:
1. Ingresa el código postal (5 dígitos)
2. Verifica que sea correcto

### Paso 6: Email de Facturación

**Qué es**:
Email donde recibirás notificaciones de facturación.

**Configurar**:
1. Ingresa el email de facturación
2. Usa un email que revises regularmente
3. Puede ser el mismo que el email general de la empresa

### Paso 7: Teléfono

**Qué es**:
Teléfono de contacto fiscal.

**Configurar**:
1. Ingresa el teléfono con lada
2. Ejemplo: 55-1234-5678

### Paso 8: Dirección Fiscal

**Qué es**:
Domicilio fiscal completo de tu empresa.

**Configurar**:
1. Ingresa la dirección completa
2. Incluye calle, número, colonia, ciudad, estado, código postal
3. Debe coincidir con tu constancia fiscal

### Paso 9: Guardar Configuración

1. Revisa que todos los datos sean correctos
2. Haz clic en "Guardar"
3. La configuración quedará guardada
4. Podrás emitir facturas con esta configuración

---

## Sección 4: Monitor de Facturas

### Qué Verás Aquí

- Lista de todas las facturas emitidas
- Estado de cada factura (activa, cancelada)
- KPIs: total de facturas, facturas activas, canceladas

### KPIs Principales

**Total de Facturas Emitidas**:
- Cantidad total de facturas emitidas en el periodo

**Facturas Activas en SAT**:
- Facturas que están vigentes y válidas

**Facturas Canceladas**:
- Facturas que han sido canceladas ante el SAT

**Público en General**:
- Facturas emitidas sin RFC del cliente

### Cómo Emitir una Factura

1. Busca el pedido que quieres facturar
2. Haz clic en "Facturar"
3. Te llevará a la sección de facturación

**Paso 1: Datos del Cliente**
- Si el cliente ya tiene datos fiscales, aparecerán automáticamente
- Si no, ingresa:
  - RFC del cliente
  - Razón Social
  - Régimen Fiscal
  - Uso de CFDI
  - Código Postal Fiscal
  - Email de Facturación

**Paso 2: Seleccionar Uso de CFDI**
- G01: Adquisición de mercancías (común)
- G02: Devoluciones, descuentos o bonificaciones
- G03: Gastos en general (común)
- P01: Por definir

**Paso 3: Revisar Datos**
- Verifica que todos los datos sean correctos
- Revisa el monto a facturar
- Confirma los productos incluidos

**Paso 4: Emitir**
1. Haz clic en "Emitir Factura"
2. El sistema generará la factura CFDI 4.0
3. La factura se enviará al SAT para timbrado
4. El cliente recibirá:
   - PDF de la factura por email
   - XML de la factura por email
   - Número de folio fiscal

### Cómo Cancelar una Factura

**Cuándo cancelar**:
- Cuando el cliente solicita cancelación
- Cuando hay error en la factura
- Cuando la operación no se realizó

**Pasos**:
1. Busca la factura que quieres cancelar
2. Haz clic en "Cancelar"
3. Selecciona el motivo de cancelación SAT:
   - 01: Comprobante emitido con errores con relación
   - 02: Comprobante emitido con errores sin relación
   - 03: No se realizó la operación (común)
   - 04: Operación nominativa relacionada en factura global

4. Agrega notas de la cancelación
5. Confirma la cancelación
6. El sistema enviará la cancelación al SAT
7. El cliente recibirá notificación

**Plazos de cancelación**:
- Generalmente: hasta 72 horas después de emisión
- Algunos casos: hasta 30 días
- Verifica con tu contador los plazos específicos

### Cómo Descargar PDF y XML

**PDF**:
1. Haz clic en "Descargar PDF" de la factura
2. El archivo se descargará automáticamente
3. Puedes imprimirlo o enviarlo por email

**XML**:
1. Haz clic en "Descargar XML" de la factura
2. El archivo se descargará automáticamente
3. Este es el archivo oficial para el SAT

---

## Solución de Problemas

### Problema: Error al configurar Mercado Pago

**Solución**:
- Verifica que la Public Key y Access Token sean correctas
- Verifica que la cuenta esté activa en Mercado Pago
- Intenta con credenciales de sandbox para pruebas

### Problema: Error al configurar Stripe

**Solución**:
- Verifica que las API keys sean correctas
- Verifica que la cuenta esté activa en Stripe
- Verifica que estés usando las keys correctas (test vs live)

### Problema: CLABE inválida

**Solución**:
- Verifica que tenga 18 dígitos
- Verifica que sean solo números
- Verifica que sea la CLABE correcta con tu banco
- Usa el validador de CLABE de tu banco

### Problema: Error al emitir factura

**Solución**:
- Verifica que la API Key de Facturapi sea correcta
- Verifica que los datos fiscales sean válidos
- Verifica que el RFC del cliente sea correcto
- Verifica que haya conexión a internet

### Problema: Error al cancelar factura

**Solución**:
- Verifica que el motivo de cancelación sea válido
- Verifica que esté dentro del plazo permitido
- Verifica que la factura no haya sido ya cancelada
- Contacta a Facturapi si persiste

---

## Buenas Prácticas

### Mantener Credenciales Seguras

- No compartas tus API keys
- Cámbialas regularmente
- Usa credenciales diferentes para producción y pruebas

### Verificar Datos Fiscales

- Revisa tus datos fiscales regularmente
- Actualiza si hay cambios
- Consulta a tu contador si tienes dudas

### Probar Configuración

- Usa modo sandbox para pruebas
- Verifica que todo funcione antes de usar producción
- Haz pruebas de emisión de facturas

### Monitorear Facturas

- Revisa las facturas emitidas regularmente
- Verifica que no haya errores
- Cancela facturas con errores rápidamente

---

## Contacto de Soporte

Si tienes problemas o preguntas:
- Soporte técnico: soporte@ferreteriafox.com
- Teléfono: 55-1234-5678
- Horario: Lunes a Viernes, 9:00 AM - 6:00 PM

---

**Documento Versión**: 1.0  
**Fecha de Creación**: 2026  
**Última Actualización**: 2026
