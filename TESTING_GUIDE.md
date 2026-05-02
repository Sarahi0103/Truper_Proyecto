# Manual Testing Guide - Image Management Optimizations

## 📋 Checklist de Pruebas

### Parte 1: Verificación Básica

- [ ] Acceder a http://localhost:8088/admin_supply.php
- [ ] Hacer login como administrador
- [ ] Verificar que la página carga sin errores
- [ ] Verificar que no hay errores en consola (F12)

### Parte 2: Ingreso de Código de Producto

1. **En la sección "Agregar Producto"**:
   - [ ] Ingresa un código válido: `99999` (5 dígitos)
   - [ ] Verifica que el mensaje diga "Código disponible" en verde
   - [ ] Cambia a código existente (ej: `10001`)
   - [ ] Verifica que diga "Código duplicado" en rojo

2. **Cargar Galería por Código**:
   - [ ] Ingresa código `99999`
   - [ ] Desplázate down para ver la sección "Galería del producto"
   - [ ] Verifica que dice "Escribe un código de 5 o 6 números para cargar su galería"
   - [ ] Debería cargar la galería (vacía si es nuevo producto)

### Parte 3: Compresión de Imágenes

**Preparar imágenes de prueba**:
```bash
# Crear 3 imágenes JPEG grandes (~2MB c/u)
# O usar imágenes existentes en /tmp/
```

1. **Seleccionar Archivos**:
   - [ ] Haz clic en "Subir imagen o varias imágenes"
   - [ ] Selecciona 3 imágenes JPEG grandes
   - [ ] Verifica que se inicie la carga automáticamente

2. **Ver Barra de Progreso**:
   - [ ] Debería ver: `⏳ Procesando imágenes... [████░░░░░░] 40%`
   - [ ] La barra sube gradualmente a 100%
   - [ ] Debería tomar ~1-3 segundos (compresión)
   - [ ] Luego ~2-5 segundos más (upload)

3. **Confirmación**:
   - [ ] Debe aparecer: `✅ Imágenes cargadas correctamente (3 imágenes)`
   - [ ] Las 3 imágenes aparecen en la galería
   - [ ] La primera tiene etiqueta "★ PORTADA"
   - [ ] Tamaño total de archivo fue ~60-70% menor (check en Network tab)

### Parte 4: Drag and Drop para Reordenamiento

1. **Ver estado de los items**:
   - [ ] Cada imagen tiene un borde (azul para portada, gris para otros)
   - [ ] Cursor cambia a "grab" al pasar sobre imagen
   - [ ] Label superior dice "Drag & Drop para reordenar"

2. **Hacer Drag & Drop**:
   - [ ] Arrastra imagen #2 a posición #1
   - [ ] Durante arrastre: opacidad 50%, borde punteado
   - [ ] Al soltar: imagen se mueve instantáneamente
   - [ ] No hay recarga de página
   - [ ] Se ve mensaje de confirmación

3. **Verificar Orden**:
   - [ ] Actualiza el dropdown de posiciones
   - [ ] La primera imagen es ahora la que moviste
   - [ ] Tiene label "★ PORTADA"

### Parte 5: Cambiar Portada

1. **Desde Dropdown**:
   - [ ] En cualquier imagen (no portada), hay dropdown de posición
   - [ ] Selecciona posición "1"
   - [ ] Imagen se mueve a primer lugar automáticamente
   - [ ] Recibe label "★ PORTADA"

2. **Desde Botón**:
   - [ ] En imagen no-portada, hay botón "★ Portada"
   - [ ] Haz clic
   - [ ] Se mueve a primer lugar instantáneamente

### Parte 6: Eliminar Imagen

1. **Hacer clic en Quitar**:
   - [ ] Cada imagen tiene botón "✕ Quitar"
   - [ ] Haz clic en una imagen no-portada
   - [ ] Aparece confirmación: "¿Eliminar esta imagen de la galería?"
   - [ ] Haz clic en Aceptar

2. **Verificar Eliminación**:
   - [ ] Imagen desaparece instantáneamente
   - [ ] Count de imágenes baja (ej: 3 -> 2)
   - [ ] Ver mensaje: "✅ Imagen eliminada"
   - [ ] Galería se refresca automáticamente

### Parte 7: Cache HTTP (Avanzado)

**En DevTools (F12) → Network Tab**:

1. **Primer request**:
   - [ ] Abre Network tab
   - [ ] Ingresa código `99999`
   - [ ] Busca request `product-gallery-list?sku=99999`
   - [ ] Status debería ser `200`
   - [ ] Response headers incluyen: `ETag: "gallery-..."`
   - [ ] Response size: ~500 bytes

