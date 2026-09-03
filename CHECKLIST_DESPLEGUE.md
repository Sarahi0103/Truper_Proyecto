# 🚀 Checklist de Despliegue - Truper Platform

## 📋 Pre-Despliegue (Local)

### 1. Instalar Dependencias
```bash
cd c:\Users\ksgom\proyecto_Truper
composer install
```

**Verificación:**
- [ ] Directorio `vendor/` creado
- [ ] `stripe/stripe-php` instalado
- [ ] `facturapi/facturapi-php` instalado
- [ ] `mercadopago/dx-php` instalado

### 2. Ejecutar Migraciones de Base de Datos
```bash
psql -U truper_admin -d truper_platform -f database_migrations/add_tax_fields_to_products.sql
psql -U truper_admin -d truper_platform -f database_migrations/add_payment_complements_table.sql
```

**Verificación:**
- [ ] Tabla `products` tiene campos `is_tax_exempt`, `tax_rate`, `tax_type`
- [ ] Tabla `orders` tiene campos `subtotal_amount`, `tax_amount`, `tax_rate_applied`
- [ ] Tabla `order_items` tiene campos `tax_rate`, `tax_amount`, `line_total_with_tax`
- [ ] Tabla `global_invoices` creada
- [ ] Tabla `payment_complements` creada

### 3. Configurar Archivo .env
Crear archivo `.env` en la raíz del proyecto:
```env
# Base de Datos
DB_HOST=localhost
DB_PORT=5432
DB_NAME=truper_platform
DB_USER=truper_admin
DB_PASS=tu_password_seguro

# Stripe
STRIPE_PUBLIC_KEY=pk_live_...
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Mercado Pago
MERCADOPAGO_PUBLIC_KEY=APP_USR-...
MERCADOPAGO_ACCESS_TOKEN=APP_USR-...

# Facturapi
FACTURAPI_API_KEY=sk_live_...

# Entorno
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com
```

**Verificación:**
- [ ] Archivo `.env` creado
- [ ] Todas las variables configuradas
- [ ] Archivo `.env` agregado a `.gitignore`

### 4. Probar Funcionalidades Localmente

**Pagos:**
- [ ] Probar checkout con tarjeta de prueba Stripe (4242 4242 4242 4242)
- [ ] Verificar que webhook reciba confirmación
- [ ] Probar pago contra entrega
- [ ] Probar pago SPEI

**Facturación:**
- [ ] Probar emisión de factura individual
- [ ] Verificar que se genere XML y PDF
- [ ] Probar validación de RFC
- [ ] Probar cancelación de factura
- [ ] Probar factura global manual

**Cálculo de IVA:**
- [ ] Verificar cálculo correcto de IVA (16%)
- [ ] Probar producto exento de IVA
- [ ] Verificar desglose en orden

---

## ☁️ Despliegue en Servidor

### 5. Preparar Servidor

