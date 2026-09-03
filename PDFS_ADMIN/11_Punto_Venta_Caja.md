# Cómo Usar el Punto de Venta (Caja)

## Manual de Administración - Ferretería FOX

---

## Índice

1. [¿Qué es el Punto de Venta (Caja)?](#qué-es-el-punto-de-venta-caja)
2. [Cómo Acceder al Punto de Venta](#cómo-acceder-al-punto-de-venta)
3. [Paso 1: Abrir el Cajón](#paso-1-abrir-el-cajón)
4. [Paso 2: Buscar Productos](#paso-2-buscar-productos)
5. [Paso 3: Agregar Productos al Carrito](#paso-3-agregar-productos-al-carrito)
6. [Paso 4: Datos del Cliente](#paso-4-datos-del-cliente)
7. [Paso 5: Método de Pago](#paso-5-método-de-pago)
8. [Paso 6: Procesar la Venta](#paso-6-procesar-la-venta)
9. [Paso 7: Cerrar el Cajón](#paso-7-cerrar-el-cajón)
10. [Solución de Problemas](#solución-de-problemas)

---

## ¿Qué es el Punto de Venta (Caja)?

Es el sistema para registrar ventas en la tienda física. Es como una caja registradora moderna donde se agregan productos, se cobra al cliente y se imprime el ticket.

**Funciones principales**:
- Abrir y cerrar el cajón de dinero
- Buscar y agregar productos
- Procesar diferentes métodos de pago
- Imprimir tickets de venta
- Registrar todas las transacciones

---

## Cómo Acceder al Punto de Venta

1. Inicia sesión como administrador o empleado
2. Haz clic en "Admin Local" en el menú superior
3. Selecciona "Caja / Punto de Venta" del menú desplegable
4. Verás la página del punto de venta

---

## Paso 1: Abrir el Cajón

### Qué es el Cajón

Es el cajón físico donde se guarda el dinero en efectivo. Debe abrirse al inicio del turno y cerrarse al final.

### Cómo Abrir el Cajón

1. Verifica el estado del cajón en la parte superior
2. Si dice "Cerrado", haz clic en "Abrir Cajón"
3. Ingresa el monto inicial de dinero que hay en el cajón
   - Ejemplo: $5,000.00 si ese es el fondo de caja
   - Este es el dinero que había antes de iniciar el turno
4. Haz clic en "Confirmar"
5. El cajón se abrirá físicamente (si está conectado)
6. El estado cambiará a "Abierto"

### Qué Verás Cuando el Cajón Está Abierto

- **Saldo Inicial**: Dinero que había al abrir
- **Ventas del Turno**: Se actualiza en tiempo real con cada venta
- **Saldo Actual**: Inicial + ventas (se actualiza automáticamente)

### Importante

- Solo el personal autorizado puede abrir el cajón
- El monto inicial debe coincidir con el dinero físico
- Si hay discrepancia, reporta al administrador

---

## Paso 2: Buscar Productos

### Cómo Buscar por Nombre

1. En la barra de búsqueda, escribe el nombre del producto
   - Ejemplo: "taladro", "martillo", "clavo"
2. Haz clic en "Buscar" o presiona Enter
3. Verás una lista de productos que coinciden
4. Selecciona el producto que quieres agregar

### Cómo Escanear Código de Barras

1. Usa el lector de código de barras conectado a la computadora
2. Pasa el código de barras del producto por el lector
3. El producto se agregará automáticamente al carrito
4. El sistema buscará el producto por su código

**Si no tienes lector**:
- Puedes escribir el código de barras manualmente
- Ingresa el código en el campo de búsqueda
- Haz clic en "Buscar"

### Cómo Buscar por SKU

1. Escribe el SKU del producto (código interno)
   - Ejemplo: TAL-1800, MAR-0500
2. Haz clic en "Buscar"
3. El producto aparecerá en los resultados

### Qué Verás en los Resultados

- Imagen del producto
- Nombre del producto
- SKU
- Precio unitario
- Stock disponible
- Botón "Agregar"

---

## Paso 3: Agregar Productos al Carrito

### Cómo Agregar un Producto

1. Busca el producto como se explicó arriba
2. Haz clic en el producto o en "Agregar"
3. El producto aparecerá en el carrito de venta

### Cómo Modificar la Cantidad

1. En el carrito, busca el producto
2. Cambia el número en el campo de cantidad
3. El precio total se actualizará automáticamente
   - Ejemplo: 1 taladro a $1,299 = $1,299
   - 2 taladros a $1,299 = $2,598

### Cómo Eliminar un Producto

1. En el carrito, haz clic en "Eliminar" del producto
2. El producto desaparecerá del carrito
3. El total se actualizará automáticamente

### Qué Verás en el Carrito

Para cada producto:
- Nombre del producto
- SKU
- Cantidad
- Precio unitario
- Precio total (cantidad × precio unitario)
- Botón "Eliminar"

En la parte inferior:
- **Subtotal**: Suma de todos los productos
- **IVA (16%)**: Impuesto al valor agregado
- **Total**: Subtotal + IVA

---

## Paso 4: Datos del Cliente (Opcional)

### Por Qué Agregar Datos del Cliente

- Para emitir factura fiscal
- Para tener un registro de la venta
- Para programas de lealtad
- Para contacto futuro

### Cómo Agregar un Cliente Existente

1. Haz clic en "Buscar Cliente"
2. Escribe el nombre, email o teléfono del cliente
3. Selecciona el cliente de la lista
4. Los datos se llenarán automáticamente

### Cómo Crear un Nuevo Cliente

1. Haz clic en "Nuevo Cliente"
2. Ingresa los datos:
   - **Nombre**: Nombre completo del cliente
   - **Email**: Correo electrónico
   - **Teléfono**: Número de teléfono
3. Si requiere factura, ingresa:
   - **RFC**: Registro Federal de Contribuyentes
   - **Razón Social**: Nombre fiscal
   - **Régimen Fiscal**: Selecciona del menú
4. Haz clic en "Guardar"
5. El cliente quedará registrado en el sistema

### Cómo Omitir Datos del Cliente

- Si el cliente no requiere factura
- Si es una venta rápida
- Si el cliente prefiere anonimato
- Simplemente omite este paso y continúa

---

## Paso 5: Método de Pago

### Opciones Disponibles

#### Efectivo

**Qué hacer**:
1. Selecciona "Efectivo"
2. El sistema mostrará el total a pagar
3. Ingresa cuánto dinero te dio el cliente
   - Ejemplo: Total $1,500, cliente paga $2,000
4. El sistema calculará el cambio automáticamente
   - Cambio = $2,000 - $1,500 = $500
5. Verifica que tengas el cambio suficiente
6. Haz clic en "Procesar Venta"

#### Tarjeta de Crédito/Débito

**Si tienes terminal POS conectado**:
1. Selecciona "Tarjeta"
2. Haz clic en "Conectar terminal POS"
3. El cliente pasa su tarjeta en el terminal
4. Espera la aprobación
5. Si es aprobada, continúa
6. Si es rechazada, pide otro método de pago

**Si no tienes terminal**:
1. Selecciona "Tarjeta"
2. Ingresa los últimos 4 dígitos de la tarjeta
3. Ingresa el monto
4. Haz clic en "Procesar"
5. El sistema procesará el pago

#### Transferencia

**Qué hacer**:
1. Selecciona "Transferencia"
2. El cliente hace la transferencia a tu cuenta
3. Verifica que el pago haya llegado
4. Confirma la venta

#### Vale

**Qué es**:
Usado para clientes que tienen crédito o vale de regalo.

**Qué hacer**:
1. Selecciona "Vale"
2. Ingresa el número de vale
3. Verifica que el vale sea válido
4. Verifica que tenga saldo suficiente
5. Confirma la venta

---

## Paso 6: Procesar la Venta

### Pasos Finales

1. **Revisar el carrito**:
   - Verifica que todos los productos sean correctos
   - Verifica las cantidades
   - Verifica que no haya productos extra

2. **Verificar el total**:
   - Revisa el subtotal
   - Revisa el IVA
   - Revisa el total final

3. **Seleccionar el método de pago**:
   - Selecciona el método que el cliente usará
   - Si es efectivo, ingresa el monto recibido
   - Si es tarjeta, procesa el pago

4. **Confirmar la venta**:
   - Haz clic en "Procesar Venta"
   - El sistema realizará las siguientes acciones:
     - Registrará la venta en la base de datos
     - Actualizará el inventario (restará las cantidades)
     - Imprimirá el ticket automáticamente
     - Actualizará el saldo del cajón
     - Actualizará las ventas del turno

### Qué Recibirás

- **Ticket impreso**: Con todos los detalles de la venta
- **Confirmación en pantalla**: Número de venta
- **Actualización de inventario**: Stock reducido automáticamente

### Qué Incluye el Ticket

- Número de venta
- Fecha y hora
- Datos del cliente (si se proporcionaron)
- Lista de productos con cantidades y precios
- Subtotal, IVA, total
- Método de pago
- Cambio (si aplica)

---

## Paso 7: Cerrar el Cajón

### Cuándo Cerrar el Cajón

- Al final del turno
- Al final del día
- Cuando se hace cambio de cajero
- Cuando se hace corte de caja

### Pasos para Cerrar el Cajón

1. Haz clic en "Cerrar Cajón"
2. El sistema mostrará un resumen:
   - **Ventas del turno**: Total de dinero generado
   - **Saldo inicial**: Dinero que había al abrir
   - **Saldo final**: Dinero que debería haber
   - **Diferencia**: Debe ser $0.00

3. **Cuenta el dinero físico**:
   - Cuenta todo el efectivo en el cajón
   - Verifica billetes y monedas
   - Anota el total

4. **Compara con el sistema**:
   - Si coincide: El saldo es correcto
   - Si no coincide: Hay una discrepancia

5. **Si coincide**:
   - Haz clic en "Confirmar Cierre"
   - El cajón se cerrará
   - El estado cambiará a "Cerrado"
   - Se generará reporte del turno

6. **Si no coincide**:
   - No confirmes el cierre
   - Reporta la discrepancia al administrador
   - Investiga la causa del error
   - Corrige antes de cerrar

### Qué Hacer con Discrepancias

**Si falta dinero**:
- Revisa si hubo errores en el sistema
- Revisa si hubo ventas no registradas
- Reporta al administrador inmediatamente

**Si sobra dinero**:
- Revisa si hubo devoluciones no registradas
- Revisa si hubo errores en el sistema
- Reporta al administrador

### Reporte de Turno

Al cerrar el cajón, el sistema genera un reporte que incluye:
- Ventas totales del turno
- Ventas por método de pago
- Productos más vendidos
- Número de transacciones
- Hora de apertura y cierre

---

## Solución de Problemas

### Problema: No puedo abrir el cajón

**Solución**:
- Verifica que tengas permisos de administrador o empleado
- Verifica que el cajón no esté ya abierto por otro usuario
- Verifica que el cajón esté conectado
- Reinicia el sistema si persiste

### Problema: El producto no aparece en la búsqueda

**Solución**:
- Verifica que el nombre esté escrito correctamente
- Intenta buscar por SKU
- Verifica que el producto esté activo en el inventario
- Verifica que tenga stock disponible

### Problema: El stock no se actualiza

**Solución**:
- Verifica que la venta se haya procesado correctamente
- Verifica que no haya error en el sistema
- Actualiza el inventario manualmente si es necesario
- Reporta a soporte técnico si persiste

### Problema: El ticket no se imprime

**Solución**:
- Verifica que la impresora esté conectada
- Verifica que tenga papel
- Verifica que esté encendida
- Reimprime el ticket manualmente

### Problema: Discrepancia en el cierre de cajón

**Solución**:
- Cuenta el dinero nuevamente
- Revisa las ventas del turno
- Revisa si hubo devoluciones
- Reporta al administrador
- No cierres el cajón hasta resolver

---

## Buenas Prácticas

### Abrir y Cerrar el Cajón Correctamente

- Siempre abre el cajón al inicio del turno
- Siempre cierra el cajón al final
- Usa el monto inicial correcto
- Verifica el saldo al cerrar

### Verificar el Carrito Antes de Cobrar

- Revisa que todos los productos sean correctos
- Verifica las cantidades
- Confirma con el cliente
- Evita errores costosos

### Ofrecer Factura

- Pregunta si el cliente requiere factura
- Si es cliente B2B, casi siempre requiere factura
- Facilita el proceso de facturación

### Mantener el Área Ordenada

- Mantén el área de caja limpia
- Organiza el dinero en el cajón
- Mantén los productos ordenados
- Proyecta imagen profesional

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
