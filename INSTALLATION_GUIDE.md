# 🚀 GUÍA DE INSTALACIÓN RÁPIDA - Truper Platform

## Requisitos Previos
- PHP 7.4+
- PostgreSQL 12+
- Apache/Nginx
- Composer (opcional)

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
psql -U postgres

# Crear usuario
CREATE ROLE truper_admin WITH LOGIN PASSWORD 'TruperSecure2024!';
ALTER ROLE truper_admin CREATEDB;

# Crear base de datos
CREATE DATABASE truper_platform OWNER truper_admin;

# Salir
\q
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

1. Abrir navegador: `http://truper.local/truper_platform/public/`
2. Iniciar sesión con:
   - Email: `admin@truper.com`
   - Contraseña: `Admin123!`

## Datos de Prueba

Para cargar productos de ejemplo:

```bash
psql -U truper_admin -d truper_platform -f PRODUCTOS_EJEMPLO.sql
```

## Troubleshooting

### Error: "Connection refused"
- Verificar que PostgreSQL está corriendo
- Comprobar credenciales en `config/database.php`

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