**Requisitos mínimos:**
- [ ] Ubuntu 20.04+ / CentOS 8+ / Debian 11+
- [ ] PHP 8.1+ con extensiones: pdo, pdo_pgsql, curl, json, mbstring
- [ ] PostgreSQL 13+
- [ ] Apache 2.4+ o Nginx
- [ ] Composer instalado
- [ ] Git instalado
- [ ] Certificado SSL (Let's Encrypt o comercial)

**Instalación de dependencias:**
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install php8.1 php8.1-pdo php8.1-pgsql php8.1-curl php8.1-json php8.1-mbstring php8.1-xml
sudo apt install postgresql-13 postgresql-contrib
sudo apt install composer
sudo apt install git
sudo apt install certbot python3-certbot-apache  # Para SSL
```

### 6. Configurar Base de Datos en Servidor

```bash
# Crear usuario y base de datos
sudo -u postgres psql
CREATE USER truper_admin WITH PASSWORD 'password_seguro';
CREATE DATABASE truper_platform OWNER truper_admin;
GRANT ALL PRIVILEGES ON DATABASE truper_platform TO truper_admin;
\q

# Importar esquema
psql -U truper_admin -d truper_platform < database.sql

# Ejecutar migraciones
psql -U truper_admin -d truper_platform < database_migrations/add_tax_fields_to_products.sql
psql -U truper_admin -d truper_platform < database_migrations/add_payment_complements_table.sql
```

**Verificación:**
- [ ] Base de datos creada
- [ ] Usuario tiene permisos
- [ ] Tablas creadas correctamente
- [ ] Migraciones ejecutadas

### 7. Subir Código al Servidor

**Opción A: Git (Recomendado)**
```bash
# En servidor
cd /var/www
sudo git clone https://github.com/tu-repo/proyecto_Truper.git
cd proyecto_Truper
composer install --no-dev
```

**Opción B: FTP/SFTP**
- [ ] Subir todos los archivos excepto `vendor/`
- [ ] Ejecutar `composer install` en servidor

**Verificación:**
- [ ] Archivos subidos
- [ ] Dependencias instaladas
- [ ] Permisos correctos (755 para directorios, 644 para archivos)

### 8. Configurar Permisos

```bash
# Establecer permisos
sudo chown -R www-data:www-data /var/www/proyecto_Truper
sudo chmod -R 755 /var/www/proyecto_Truper
sudo chmod -R 644 /var/www/proyecto_Truper/*.php
sudo chmod -R 777 /var/www/proyecto_Truper/logs  # Si existe directorio de logs
```

**Verificación:**
- [ ] www-data es propietario
- [ ] Permisos correctos

### 9. Configurar Apache

Crear archivo `/etc/apache2/sites-available/truper.conf`:
```apache
<VirtualHost *:80>
    ServerName tu-dominio.com
    ServerAlias www.tu-dominio.com
    DocumentRoot /var/www/proyecto_Truper/public

    <Directory /var/www/proyecto_Truper/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/truper_error.log
    CustomLog ${APACHE_LOG_DIR}/truper_access.log combined
</VirtualHost>
```

**Activar sitio:**
```bash
sudo a2ensite truper.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**Verificación:**
- [ ] Sitio activado
- [ ] Mod rewrite habilitado
- [ ] Apache reiniciado sin errores

### 10. Configurar HTTPS con Let's Encrypt

```bash
sudo certbot --apache -d tu-dominio.com -d www.tu-dominio.com
```

**Verificación:**
- [ ] Certificado SSL instalado
- [ ] Redirección HTTPS automática
- [ ] Certificado renovable automáticamente

### 11. Configurar Variables de Entorno en Servidor

```bash
cd /var/www/proyecto_Truper
nano .env
```

Copiar configuración del `.env` local con credenciales de producción.

**Verificación:**
- [ ] `.env` creado en servidor
- [ ] Credenciales de producción configuradas
- [ ] APP_ENV=production

---

## 🔌 Configuración de Servicios Externos

### 12. Configurar Stripe

**En Stripe Dashboard:**
1. Crear cuenta en https://dashboard.stripe.com/
2. Obtener API keys (modo live)
3. Configurar webhook:
   - URL: `https://tu-dominio.com/api/stripe_webhook.php`
   - Eventos: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`
4. Copiar webhook secret

**En plataforma:**
- [ ] Ir a `admin_online_billing.php`
- [ ] Pestaña "Pasarelas de Cobro"
- [ ] Ingresar Stripe Publishable Key
- [ ] Ingresar Stripe Secret Key
- [ ] Ingresar Stripe Webhook Secret

**Verificación:**
- [ ] API keys configuradas
- [ ] Webhook configurado en Stripe
- [ ] Webhook secret guardado

### 13. Configurar Mercado Pago

**En Mercado Pago Dashboard:**
1. Crear aplicación en https://www.mercadopago.com.mx/developers
2. Obtener Access Token
3. Configurar webhook:
   - URL: `https://tu-dominio.com/api/mercadopago_webhook.php`
   - Eventos: `payment`

**En plataforma:**
- [ ] Ir a `admin_online_billing.php`
- [ ] Ingresar Mercado Pago Public Key
- [ ] Ingresar Mercado Pago Access Token

**Verificación:**
- [ ] API keys configuradas
- [ ] Webhook configurado en Mercado Pago

### 14. Configurar Facturapi

**En Facturapi:**
1. Crear cuenta en https://www.facturapi.mx/
2. Obtener API key (modo live)
3. Configurar CSD (Certificados de Sello Digital)
4. Registrar RFC de la empresa

**En plataforma:**
- [ ] Ir a `admin_online_billing.php`
- [ ] Pestaña "Configuración Fiscal CFDI 4.0"
- [ ] Ingresar Facturapi API Key
- [ ] Ingresar RFC de empresa
- [ ] Ingresar Razón Social exacta
- [ ] Seleccionar Régimen Fiscal
- [ ] Ingresar Código Postal fiscal

**Verificación:**
- [ ] API key configurada
- [ ] Datos fiscales completos
- [ ] CSD estatus: Activo

---

## ⏰ Configuración de Tareas Programadas

### 15. Configurar Cron Job de Factura Global

```bash
crontab -e
```

Agregar:
```cron
0 1 * * * /usr/bin/php /var/www/proyecto_Truper/cron/daily_global_invoice.php >> /var/log/truper_global_invoice.log 2>&1
```

**Verificación:**
- [ ] Cron job agregado
- [ ] Log file creado
- [ ] Ejecución programada a las 1:00 AM

### 16. Configurar Cron Job de Limpieza (Opcional)

```cron
0 3 * * 0 /usr/bin/php /var/www/proyecto_Truper/cron/cleanup_logs.php
```

---

## 🧪 Pruebas en Producción

### 17. Pruebas de Pagos

**Stripe:**
- [ ] Probar compra con tarjeta real (monto pequeño)
- [ ] Verificar confirmación en dashboard Stripe
- [ ] Verificar webhook recibido
- [ ] Verificar orden marcada como "paid"

**Mercado Pago:**
- [ ] Probar compra con Mercado Pago
- [ ] Verificar redirección correcta
- [ ] Verificar webhook recibido

**Pagos contra entrega:**
- [ ] Probar flujo completo
- [ ] Verificar orden en estado "pending_cod"

### 18. Pruebas de Facturación

**Factura Individual:**
- [ ] Probar compra con factura
- [ ] Verificar RFC validado
- [ ] Verificar factura emitida en Facturapi
- [ ] Descargar XML y PDF
- [ ] Validar XML con validador SAT

**Factura Global:**
- [ ] Ejecutar cron job manualmente
- [ ] Verificar factura global generada
- [ ] Verificar ventas marcadas como facturadas

**Cancelación:**
- [ ] Probar cancelación de factura
- [ ] Verificar reincorporación de inventario
- [ ] Verificar UUID cancelado en SAT

### 19. Pruebas de Seguridad

- [ ] Verificar HTTPS obligatorio
- [ ] Verificar CSRF tokens funcionando
- [ ] Verificar rate limiting activo
- [ ] Verificar autenticación segura
- [ ] Verificar que .env no es accesible vía web

### 20. Pruebas de Rendimiento

- [ ] Cargar página principal (< 2s)
- [ ] Procesar checkout (< 5s)
- [ ] Emitir factura (< 3s)
- [ ] Verificar uso de memoria aceptable
- [ ] Verificar que no hay errores en logs

---

## 📊 Monitoreo Post-Despliegue

### 21. Configurar Monitoreo

**Logs:**
- [ ] Revisar `/var/log/apache2/truper_error.log` diariamente
- [ ] Revisar `/var/log/truper_global_invoice.log` diariamente
- [ ] Configurar alertas para errores críticos

**Métricas:**
- [ ] Monitorear uptime del servidor
- [ ] Monitorear uso de CPU/RAM
- [ ] Monitorear espacio en disco
- [ ] Monitorear latencia de base de datos

**Backups:**
- [ ] Configurar backup automático de PostgreSQL
- [ ] Configurar backup de archivos
- [ ] Probar restauración de backup

### 22. Documentación Final

- [ ] Actualizar documentación con URLs de producción
- [ ] Documentar credenciales (en lugar seguro)
- [ ] Crear manual de operaciones
- [ ] Documentar procedimientos de emergencia

---

## ✅ Checklist Final

### Antes de Ir en Vivo
- [ ] Todas las pruebas pasadas
- [ ] SSL configurado y funcionando
- [ ] API keys de producción configuradas
- [ ] Base de datos migrada
- [ ] Cron jobs configurados
- [ ] Monitoreo activo
- [ ] Backups automatizados
- [ ] Equipo entrenado en uso del sistema

### Día del Lanzamiento
- [ ] Anuncio a clientes
- [ ] Monitoreo intensivo (primeras 24h)
- [ ] Soporte disponible
- [ ] Plan de contingencia listo

---

## 🆘 Procedimientos de Emergencia

### Si fallan los pagos:
1. Verificar API keys
2. Revisar logs de webhooks
3. Verificar estado de Stripe/Mercado Pago
4. Habilitar modo manual temporal

### Si falla facturación:
1. Verificar API key de Facturapi
2. Verificar CSD vigente
3. Generar facturas manualmente
4. Contactar soporte Facturapi

### Si el sitio cae:
1. Verificar logs de Apache
2. Reiniciar servicios
3. Verificar espacio en disco
4. Restaurar backup si necesario

---

**Última actualización:** 2026-08-18
**Versión:** 1.0
