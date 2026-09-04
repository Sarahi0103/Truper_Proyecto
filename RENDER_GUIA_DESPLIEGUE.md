# 🚀 Guía Completa de Despliegue en Render (Render Blueprint)

Esta plataforma está 100% preparada y optimizada para desplegarse en **Render** con un solo clic utilizando **Render Blueprints** (`render.yaml`).

---

## 📦 Componentes de la Arquitectura en Render

Al conectar tu repositorio a Render, el archivo `render.yaml` aprovisionará automáticamente:

1. **Base de Datos PostgreSQL Gestionada (`truper-db`)**:
   - Motor PostgreSQL de alto rendimiento.
   - Ubicada en la misma región (`oregon`) que el servicio web para latencia mínima (< 1 ms).
   - Inyección automática de credenciales (`DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DATABASE_URL`).
   - Inicialización automática: en el primer arranque, el sistema ejecuta automáticamente `database.sql` y las 19 migraciones incrementales de `database_migrations/`.

2. **Servicio Web Docker (`truper-web`)**:
   - Entorno **PHP 8.2 + Apache** con extensiones requeridas instaladas: `pdo_pgsql`, `pgsql`, `gd` (con soporte Freetype/JPEG para optimización de imágenes), `bcmath` (para pasarelas de pago), `zip` y `opcache` (para aceleración en producción).
   - `DocumentRoot` configurado en `/public`.
   - Rutas amigables vía `.htaccess` y `mod_rewrite`.
   - Health check en `/health.php` para monitoreo continuo de disponibilidad y cero caídas en redespliegues.

3. **Disco Persistente de Almacenamiento (`images-disk`)**:
   - Tamaño: 10 GB montado en `/var/www/html/images`.
   - Enlazado simbólicamente a `/public/images`.
   - **Beneficio**: Todas las fotos de productos, recibos, logotipos y documentos que suban los administradores y clientes **permanecen intactos** entre despliegues y actualizaciones de código.

---

## 🛠️ Pasos para Desplegar (Cuando decidas hacer Push)

> ⚠️ **Nota:** No se ha realizado ningún `commit` ni `push`. Tus cambios siguen 100% locales en tu equipo. Cuando tú decidas subirlos a GitHub, sigue estos sencillos pasos:

### Paso 1: Hacer Commit y Push a tu Repositorio
```bash
git add .
git commit -m "feat: configuracion completa de produccion para Render y optimizaciones"
git push origin main
```

### Paso 2: Crear el Blueprint en Render
1. Inicia sesión en tu cuenta de [Render](https://dashboard.render.com/).
2. Haz clic en el botón superior derecho **"New +"** y selecciona **"Blueprint"**.
3. Conecta tu cuenta de GitHub y selecciona el repositorio de este proyecto (`ksg-software/Ferreteria` o tu fork correspondiente).
4. Render leerá automáticamente el archivo `render.yaml` y te mostrará el resumen de recursos a crear:
   - Base de Datos: `truper-db`
   - Servicio Web: `truper-web`
   - Disco Persistente: `images-disk`
5. Haz clic en **"Apply"** (Aplicar).

### Paso 3: Despliegue Automático
- Render compilará el `Dockerfile` e iniciará la base de datos PostgreSQL.
- El script de inicio `docker/start.sh` detectará la base de datos, ejecutará el esquema completo y las migraciones, y levantará el servidor Apache.
- Cuando el health check en `/health.php` devuelva status 200, tu plataforma estará **completamente en vivo con HTTPS automático**.

---

## 🔑 Variables de Entorno Opcionales (En el Dashboard de Render)

Puedes configurar las siguientes variables desde **truper-web > Environment** si deseas habilitar pagos y facturación en vivo:

| Variable | Descripción | Ejemplo |
| :--- | :--- | :--- |
| `APP_URL` | URL pública asignada por Render o tu dominio | `https://truper-web.onrender.com` |
| `COMPANY_WHATSAPP_PHONE` | Teléfono para cotizaciones por WhatsApp | `3312482297` |
| `STRIPE_SECRET_KEY` | Clave secreta de Stripe | `sk_live_...` |
| `STRIPE_PUBLISHABLE_KEY` | Clave pública de Stripe | `pk_live_...` |
| `STRIPE_WEBHOOK_SECRET` | Firma del webhook de Stripe | `whsec_...` |
| `MERCADOPAGO_ACCESS_TOKEN` | Token de Mercado Pago | `APP_USR-...` |
| `FACTURAPI_API_KEY` | Clave API de Facturapi para CFDI 4.0 | `sk_live_...` |

---

## 💡 Alternativa: Plan 100% Gratuito (Render Free Tier)

El archivo `render.yaml` está preconfigurado con `plan: starter` porque Render requiere el plan Starter para soportar Discos Persistentes (`disk`).

Si deseas utilizar Render de manera **100% gratuita**, realiza estos 2 ajustes en `render.yaml` antes de desplegar:
1. En `databases`: cambia `plan: starter` por `plan: free`. *(Ten en cuenta que las bases de datos gratuitas de Render se desactivan a los 30 días o cuando están inactivas)*.
2. En `services`: cambia `plan: starter` por `plan: free` y elimina o comenta el bloque `disk:`.
   *(En el plan gratuito, las imágenes subidas residirán en el contenedor efímero y se restablecerán a los valores iniciales del repositorio en cada nuevo despliegue)*.

---

## ✅ Verificación del Despliegue

Una vez completado el despliegue en Render:
- Entra a la URL de tu servicio: `https://tu-servicio.onrender.com/`
- Inicia sesión como administrador en: `https://tu-servicio.onrender.com/admin_login.php`
- Revisa el estado de la salud en: `https://tu-servicio.onrender.com/health.php`
