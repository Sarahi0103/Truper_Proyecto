# Truper Platform - Guía de Instalación y Uso

## 📋 Descripción General

**Truper Platform** es una plataforma web integral de gestión empresarial diseñada para la empresa Truper. Incluye funcionalidades para:

- ✅ Gestión de clientes y sistema de lealtad
- ✅ Creación y seguimiento de pedidos
- ✅ Control de pagos sin inventario tradicional
- ✅ Asignación y seguimiento de tareas para empleados
- ✅ Análisis estadístico de compras basado en temporada y factores externos
- ✅ Módulo mayorista con precios especiales
- ✅ Sistema de predicción de demanda con IA
- ✅ Lector de códigos de barras
- ✅ Recordatorios de cumpleaños con bonificaciones
- ✅ Seguridad empresarial con roles de usuario

## 🚀 Requisitos Previos

- **PHP 7.4+**
- **PostgreSQL 12+**
- **Apache/Nginx con mod_rewrite**
- **Composer (opcional para futuras extensiones)**

## 📦 Instalación

### 1. Preparar Base de Datos

```bash
# Conectar a PostgreSQL
psql -U postgres

# Crear usuario
CREATE ROLE truper_admin WITH LOGIN PASSWORD 'TruperSecure2024!';
ALTER ROLE truper_admin CREATEDB;

# Crear base de datos
CREATE DATABASE truper_platform OWNER truper_admin;

# Conectar a la base de datos
\c truper_platform truper_admin

# Importar schema
\i database.sql
```

### 2. Configurar PHP

```bash
# Copiar archivos de configuración
cp config/database.example.php config/database.php

# Editar credenciales si es necesario
# Configurar en config/database.php:
# - DB_HOST
# - DB_USER
# - DB_PASS
# - DB_NAME
```

### 3. Configurar Apache/Nginx

**Para Apache (.htaccess ya incluido):**
```
<Directory /var/www/truper_platform/public>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</Directory>
```

**Para Nginx:**
```nginx
location /truper_platform {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 4. Permisos de Carpetas

```bash
chmod 755 public/
chmod 755 public/css/
chmod 755 public/js/
chmod 755 public/images/
chmod 755 config/
chmod 600 config/database.php  # Restringir acceso
```

### 5. Iniciar Sesión

**Usuario Administrador por Defecto:**
- Email: `admin@truper.com`
- Contraseña: `Admin123!`

## 📁 Estructura del Proyecto

```
truper_platform/
├── config/
│   ├── config.php          # Configuración general y funciones
│   └── database.php        # Conexión a PostgreSQL
├── src/
│   ├── controllers/        # Lógica de negocio
│   │   ├── AuthController.php
│   │   ├── OrderController.php
│   │   ├── TaskController.php
│   │   └── AnalyticsController.php
│   ├── models/            # Modelos de datos (futuro)
│   ├── views/             # Templates (futuro)
│   └── utils/             # Utilidades
├── public/
│   ├── api/               # Endpoints de API
│   │   ├── auth.php
│   │   ├── orders.php
│   │   ├── tasks.php
│   │   └── analytics.php
│   ├── css/
│   │   ├── styles.css     # Estilos principales
│   │   └── dashboard.css  # Estilos específicos
│   ├── js/
│   │   ├── main.js        # JavaScript principal
│   │   ├── orders.js      # Lógica de pedidos
│   │   ├── tasks.js       # Lógica de tareas
│   │   └── analytics.js   # Gráficas y análisis
│   ├── images/            # Recursos gráficos
│   ├── login.php          # Página de login
│   ├── register.php       # Página de registro
│   ├── dashboard.php      # Dashboard principal
│   ├── orders.php         # Gestión de pedidos
│   ├── tasks.php          # Gestión de tareas
│   ├── analytics.php      # Estadísticas
│   └── profile.php        # Perfil de usuario
├── database.sql           # Script de creación
└── README.md              # Este archivo
```

## 🎨 Identidad Visual

**Colores Corporativos:**
- Naranja: `#FF7F00` (Principal)
- Negro: `#1A1A1A` (Fondos oscuros)
- Blanco: `#FFFFFF` (Fondo)
- Grises: Para textos secundarios

**Diseño:**
- Minimalista y profesional
- Responsive (móvil, tablet, desktop)
- Interfaz intuitiva

## 🔐 Seguridad Implementada

