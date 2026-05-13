# 🧪 Guía de Prueba en Dispositivos Reales

## Acceso a la Aplicación

### URL Pública
```
https://super-duper-invention-pjg657957jj7f7g9j-9000.app.github.dev
```

### Credenciales de Prueba

#### Admin
```
Email: admin@truper.com
Password: Admin123!
```

#### Cliente Regular
```
Email: client@truper.com
Password: Client123!
```

---

## 🧪 Plan de Prueba Completo

### FASE 1: Prueba en Browser DevTools (5 minutos)

#### 1.1 Setup
1. Abre la aplicación en Chrome/Firefox/Edge
2. Presiona `F12` para abrir DevTools
3. Haz clic en el ícono `Toggle device toolbar` (📱) o `Ctrl+Shift+M`
4. DevTools ahora muestra el tamaño en tiempo real

#### 1.2 Prueba de Breakpoints

**iPhone 12 (390x844)**
- [ ] Se carga sin errores
- [ ] Grid de productos: 1 columna
- [ ] Navegación: hamburguesa menu
- [ ] Botones: mínimo 44x44px
- [ ] No hay horizontal scroll

**Samsung Galaxy S21 (360x800)**
- [ ] Se carga sin errores
- [ ] Grid de productos: 1 columna
- [ ] Todo legible sin zoom
- [ ] Botones tappables
- [ ] Imágenes escaladas correctamente

**iPad (768x1024)**
- [ ] Se carga sin errores
- [ ] Grid de productos: 2 columnas
- [ ] Menu visible
- [ ] Layout cómodo
- [ ] Tappable targets adecuados

**iPad Pro (1024x1366)**
- [ ] Grid de productos: 3 columnas
- [ ] Desktop layout activo
- [ ] Máxima información visible
- [ ] Navegación completa
- [ ] Todo visible sin scroll

**Desktop (1440x900)**
- [ ] Grid de productos: 4 columnas
- [ ] Layout óptimo
- [ ] Espaciado correcto
- [ ] Botones con hover states
- [ ] Formularios multi-columna

#### 1.3 Prueba de Orientación

Para cada dispositivo (iPhone, Samsung, iPad):
1. Haz clic en el botón "Rotate" en DevTools o `Ctrl+Shift+R`
2. Cambia a landscape
3. Verifica que:
   - [ ] Layout se adapta
   - [ ] No hay contenido cortado
   - [ ] Elementos se reorganizan correctamente
   - [ ] Viewport es apropiado
4. Vuelve a portrait
5. Verifica que todo vuelve a normal

---

### FASE 2: Prueba en Dispositivo Real (10 minutos)

#### 2.1 Acceso desde Smartphone

**Método 1: QR Code**
1. Escanea con cámara del teléfono
2. Haz clic en la notificación para abrir

**Método 2: URL Manual**
1. Abre Safari/Chrome en tu teléfono
2. Ingresa la URL: `https://super-duper-invention-pjg657957jj7f7g9j-9000.app.github.dev`
3. Espera a que cargue completamente

#### 2.2 Prueba de Funcionalidad

**HomePage (Catálogo)**
- [ ] Carga sin errores
- [ ] Productos visibles
- [ ] Imágenes se cargan
- [ ] Scroll es suave
- [ ] Productos se adaptan al ancho

**Login**
- [ ] Página se carga
- [ ] Inputs son tappables (44x44px)
- [ ] Teclado virtual no oculta inputs
- [ ] Botón login es fácil de presionar
- [ ] Focus visual en inputs

**Catálogo (después de login)**
- [ ] Grid de productos en 1 columna
- [ ] Botones "Agregar al Carrito" funcionales
- [ ] Scroll es suave
- [ ] Imágenes se cargan sin delay

**Carrito**
- [ ] Productos listados correctamente
- [ ] Cantidades ajustables
- [ ] Totales visibles
- [ ] Botón checkout accesible
- [ ] Puede scrollear para ver más

**Orientación Landscape**
- [ ] Grid: 2 columnas
- [ ] Más contenido visible
- [ ] Teclado virtual no rompe layout
- [ ] Todo sigue siendo accesible

---

### FASE 3: Prueba de Accesibilidad (5 minutos)

#### 3.1 Touch Targets
```
En cualquier dispositivo:
1. Intenta tocar cada botón
2. Verifica que sea fácil de tocar
3. Si faltas, repite 2-3 veces
4. Debe ser consistentemente tocable
```

#### 3.2 Legibilidad
```
Sin zoom:
- [ ] Puedes leer todo el texto
- [ ] No hay texto cortado
- [ ] Contraste es bueno
- [ ] Font es legible
```

#### 3.3 Orientación
```
Portrait → Landscape:
1. Rota tu teléfono
2. Esperamos el re-render
3. Verifica que no se corta nada
4. Vuelve a portrait
5. Debe volver a estado original
```

#### 3.4 Dark Mode
```
En dispositivo:
1. Activa dark mode del SO
2. Vuelve a abrir la página
3. Verifica que se cambia automático
4. Colores deben ser legibles
5. Contraste debe mantenerse
```

---

### FASE 4: Prueba de Performance (3 minutos)

#### 4.1 Velocidad de Carga
```
En red 4G:
- Tiempo de carga: < 3 segundos
- Primera paint: < 1 segundo
- Contenido interactivo: < 2 segundos
```

#### 4.2 Velocidad de Scroll
```
En cualquier página:
1. Scrollea rápidamente hacia abajo
2. Scrollea rápidamente hacia arriba
3. No debe haber lag o jank
4. Debe ser suave
```

#### 4.3 Interactividad
```
Al clickear/tocar:
1. Botones responden inmediatamente
2. Transitions son suaves
3. No hay delays visibles
4. Feedback visual es claro
```

