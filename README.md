# Truper Platform - Plataforma Web Empresarial

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-sqlite&logo=php)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-13%2B-336791?style=flat-sqlite&logo=postgresql)](https://www.postgresql.org/)
[![License](https://img.shields.io/badge/Estado-Producci%C3%B3n-success)]()

## 📋 Descripción General

**Truper Platform** es una solución web integral de gestión comercial desarrollada a la medida para la empresa **Truper**. Combina un catálogo interactivo de alta velocidad para clientes con un potente panel administrativo de operaciones, ventas y control financiero.

### ✨ Funcionalidades Principales:
- 🛍️ **Catálogo Interactivo con Portada Personalizada**: Visualización de herramientas con búsqueda inteligente por SKU/Nombre, variantes, imágenes de alta definición e iluminación adaptativa.
- 💬 **Cotizaciones en PDF y WhatsApp**: Generación instantánea de presupuestos en PDF descargables o listos para enviar por chat.
- 📊 **Dashboard de Métricas en Tiempo Real**: Indicadores de Órdenes, Ingresos, Gastos operativos y cálculo automático de **Ganancia Neta**.
- 📦 **Módulo de Abastecimiento e Inventario (`admin_supply.php`)**: Alta de productos, control de existencias, gestión de galerías y administración de promociones.
- 🧾 **Punto de Venta y Caja (`cashier.php`)**: Registro de apertura de turno, cobro de ventas en mostrador (Efectivo/Tarjeta/Transferencia) y arqueos de cierre de turno.
- 📋 **Gestión de Tareas y Coordinación (`tasks.php`)**: Asignación y seguimiento de actividades del personal operativo.
- 💸 **Control de Gastos (`gastos.php`)**: Registro categorizado de egresos para un balance financiero exacto.
- 🔐 **Seguridad Avanzada**: Autenticación por Nombre de Usuario o Email, cifrado Bcrypt, protección contra CSRF y Rate Limiting por IP.

---

## 📚 Documentación Oficial de Entrega

El proyecto incluye manuales detallados para cada perfil dentro de la organización:

- 📖 **[MANUAL_USUARIO.md](file:///c:/Users/ksgom/proyecto_Truper/MANUAL_USUARIO.md)**: Manual operativo ilustrado para cajeros, administradores y personal, incluyendo Procedimientos Operativos Estándar (SOPs) diarios y preguntas frecuentes.
- 🛠️ **[MANUAL_TECNICO.md](file:///c:/Users/ksgom/proyecto_Truper/MANUAL_TECNICO.md)**: Manual de arquitectura, seguridad, mantenimiento de servidores y modelo de datos para el equipo de TI.
- 🚀 **[INSTALLATION_GUIDE.md](file:///c:/Users/ksgom/proyecto_Truper/INSTALLATION_GUIDE.md)**: Instrucciones paso a paso para despliegue en Docker, Apache o Nginx.

---

## 🚀 Requisitos de Infraestructura

- **PHP 8.1+** (extensiones: `pdo_pgsql`, `pg_trgm`, `json`, `session`, `mbstring`, `gd` / `brotli`).
- **PostgreSQL 13+**.
- **Servidor Web**: Apache (con `mod_rewrite`) o Nginx.

---

## 📦 Despliegue Rápido (Docker)

```bash
docker compose up -d --build
```

- **Acceso Web Local**: `http://localhost:8088` (o `http://localhost:8000` en ejecución local).
- **Base de Datos**: `localhost:5433` (Base: `truper_platform`).

---

## 🗄️ Inicialización de Base de Datos y Limpieza

- **Esquema de Producción**: [database.sql](file:///c:/Users/ksgom/proyecto_Truper/database.sql)
- **Script de Desinfección Total (Base de Datos en 0)**: [RESET_EMPTY_DATABASE.sql](file:///c:/Users/ksgom/proyecto_Truper/db/RESET_EMPTY_DATABASE.sql)

---

&copy; 2026 Truper Platform. Todos los derechos reservados.