2. **Segundo request (sin cambios)**:
   - [ ] Recarga la página o ingresa mismo código
   - [ ] Busca nuevo request a `product-gallery-list`
   - [ ] Status debería ser `304 Not Modified`
   - [ ] Response size: ~100 bytes (solo headers)
   - [ ] Tiempo: <10ms (mucho más rápido)

3. **Después de agregar imagen**:
   - [ ] Carga nueva imagen
   - [ ] Next request debería ser `200` (cambió)
   - [ ] ETag es diferente
   - [ ] Response size es mayor (datos nuevos)

### Parte 8: Marketplace CE

Repetir todas las pruebas anteriores en la pestaña "Marketplace CE":

- [ ] Ingresa código `88888` (CE)
- [ ] Verifica que galería carga
- [ ] Prueba upload de imágenes
- [ ] Prueba drag-drop
- [ ] Prueba cambiar portada
- [ ] Prueba eliminar imagen
- [ ] Verifica que ETag funciona

### Parte 9: Comportamiento en Móvil

Abrir DevTools → Responsive Mode (iPhone 12):

- [ ] Layout se adapta correctamente
- [ ] Drag-drop funciona en touch
- [ ] Dropdown de posiciones es usable
- [ ] Barra de progreso visible
- [ ] Texto legible
- [ ] Botones clickeables

### Parte 10: Errores e Edge Cases

1. **Archivo sin seleccionar**:
   - [ ] Haz clic en "Cargar imágenes" sin seleccionar archivos
   - [ ] Debería permitir continuar (no es error)
   - [ ] Mensaje: "Primero captura un código..."

2. **Código inválido**:
   - [ ] Ingresa `123` (menos de 5 dígitos)
   - [ ] Selecciona imagen
   - [ ] Debería dar error: "código de producto válido"

3. **Archivo muy grande**:
   - [ ] Selecciona archivo >50MB
   - [ ] Debería dar error de tamaño (backend)
   - [ ] O comprime automáticamente si Canvas lo permite

4. **Compresión falla**:
   - [ ] (Simulado) Si Canvas API no está disponible
   - [ ] Debería usar archivo original
   - [ ] Debería funcionar pero ser más lento

---

## 🔍 Verificación Técnica

### Console (F12)

```javascript
// Verificar que funciones existen:
typeof compressImage === 'function' // true
typeof setupGalleryDragDrop === 'function' // true
typeof uploadProductImages === 'function' // true

// Verificar que caches existen:
typeof stockGalleryCache !== 'undefined' // true
typeof marketplaceGalleryCache !== 'undefined' // true
```

### Network Tab (F12)

```
Request 1: GET /api/admin_supply.php?action=product-gallery-list&sku=99999
  Status: 200
  Headers: ETag: "gallery-abc123..."
  
Request 2 (sin cambios): GET /api/admin_supply.php?action=product-gallery-list&sku=99999
  Status: 304 (Not Modified)
  Headers: ETag: "gallery-abc123..."
  Size: <1KB
```

### Performance (F12)

**Upload 5 imágenes de 2MB**:
- Compresión: ~500-800ms
- Upload: ~2-4s
- Total: ~3-5s (antes era 8-10s)

**Cargar galería**:
- Con cache 200: ~400-600ms
- Con cache 304: ~20-50ms (15x faster)

---

## 📝 Plantilla de Reporte

Cuando reportes un bug, incluye:

1. **Pasos para reproducir**:
   - Paso 1...
   - Paso 2...

2. **Resultado esperado**:
   - Debería...

3. **Resultado actual**:
   - Ocurre...

4. **Información**:
   - Navegador: (Chrome/Firefox/Safari)
   - Sistema: (Windows/Mac/Linux)
   - Resolución: 1920x1080
   - DevTools Console: (¿hay errores?)

5. **Screenshots/Videos**:
   - (Adjunta si es posible)

---

## ✅ Requisitos para Pasar

Para que se considere "LISTO PARA PRODUCCIÓN":

- [ ] Todos los pasos de Parte 1-8 pasan
- [ ] No hay errores en console (F12)
- [ ] Performance de uploads mejora 2-3x
- [ ] Cache HTTP funciona (304 responses)
- [ ] Drag-drop es responsive en móvil
- [ ] Mensajes de error son claros
- [ ] No hay breaking changes (antiguo código sigue funcionando)

---

## 🚨 Rollback (en caso de emergencia)

Si necesitas revertir los cambios:

```bash
git diff public/admin_supply.php  # Ver cambios
git diff public/api/admin_supply.php
git checkout -- public/admin_supply.php  # Revert
git checkout -- public/api/admin_supply.php
```

---

## 📞 Soporte

Si encuentras problemas:

1. Abre DevTools (F12)
2. Ve a Console tab
3. Copia los errores
4. Reporta con los pasos para reproducir
5. Incluye screenshot o video si es posible

---

**Última Actualización**: Mayo 2026
**Versión**: 2.0 (Optimizaciones Aplicadas)