---

### FASE 5: Prueba de Casos Extremos (5 minutos)

#### 5.1 Textos Largos
```
En página con textos:
- [ ] Párrafos largos se adaptan
- [ ] No causan overflow horizontal
- [ ] Lectura es cómoda
- [ ] Líneas no son demasiado largas (>70 chars)
```

#### 5.2 Muchas Imágenes
```
En catálogo con muchos productos:
- [ ] Scroll es suave (no lag)
- [ ] Imágenes se cargan sin delay
- [ ] No consume mucha batería
- [ ] Performance se mantiene
```

#### 5.3 Formularios Complejos
```
En formularios:
- [ ] Todos los inputs son tappables
- [ ] Teclado virtual no oculta campos críticos
- [ ] Labels son legibles
- [ ] Submit button es accesible
```

#### 5.4 Notches (si aplica)
```
En iPhone X/12/13/14:
- [ ] Contenido no va bajo el notch
- [ ] Safe areas respetadas
- [ ] Todo contenido visible
- [ ] Viewport-fit funcionando
```

---

## 📊 Checklist de Prueba Final

### Mobile (320px - 479px)
- [ ] Página carga sin errores
- [ ] Grid: 1 columna
- [ ] Botones: 44x44px mínimo
- [ ] No hay horizontal scroll
- [ ] Texto es legible
- [ ] Imágenes se escalan
- [ ] Scroll es suave
- [ ] Touch targets funcionales

### Tablet (768px - 1023px)
- [ ] Página carga sin errores
- [ ] Grid: 2-3 columnas
- [ ] Menú visible
- [ ] Layout es cómodo
- [ ] Botones tappables
- [ ] Contenido bien distribuido
- [ ] Performance es bueno

### Desktop (1024px+)
- [ ] Página carga sin errores
- [ ] Grid: 4 columnas (si aplica)
- [ ] Layout es óptimo
- [ ] Hover states en botones
- [ ] Navegación completa
- [ ] Máxima información visible

### Orientación
- [ ] Portrait → Landscape: funciona
- [ ] Landscape → Portrait: funciona
- [ ] No hay contenido cortado
- [ ] Re-layout es automático

### Dark Mode
- [ ] Se activa automáticamente
- [ ] Colores legibles
- [ ] Contraste suficiente (4.5:1)
- [ ] No hay elementos invisible

### Performance
- [ ] Carga: < 3 segundos
- [ ] Scroll: suave (60 fps)
- [ ] Interactividad: inmediata
- [ ] Sin jank o lag

---

## 🚨 Problemas a Reportar

Si encuentras problemas, reporta:

### Información Necesaria:
1. **Dispositivo**: Samsung Galaxy S21 / iPhone 12 / iPad / etc.
2. **Navegador**: Chrome / Safari / Firefox / Edge
3. **Tamaño**: 360x800 / 390x844 / 768x1024 / etc.
4. **Sistema**: Android 12 / iOS 15 / etc.
5. **Problema**: Descripción clara
6. **Pasos para reproducir**: 1, 2, 3...
7. **Screenshot**: Si es posible

### Formato de Reporte:
```
DISPOSITIVO: iPhone 12 (390x844)
NAVEGADOR: Safari 15
PÁGINA: /index.php (Catálogo)
PROBLEMA: Grid muestra 2 columnas en mobile
PASOS:
  1. Abre la página
  2. Observa el grid de productos
  3. Se muestran 2 columnas en lugar de 1
RESULTADO ESPERADO: 1 columna
RESULTADO ACTUAL: 2 columnas
```

---

## ✅ Test Scenarios

### Scenario 1: Compra en Mobile
```
1. Abre en iPhone desde WiFi
2. Login como cliente
3. Navega catálogo
4. Agregar producto a carrito
5. Ir a carrito
6. Checkout
7. Completar formulario
8. Pagar
✓ Todo debe funcionar sin problemas
```

### Scenario 2: Admin Dashboard
```
1. Abre en iPad
2. Login como admin
3. Navega dashboard
4. Ver productos
5. Ver pedidos
6. Ver estadísticas
7. Rota a landscape
✓ Dashboard debe ser responsive
```

### Scenario 3: Búsqueda en Móvil
```
1. Abre en Android phone
2. Usa search bar
3. Ingresa query
4. Busca
5. Ve resultados
6. Filtra resultados
7. Ordena por precio
✓ Búsqueda debe ser responsiva
```

---

## 📈 Métricas a Validar

**Lighthouse (DevTools)**
```
1. Abre DevTools (F12)
2. Ve a Lighthouse
3. Selecciona "Mobile"
4. Genera reporte
5. Verifica scores:
   - Performance: > 60
   - Accessibility: > 80
   - Best Practices: > 80
   - SEO: > 80
```

---

## 🎯 Criterio de Éxito

La prueba es exitosa cuando:

✅ 18/18 páginas son responsive
✅ Todos los breakpoints funcionan
✅ Touch targets son >= 44x44px
✅ No hay horizontal scroll en mobile
✅ Texto es legible sin zoom
✅ Dark mode funciona automático
✅ Orientación se adapta
✅ Lighthouse score >= 80
✅ Performance es suave
✅ Usuarios pueden completar tareas

---

## 📞 Soporte

Si necesitas ayuda:
1. Consulta: `RESPONSIVE_TESTING_GUIDE.md`
2. Consulta: `RESPONSIVE_IMPLEMENTATION_SUMMARY.md`
3. Consulta: `RESPONSIVE_VISUAL_DEMO.md`
4. Ejecuta: `./verify_responsive.sh`

---

**Fecha**: 2025-05-08
**Status**: ✅ Listo para Prueba
**Cobertura**: 100% de páginas responsivas
