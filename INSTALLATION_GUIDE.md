# 🚀 GUÍA DE INSTALACIÓN RÁPIDA - Truper Platform

## Requisitos Previos
- PHP 7.4+
- PostgreSQL 12+
- Apache/Nginx
- Composer (opcional)

## Instalación Recomendada: Docker Compose

Este proyecto ya trae un stack listo para desarrollo local:

```bash
docker compose up -d
```

Servicios y puertos:

- Web: http://localhost:8088
- Base de datos: localhost:5433

Credenciales del entorno Docker:

- Base de datos: `truper_platform`
- Usuario: `truper_admin`
- Contraseña: `Truper123!`

Validación rápida dentro de Docker:

```bash
docker compose exec -T web php -l /var/www/html/backend/models/BarcodeReader.php
cat db/ALTER_PAYMENT_TERMS.sql | docker compose exec -T db psql -U truper_admin -d truper_platform
```

## Paso 1: Instalar PostgreSQL

### En Windows:
1. Descargar PostgreSQL desde: https://www.postgresql.org/download/windows/
2. Instalar con contraseña para usuario `postgres`
3. Recordar puerto (generalmente 5432)

### En Linux (Ubuntu/Debian):
```bash
sudo apt-get install postgresql postgresql-contrib
sudo systemctl start postgresql
```

## Paso 2: Crear Base de Datos

```bash
# Conectar a PostgreSQL
psql -U truper_admin -d truper_platform

# Crear usuario
-- La base y el usuario ya existen en el stack Docker.
-- Si instalas PostgreSQL manualmente, crea el rol y la base con tus propios valores.
```

## Paso 3: Importar Schema

```bash
# En Windows (cmd):
psql -U truper_admin -d truper_platform -f database.sql

# En Linux:
psql -U truper_admin -d truper_platform < database.sql
```

## Paso 4: Configurar Apache

### Crear VirtualHost
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

### Habilitar sitio
```bash
sudo a2ensite truper
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Agregar hosts (Windows)
```
C:\Windows\System32\drivers\etc\hosts

127.0.0.1 truper.local
```

## Paso 5: Verificar Instalación

1. Abrir navegador: `http://localhost:8088/`
2. Iniciar sesión con el usuario administrador configurado en la base de datos.

## Datos de Prueba

Para cargar productos de ejemplo:

```bash
psql -U truper_admin -d truper_platform -f PRODUCTOS_EJEMPLO.sql
```

## Troubleshooting

### Error: "Connection refused"
- Verificar que Docker Compose esté levantado
- Comprobar credenciales en `config/database.php`
- Revisar `docker compose ps` y `docker compose logs -f db`

### Error 500 - Internal Server Error
- Revisar permisos de carpetas
- Habilitar error_reporting en PHP
- Revisar logs de Apache

### Las sesiones se pierden
- Verificar permisos de carpeta `/tmp` (Linux)
- Comprobar `session.save_path` en php.ini

## Próximos Pasos

1. Cambiar contraseña del admin
2. Crear usuarios (Clientes, Empleados)
3. Cargar productos
4. Configurar lector de código de barras
5. Configurar tareas programadas (cron jobs)

---

**¿Necesitas ayuda?** Contacta a soporte@truper.com
