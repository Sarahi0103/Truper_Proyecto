# Cómo Configurar Métodos de Pago

## Manual de Administración - Ferretería FOX

---

## Índice

1. [¿Qué es la Configuración de Métodos de Pago?](#qué-es-la-configuración-de-métodos-de-pago)
2. [Cómo Acceder a Configuración de Pagos](#cómo-acceder-a-configuración-de-pagos)
3. [Sección 1: Bancos Mexicanos](#seción-1-bancos-mexicanos)
4. [Sección 2: Métodos de Pago](#sección-2-métodos-de-pago)
5. [Sección 3: Configuración Fiscal SAT](#sección-3-configuración-fiscal-sat)
6. [Solución de Problemas](#solución-de-problemas)

---

## ¿Qué es la Configuración de Métodos de Pago?

Es la sección donde se configuran los bancos mexicanos donde tu empresa tiene cuentas para recibir pagos por SPEI y transferencias, así como las comisiones y configuración de cada método de pago.

**Funciones principales**:
- Configurar bancos mexicanos con CLABEs
- Configurar comisiones de cada método de pago
- Habilitar o deshabilitar métodos de pago
- Configurar datos fiscales para facturación

---

## Cómo Acceder a Configuración de Pagos

1. Inicia sesión como administrador
2. Haz clic en "Admin Tienda" en el menú superior
3. Selecciona "Configuración de Pagos" del menú desplegable
4. Verás la página de configuración de métodos de pago

---

## Sección 1: Bancos Mexicanos

### Qué Configurar Aquí

Los bancos donde tu empresa tiene cuentas para recibir pagos. Los clientes verán estos bancos cuando paguen con SPEI o transferencia.

### Cómo Agregar un Banco

**Paso 1: Preparar datos bancarios**
- Nombre del banco (ej: BBVA Bancomer)
- Código SAT del banco (3 dígitos)
- Tu CLABE (18 dígitos)
- Número de cuenta (opcional)
- Titular de la cuenta
- RFC del titular (opcional)

**Paso 2: Agregar en el sistema**
1. Haz clic en "+ Agregar Nuevo Banco"
2. Ingresa el nombre del banco (ej: BBVA Bancomer)
3. Ingresa el código SAT del banco (3 dígitos)
   - Ejemplo: 012 para BBVA
   - Puedes consultar el catálogo SAT si no lo sabes

4. Ingresa tu CLABE (18 dígitos)
   - El sistema validará automáticamente que sea correcta
   - Si hay error, verás un mensaje en rojo
   - Corrige y vuelve a intentar

5. Ingresa el número de cuenta (opcional)
   - No es obligatorio pero ayuda al cliente

6. Ingresa el titular de la cuenta
   - Nombre o razón social exacta

7. Ingresa el RFC del titular (opcional)
   - 13 caracteres
   - Útil para facturación

8. Marca qué métodos soporta:
   - ☑ SPEI (si aceptas pagos SPEI)
   - ☑ Tarjeta (si aceptas pagos con tarjeta)
   - ☑ Transferencia (si aceptas transferencias regulares)

9. Marca "Activo" para que aparezca en el checkout

10. Haz clic en "Guardar"

**Resultado**:
- El banco quedará guardado en el sistema
- Los clientes verán este banco cuando paguen
- La CLABE se mostrará al cliente

### Cómo Editar un Banco

1. En la lista de bancos, haz clic en "Editar"
2. Modifica los campos necesarios:
   - Nombre del banco
   - CLABE
   - Titular
   - Métodos soportados
3. Haz clic en "Guardar"
4. Los cambios se aplicarán inmediatamente

### Cómo Eliminar un Banco

1. En la lista de bancos, haz clic en "Eliminar"
2. Confirma la eliminación
3. El banco ya no aparecerá en el checkout
4. Los clientes no podrán seleccionarlo para pagos

**Consideración**:
- Si eliminas un banco, asegúrate de tener otros configurados
- Los clientes no podrán pagar a ese banco

---

## Sección 2: Métodos de Pago

### Qué Configurar Aquí

Las comisiones, montos mínimos/máximos y disponibilidad de cada método de pago.

### Métodos Disponibles

- **Tarjeta de Crédito/Débito**: Pagos con tarjeta Visa, Mastercard, Amex
- **SPEI**: Transferencias electrónicas interbancarias
- **Transferencia**: Transferencias bancarias regulares
- **Mercado Pago**: Pagos a través de Mercado Pago
- **Stripe**: Pagos a través de Stripe
- **Efectivo**: Pago contra entrega

### Cómo Configurar Comisiones

**Paso 1: Seleccionar el método**
1. Busca el método de pago que quieres configurar
2. Haz clic en "Editar"

**Paso 2: Configurar parámetros**
- **Comisión porcentual**: Porcentaje que cobras sobre el monto
  - Ejemplo: 3.5 para 3.5%
  - Si el cliente paga $1,000, cobras $35 de comisión

- **Comisión fija**: Monto fijo que cobras
  - Ejemplo: 3.00 para $3.00
  - Se suma a la comisión porcentual

- **Monto mínimo**: Monto mínimo para usar este método
  - Ejemplo: 100 para $100
  - Si el cliente paga menos, no puede usar este método

- **Monto máximo**: Monto máximo (opcional)
  - Ejemplo: 50000 para $50,000
  - Si el cliente paga más, no puede usar este método

**Paso 3: Guardar**
1. Haz clic en "Guardar"
2. La configuración se aplicará inmediatamente

### Ejemplo de Cálculo de Comisión

Si configuras:
- Comisión porcentual: 3.5%
- Comisión fija: $3.00

Para un pago de $1,000:
- Comisión porcentual: $1,000 × 3.5% = $35.00
- Comisión fija: $3.00
- Total comisión: $38.00
- Total cliente paga: $1,038.00

### Cómo Habilitar/Deshabilitar un Método

**Habilitar**:
1. Busca el método de pago
2. Usa el interruptor (toggle) para habilitar
3. El método aparecerá en el checkout
4. Los clientes podrán usarlo

**Deshabilitar**:
1. Busca el método de pago
2. Usa el interruptor (toggle) para deshabilitar
3. El método ya no aparecerá en el checkout
4. Los clientes no podrán usarlo

**Cuándo deshabilitar**:
- Cuando hay problemas con el procesador de pagos
- Cuando temporalmente no aceptas cierto método
- Cuando estás haciendo mantenimiento

---

## Sección 3: Configuración Fiscal SAT

### Qué es Esta Sección

Es la misma configuración fiscal que en "Facturación & Pagos SAT". Aquí también puedes configurar los datos fiscales de tu empresa para emitir facturas CFDI 4.0.

### Datos a Configurar

**Facturapi API Key**:
- Clave para timbrar facturas ante el SAT
- Obténla en facturapi.mx

**RFC de la Empresa**:
- Registro Federal de Contribuyentes
- 13 caracteres

**Razón Social**:
- Nombre fiscal de tu empresa
- Debe coincidir con tu constancia fiscal

**Régimen Fiscal**:
- Selecciona del menú (601, 603, 606, 612, 626)
- Consulta tu constancia fiscal

**Código Postal Fiscal**:
- Código postal de tu domicilio fiscal
- 5 dígitos

**Email de Facturación**:
- Email para notificaciones de facturación

**Teléfono**:
- Teléfono de contacto fiscal

**Dirección Fiscal**:
- Domicilio fiscal completo

### Cómo Configurar

1. Completa todos los campos requeridos
2. Verifica que los datos sean correctos
3. Haz clic en "Guardar"
4. La configuración quedará guardada

---

## Solución de Problemas

### Problema: CLABE inválida

**Solución**:
- Verifica que tenga 18 dígitos
- Verifica que sean solo números
- Verifica con tu banco que la CLABE sea correcta
- Usa el validador de CLABE de tu banco

### Problema: Banco no aparece en checkout

**Solución**:
- Verifica que el banco esté marcado como "Activo"
- Verifica que soporte el método de pago seleccionado
- Verifica que no esté eliminado

### Problema: Comisión no se aplica

**Solución**:
- Verifica que la configuración esté guardada
- Verifica que el método esté habilitado
- Verifica que el monto cumpla con los requisitos

### Problema: RFC inválido

**Solución**:
- Verifica que tenga 13 caracteres
- Verifica el formato (3-4 letras + 6 dígitos + 3 alfanuméricos)
- Consulta tu constancia fiscal
- Consulta a tu contador si tienes dudas

---

## Buenas Prácticas

### Mantener Bancos Actualizados

- Revisa las CLABEs regularmente
- Actualiza si cambias de cuenta bancaria
- Verifica que los bancos estén activos

### Configurar Comisiones Adecuadas

- Investiga comisiones del mercado
- No cobres comisiones muy altas
- Ofrece métodos sin comisión para atraer clientes

### Probar Configuración

- Haz pruebas de pago en modo sandbox
- Verifica que las comisiones se calculen correctamente
- Verifica que los bancos aparezcan en checkout

### Monitorear Métodos de Pago

- Revisa qué métodos usan más los clientes
- Ajusta comisiones según el uso
- Habilita métodos populares

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
