# Configuración Final de la Plataforma

## 1. Ejecutar Migraciones de Base de Datos

Las migraciones deben ejecutarse directamente con PostgreSQL ya que contienen funciones complejas que PHP no puede procesar correctamente.

### Opción A: Usando psql (Recomendado)

```bash
# En Windows, abre la terminal y ejecuta cada migración:

psql -h localhost -U postgres -d truper_db -f database_migrations/add_tax_fields_to_products.sql
psql -h localhost -U postgres -d truper_db -f database_migrations/add_stock_reservations.sql
psql -h localhost -U postgres -d truper_db -f database_migrations/add_payment_complements_table.sql
psql -h localhost -U postgres -d truper_db -f database_migrations/add_coupons_system.sql
psql -h localhost -U postgres -d truper_db -f database_migrations/add_user_addresses.sql
psql -h localhost -U postgres -d truper_db -f database_migrations/add_shipping_tracking.sql
psql -h localhost -U postgres -d truper_db -f database_migrations/add_online_inventory.sql
psql -h localhost -U postgres -d truper_db -f database_migrations/add_product_reviews.sql
```

### Opción B: Usando pgAdmin

1. Abre pgAdmin
2. Conéctate a tu base de datos `truper_db`
3. Ve a "Tools" > "Query Tool"
4. Abre cada archivo SQL de `database_migrations/`
5. Ejecuta cada archivo uno por uno

## 2. Configurar Variables de Entorno

Copia el archivo `.env.example` a `.env` y configura los valores:

```bash
cp .env.example .env
```

Edita `.env` con tus valores reales:

```env
# Configuración de Base de Datos
DB_HOST=localhost
DB_PORT=5432
DB_NAME=truper_db
DB_USER=postgres
DB_PASSWORD=tu_password_real

# Configuración de Stripe (Pagos con Tarjetas)
STRIPE_SECRET_KEY=sk_test_tu_clave_secreta_aqui
STRIPE_PUBLISHABLE_KEY=pk_test_tu_clave_publica_aqui
STRIPE_WEBHOOK_SECRET=whsec_tu_webhook_secret_aqui

# Configuración de Mercado Pago
MERCADOPAGO_ACCESS_TOKEN=APP_USR_tu_access_token_aqui

# Configuración de Email
EMAIL_FROM=noreply@ferreteriafox.com
EMAIL_FROM_NAME=Ferretería FOX
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=tu_email@gmail.com
SMTP_PASSWORD=tu_app_password_aqui

# Configuración de la Aplicación
APP_URL=https://ferreteriafox.com
APP_ENV=production
DEBUG=false

# Configuración de Bancos para SPEI
BANK_NAME=BBVA Bancomer
BANK_CLABE=012345678901234567
BANK_ACCOUNT_HOLDER=Ferretería FOX S.A. de C.V.
```

## 3. Configurar Credenciales de Stripe

Sigue la guía detallada en `CONFIGURACION_STRIPE.md` para:

1. Crear cuenta en Stripe
2. Obtener claves API
3. Configurar webhooks
4. Integrar Stripe.js en el frontend

## 4. Verificar Configuración

Después de configurar todo, verifica:

1. **Base de datos**: Las tablas nuevas deben existir
2. **Variables de entorno**: El archivo `.env` debe estar configurado
3. **Stripe**: Las claves deben estar configuradas en `system_settings` o `.env`

## Resumen de Migraciones

Las migraciones crean las siguientes tablas y funciones:

- `add_tax_fields_to_products.sql`: Campos de impuestos en productos
- `add_stock_reservations.sql`: Sistema de reservas de stock
- `add_payment_complements_table.sql`: Tabla de pagos complementarios
- `add_coupons_system.sql`: Sistema de cupones y descuentos
- `add_user_addresses.sql`: Gestión de direcciones de usuarios
- `add_shipping_tracking.sql`: Sistema de tracking de envíos
- `add_online_inventory.sql`: Inventario online vs local
- `add_product_reviews.sql`: Sistema de reviews y ratings

## Estado Final

Una vez completados estos pasos, la plataforma estará completamente configurada y funcional con:

✅ Sistema de pagos con tarjetas (Stripe)
✅ Sistema de pagos con Mercado Pago
✅ Sistema de pagos SPEI
✅ Sistema de pagos contra entrega
✅ Notificaciones por email
✅ Sistema de tracking de envíos
✅ Gestión de inventario online vs local
✅ Sistema de reviews y ratings
✅ Sistema de cupones y descuentos
✅ Historial de compras
✅ Gestión de direcciones múltiples
✅ Panel de administración para pedidos online
✅ Interfaz de escaneo de inventario