### Funcionalidades de Seguridad:
- ✅ Contraseñas con hash bcrypt (cost: 12)
- ✅ Sesiones seguras (HttpOnly, Secure, SameSite)
- ✅ Validación de entrada (sanitización)
- ✅ Headers de seguridad HTTP
- ✅ CSRF protection
- ✅ SQL Injection prevention (Prepared Statements)
- ✅ XSS protection
- ✅ Registro de auditoría de acciones
- ✅ Control de roles (Admin, Cliente, Empleado)
- ✅ Autenticación requerida para todas las acciones

## 📊 Funcionalidades Principales

### 1. Sistema de Pedidos
- Crear pedidos con carrito dinámico
- Calcular precios con descuentos por cantidad
- Aplicar descuentos por lealtad
- Registrar pagos parciales o totales
- Seguimiento de estado del pedido

### 2. Gestión de Tareas
- Asignar tareas a empleados
- Prioridades (Bajo, Medio, Alto, Urgente)
- Fechas de vencimiento
- Registro de horas trabajadas
- Filtrado por estado

### 3. Programa de Lealtad
- Acumulación de puntos por compra
- Descuentos automáticos según puntos
- Recordatorios de cumpleaños
- Bonificación especial en fechas
- Historial de puntos

### 4. Análisis y Predicciones
- Estadísticas de compras por mes/año
- Análisis por temporada (clima, eventos)
- Predicción de demanda con IA
- Exportación de reportes
- Dashboard con métricas clave

### 5. Módulo Mayorista
- Precios especiales para mayoristas
- Cantidad mínima de compra
- Solicitud de aprobación
- Descuentos escalonados

### 6. Control de Pagos
- Registro de múltiples pagos por orden
- Cálculo de saldo pendiente
- Métodos de pago flexibles
- Historial de pagos

### 7. Sistema de Códigos de Barras
- Registro de códigos de barras
- Alineación con productos existentes
- Verificación de integridad
- Historial de escaneos

## 🤖 Sistema de Predicción (IA Adaptable)

El sistema aprende de los patrones de compra para hacer predicciones:

**Factores Considerados:**
1. Historial de compras (2+ años)
2. Temporada actual (Invierno, Primavera, Verano, Otoño)
3. Factores climáticos
4. Fechas especiales (festividades, eventos)
5. Tendencias de crecimiento/decrecimiento
6. Comparación inter-anual

**Beneficios:**
- Evita compras innecesarias
- Optimiza presupuesto mensual
- Reduce pérdidas por sobrestoque
- Mejora planificación de compras
- Aumenta eficiencia operacional

## 📱 Funcionalidades por Rol

### Administrador
- Acceso a todos los módulos
- Crear y asignar tareas
- Aprobar solicitudes mayoristas
- Ver estadísticas completas
- Generar reportes
- Gestionar usuarios

### Cliente
- Crear pedidos
- Ver historial de pedidos
- Acumular puntos de lealtad
- Ver descuentos disponibles
- Solicitar mayoreo
- Ver profile personal

### Empleado
- Ver tareas asignadas
- Registrar horas de trabajo
- Actualizar estado de tareas
- Ver calendario de tareas

## 🔄 Flujos Principales

### Flujo de Pedido Completo:
1. Cliente selecciona productos
2. Sistema calcula precios automáticamente
3. Se aplican descuentos por cantidad/lealtad
4. Cliente confirma pedido
5. Admin recibe notificación
6. Pedido pasa a "Confirmado"
7. Se prepara para envío
8. Cliente recibe notificación de entrega
9. Se registra compra para estadísticas

### Flujo de Predicción:
1. Sistema recopila datos de compras
2. Analiza patrones histó

ricos
3. Considera factores externos
4. Genera predicciones para próximo mes
5. Calcula confianza de predicción
6. Mostrala en dashboard
7. Admin puede usar para planificación

## 🐛 Troubleshooting

**Problema: No puedo conectar a la BD**
- Verificar credenciales en `config/database.php`
- Confirmar que PostgreSQL está corriendo
- Verificar permisos del usuario

**Problema: Las sesiones se pierden**
- Verificar que `php.ini` tiene `session.save_path` configurado
- Revisar permisos de carpeta de sesiones
- Comprobar timeout de sesión

**Problema: Los archivos CSS/JS no cargan**
- Verificar rutas en los includes HTML
- Confirmar permisos de lectura en `/public/`
- Limpiar cache del navegador

## 📞 Soporte y Contacto

**Email:** soporte@truper.com  
**Sitio Web:** https://www.truper.com  
**Teléfono:** +56 2 1234 5678

## 📄 Licencia

© 2024 Truper. Todos los derechos reservados.

---

**Versión:** 1.0.0  
**Última Actualización:** Marzo 2024
