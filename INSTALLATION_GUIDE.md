# 🚀 GUÍA DE INSTALACIÓN RÁPIDA - Truper Platform

Esta guía detalla los pasos para poner en marcha la aplicación web **Truper Platform** en un entorno de producción o desarrollo local.

---

## 📋 Requisitos Previos
- **PHP 8.1+** con extensiones: `pdo`, `pdo_pgsql`, `pg_trgm`, `json`, `session`, `mbstring`, `openssl`, `gd` o `brotli`.
- **PostgreSQL 13+** (con la extensión `pg_trgm` instalada).
- **Servidor Web**: Apache (con `mod_rewrite` activo) o Nginx.
- **Composer** (opcional).

---

## 🛠️ Opción 1: Instalación Rápida con Docker Compose

El proyecto incluye un contenedor listo para desarrollo local con persistencia de datos:

```bash
docker compose up -d --build
```

### Puertos y Servicios:
* **Servidor Web**: `http://localhost:8088` (o `http://localhost:8000` si se ejecuta mediante PHP CLI local)
* **Base de datos PostgreSQL**: `localhost:5433` (usuario: `truper_admin`, base: `truper_platform`)

---

## 💻 Opción 2: Instalación Manual en Servidor Web (Apache / Nginx)

### 1. Preparar la Base de Datos en PostgreSQL
Conéctate a PostgreSQL y crea la base de datos:

```sql
CREATE DATABASE truper_platform;
```

### 2. Importar el Esquema de Base de Datos
Ejecuta el script [database.sql](file:///c:/Users/ksgom/proyecto_Truper/database.sql) para crear las tablas e índices optimizados:

```bash
# En Windows (cmd/PowerShell):
psql -U truper_admin -d truper_platform -f database.sql

# En Linux:
psql -U truper_admin -d truper_platform < database.sql
```

> [!TIP]
> Si deseas realizar un vaciado completo de datos de prueba en una base de datos existente conservando las tablas limpias, ejecuta el script [RESET_EMPTY_DATABASE.sql](file:///c:/Users/ksgom/proyecto_Truper/db/RESET_EMPTY_DATABASE.sql).

### 3. Configurar Variables de Entorno (`.env`)
Copia la plantilla [.env.example](file:///c:/Users/ksgom/proyecto_Truper/.env.example) como `.env` en la raíz del proyecto y ajusta tus credenciales:

```ini
DB_HOST=localhost
DB_PORT=5432
DB_NAME=truper_platform
DB_USER=truper_admin
DB_PASSWORD=TuPasswordSeguro
APP_URL=http://localhost:8000
COMPANY_WHATSAPP_PHONE=3312482297
```

### 4. Configurar Servidor Web (DocumentRoot a `/public`)
Es indispensable apuntar la raíz pública de tu servidor web al directorio `/public`:

**Configuración para Apache (VirtualHost):**
```apache
<VirtualHost *:80>
    ServerName truper.local
    DocumentRoot "/var/www/truper_platform/public"
    
    <Directory "/var/www/truper_platform/public">
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/truper_error.log
    CustomLog ${APACHE_LOG_DIR}/truper_access.log combined
</VirtualHost>
```

### 5. Permisos de Directorios
Asegúrate de asignar permisos de lectura y escritura a las carpetas dinámicas:
```bash
chmod 775 cache/
chmod 775 logs/
chmod 775 public/images/products/gallery/
```

---




