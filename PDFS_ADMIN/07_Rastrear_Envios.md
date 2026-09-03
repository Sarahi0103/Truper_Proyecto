# Cómo Rastrear Envíos

## Manual de Administración - Ferretería FOX

---

## Índice

1. [¿Qué es el Rastreo de Envíos?](#qué-es-el-rastreo-de-envíos)
2. [Cómo Acceder al Rastreo](#cómo-acceder-al-rastreo)
3. [Cómo Rastrear un Pedido](#cómo-rastrear-un-pedido)
4. [Qué Información Verás](#qué-información-verás)
5. [Cómo Actualizar el Estado de Envío](#cómo-actualizar-el-estado-de-envío)
6. [Integración con Transportistas](#integración-con-transportistas)
7. [Solución de Problemas](#solución-de-problemas)

---

## ¿Qué es el Rastreo de Envíos?

Es la herramienta que permite saber dónde está el paquete de un cliente en tiempo real, desde que sale de la tienda hasta que llega a su destino.

**Funciones principales**:
- Ver la ubicación actual del paquete
- Ver el historial de movimientos
- Ver mapa con la ruta del envío
- Actualizar el estado del envío
- Ver información del transportista

---

## Cómo Acceder al Rastreo

1. Inicia sesión como administrador o empleado
2. Haz clic en "Admin Tienda" en el menú superior
3. Selecciona "Seguimiento / Logística" del menú desplegable
4. Verás la página de rastreo de envíos

---

## Cómo Rastrear un Pedido

### Por Número de Pedido

1. En la sección de búsqueda, escribe el número de pedido
   - Ejemplo: ORD-2024-00123
2. Haz clic en "Rastrear"
3. El sistema buscará el pedido en la base de datos
4. Verás el estado actual y el historial completo

### Por Número de Guía

1. En la sección de búsqueda, selecciona "Por Número de Guía"
2. Escribe el número de guía del transportista
   - Ejemplo: 1234567890123
3. Haz clic en "Rastrear"
4. El sistema buscará la información del transportista
5. Verás la ubicación actual del paquete

---

## Qué Información Verás

### Estado Actual

Verás uno de los siguientes estados:
- **Pendiente**: El pedido aún no ha sido enviado
- **En Proceso**: El pedido está siendo preparado
- **Enviado**: El paquete está en tránsito
- **En Tránsito**: El paquete está siendo transportado
- **Entregado**: El paquete fue entregado
- **Devuelto**: El paquete fue devuelto a la tienda

### Timeline de Seguimiento

Verás una línea de tiempo con cada actualización:

**Ejemplo**:
```
✅ Pedido Confirmado
   15 Ene 2024, 10:30 AM

✅ En Proceso
   15 Ene 2024, 2:00 PM

✅ Enviado
   16 Ene 2024, 9:00 AM
   Guía: MX-123456789

⏳ En Tránsito
   16 Ene 2024, 11:00 AM
   Ubicación: CDMX - Centro de distribución

⏳ Entregado (Pendiente)
   Estimado: 17 Ene 2024
```

### Mapa de Ubicación

- Mapa interactivo mostrando la ubicación actual
- Ruta desde la tienda hasta el destino
- Puntos intermedios si los hay

### Información del Transportista

- **Nombre del transportista**: FedEx, UPS, DHL, Estafeta, etc.
- **Número de guía**: Número de seguimiento del transportista
- **Teléfono de contacto**: Para comunicarse con el transportista
- **Enlace al sitio**: Para ver más detalles en el sitio del transportista

---

## Cómo Actualizar el Estado de Envío

### Cuándo Actualizar

- Cuando el paquete llega a un nuevo punto
- Cuando hay retrasos
- Cuando hay problemas con el envío
- Cuando el paquete es entregado

### Pasos

1. Busca el pedido en la lista
2. Haz clic en "Actualizar Estado"
3. Se abrirá un formulario

**Seleccionar el Nuevo Estado**:
- En Tránsito
- En Centro de Distribución
- En Ruta de Entrega
- Entregado
- Devuelto

**Agregar Ubicación Actual**:
- Ciudad o punto de distribución
- Ejemplo: "CDMX - Centro de distribución"

**Agregar Notas**:
- Información adicional sobre el envío
- Ejemplo: "Paquete retenido por dirección incompleta"

4. Haz clic en "Guardar"
5. El estado se actualizará en el sistema
6. El cliente verá la actualización en su seguimiento

### Actualización Automática

Si el transportista tiene integración:
- Los estados se actualizan automáticamente
- No necesitas hacerlo manualmente
- El sistema consulta la API del transportista regularmente

---

## Integración con Transportistas

### Transportistas Soportados

**FedEx**:
- Integración con API de FedEx
- Actualizaciones en tiempo real
- Mapas detallados de ruta

**UPS**:
- Integración con API de UPS
- Seguimiento preciso
- Notificaciones de entrega

**DHL**:
- Integración con API de DHL
- Seguimiento internacional
- Actualizaciones automáticas

**Estafeta**:
- Integración con API de Estafeta
- Cobertura nacional
- Actualizaciones regulares

### Configurar Integración

1. Ve a "Configuración de Envíos"
2. Selecciona el transportista
3. Ingresa tus credenciales de API
4. Configura la frecuencia de actualización
5. Guarda la configuración

---

## Solución de Problemas

### Problema: No encuentro el pedido

**Solución**:
- Verifica que el número de pedido sea correcto
- Intenta buscar por nombre de cliente
- Verifica que el pedido haya sido enviado

### Problema: El estado no se actualiza

**Solución**:
- Verifica que la integración con el transportista esté activa
- Verifica que las credenciales de API sean correctas
- Actualiza manualmente si la integración falla

### Problema: El mapa no se muestra

**Solución**:
- Verifica que tengas conexión a internet
- Verifica que el servicio de mapas esté activo
- Recarga la página

### Problema: El cliente dice que no recibió el paquete

**Solución**:
- Verifica el estado en el sistema
- Contacta al transportista
- Verifica la dirección de entrega
- Inicia proceso de reclamación si es necesario

---

## Buenas Prácticas

### Actualizar Regolarmente

- Actualiza los estados al menos una vez al día
- Esto mantiene informados a los clientes
- Ayuda a identificar problemas rápidamente

### Comunicar con Clientes

- Notifica a los clientes de actualizaciones importantes
- Proporciona el número de guía
- Ofrece ayuda si hay problemas

### Verificar Direcciones

- Verifica que las direcciones sean correctas antes de enviar
- Contacta al cliente si hay dudas
- Esto reduce devoluciones

### Archivar Envíos Completados

- Los envíos completados se archivan automáticamente
- Mantén el historial para referencia
- Útil para análisis de rendimiento

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
