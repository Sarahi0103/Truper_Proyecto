# Documentación Detallada del Sistema de Pagos y Facturación
## Ferretería FOX / Truper Platform

---

## Índice

1. [Visión General del Sistema](#visión-general)
2. [Arquitectura Técnica](#arquitectura-técnica)
3. [Base de Datos](#base-de-datos)
4. [Servicios de Backend](#servicios-de-backend)
5. [APIs del Sistema](#apis-del-sistema)
6. [Interfaces de Administración](#interfaces-de-administración)
7. [Proceso de Pago del Cliente](#proceso-de-pago-del-cliente)
8. [Integración con SAT CFDI 4.0](#integración-con-sat-cfdi-40)
9. [Flujos de Trabajo](#flujos-de-trabajo)
10. [Guía de Configuración](#guía-de-configuración)

---

## 1. Visión General del Sistema

El sistema de pagos y facturación de Ferretería FOX es una solución integral que permite:

- **Procesamiento de pagos en línea** a través de múltiples pasarelas (Stripe, Mercado Pago)
- **Pagos con SPEI** y transferencias bancarias mexicanas
- **Facturación electrónica CFDI 4.0** conforme a normativas del SAT
- **Gestión de múltiples bancos mexicanos** para recibir pagos
- **Configuración flexible** de métodos de pago y cuentas bancarias
- **Emisión automática de facturas** con datos fiscales del cliente

### Componentes Principales

```
┌─────────────────────────────────────────────────────────────┐
│                    Sistema de Pagos                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────────┐      ┌──────────────────┐           │
│  │  Cliente (Web)   │─────▶│   Checkout PHP    │           │
│  └──────────────────┘      └──────────────────┘           │
│                                    │                        │
│                                    ▼                        │
│  ┌──────────────────┐      ┌──────────────────┐           │
│  │  Admin (Web)     │─────▶│   APIs PHP       │           │
│  └──────────────────┘      └──────────────────┘           │
│                                    │                        │
│                                    ▼                        │
│  ┌──────────────────┐      ┌──────────────────┐           │
│  │  Servicios PHP   │◀─────│  PostgreSQL DB   │           │
│  └──────────────────┘      └──────────────────┘           │
│                                    │                        │
│                                    ▼                        │
│  ┌──────────────────┐      ┌──────────────────┐           │
│  │  Stripe API      │      │  Mercado Pago    │           │
│  └──────────────────┘      │  API             │           │
│                            └──────────────────┘           │
│                                    │                        │
│                                    ▼                        │
│  ┌──────────────────┐      ┌──────────────────┐           │
│  │  Facturapi API   │      │  SAT (CFDI 4.0)   │           │
│  └──────────────────┘      └──────────────────┘           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Arquitectura Técnica

### Stack Tecnológico

- **Backend**: PHP 8.1+
- **Base de Datos**: PostgreSQL con JSONB
- **Pasarelas de Pago**: Stripe, Mercado Pago
- **Facturación Electrónica**: Facturapi (PAC autorizado SAT)
- **Frontend**: HTML5, CSS3, JavaScript Vanilla
- **Seguridad**: CSRF tokens, autenticación por sesión, roles (admin/employee)

### Estructura de Archivos

```
proyecto_Truper/
├── database_migrations/
│   ├── add_mexican_banks.sql              # Bancos mexicanos y métodos de pago
│   └── add_admin_payment_accounts.sql    # Cuentas de pago del admin
├── src/Services/
│   ├── PaymentGatewayService.php          # Integración Stripe/Mercado Pago
│   ├── SatBillingService.php             # Facturación CFDI 4.0
│   └── MexicanBanksService.php           # Gestión de bancos mexicanos
├── public/
│   ├── checkout.php                      # Proceso de pago del cliente
│   ├── admin_online_billing.php           # Interfaz admin facturación
│   ├── admin_payment_config.php          # Interfaz admin configuración pagos
│   └── api/
│       ├── admin_payment_config.php      # API de configuración de pagos
│       └── admin_online_billing_api.php  # API de facturación
└── config/
    └── config.php                        # Configuración global
```

---

## 3. Base de Datos

### 3.1 Tabla: `mexican_banks`

**Propósito**: Almacena la configuración de bancos mexicanos para recibir pagos por SPEI y transferencias.

**Estructura**:
```sql
CREATE TABLE mexican_banks (
    id SERIAL PRIMARY KEY,
    bank_name VARCHAR(100) NOT NULL,           -- Nombre del banco (ej: BBVA Bancomer)
    bank_code VARCHAR(3) NOT NULL,             -- Código SAT del banco (3 dígitos)
    clabe VARCHAR(18) NOT NULL UNIQUE,        -- CLABE interbancaria (18 dígitos)
    account_number VARCHAR(20),                -- Número de cuenta
    account_holder VARCHAR(255) NOT NULL,      -- Titular de la cuenta
    rfc VARCHAR(13),                           -- RFC del titular
    logo_url VARCHAR(255),                     -- URL del logo del banco
    supports_spei BOOLEAN DEFAULT true,        -- Soporta SPEI
    supports_card BOOLEAN DEFAULT false,        -- Soporta pagos con tarjeta
    supports_transfer BOOLEAN DEFAULT true,    -- Soporta transferencias
    is_active BOOLEAN DEFAULT true,            -- Cuenta activa
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Funciones SQL**:
- `get_active_banks()` - Retorna bancos activos
- `get_bank_by_id(id)` - Retorna un banco específico
- `get_banks_by_payment_method(method)` - Filtra bancos por método de pago

**Datos Iniciales**: 10 bancos preconfigurados (BBVA Bancomer, Banorte, Santander, Banamex, HSBC, Scotiabank, Inbursa, Banco Azteca, Banco del Bajío, Afirme)

---

### 3.2 Tabla: `payment_methods_config`

**Propósito**: Configura los métodos de pago disponibles y sus comisiones.

**Estructura**:
```sql
CREATE TABLE payment_methods_config (
    method_code VARCHAR(50) PRIMARY KEY,        -- Código único del método
    method_name VARCHAR(100) NOT NULL,         -- Nombre visible
    description TEXT,                          -- Descripción del método
    fee_percentage DECIMAL(5,2) DEFAULT 0,      -- Comisión porcentual
    fee_fixed DECIMAL(10,2) DEFAULT 0,         -- Comisión fija
    min_amount DECIMAL(10,2) DEFAULT 0,        -- Monto mínimo
    max_amount DECIMAL(10,2),                  -- Monto máximo
    is_enabled BOOLEAN DEFAULT true,           -- Método habilitado
    sort_order INTEGER DEFAULT 0,              -- Orden de visualización
    metadata JSONB                             -- Configuración adicional
);
```

**Métodos Configurados**:
- `card` - Tarjeta de crédito/débito
- `spei` - SPEI (Sistema de Pagos Electrónicos Interbancarios)
- `cash` - Pago contra entrega
- `transfer` - Transferencia bancaria
- `mercadopago` - Mercado Pago
- `stripe` - Stripe

---

### 3.3 Tabla: `sat_fiscal_config`

**Propósito**: Almacena la configuración fiscal de la empresa para emitir facturas CFDI 4.0.

**Estructura**:
```sql
CREATE TABLE sat_fiscal_config (
    id SERIAL PRIMARY KEY,
    company_rfc VARCHAR(13) NOT NULL UNIQUE,   -- RFC de la empresa
    company_tax_name VARCHAR(255) NOT NULL,    -- Razón social
    company_tax_regime VARCHAR(3) NOT NULL,    -- Régimen fiscal SAT
    company_zip_code VARCHAR(5) NOT NULL,      -- Código postal fiscal
    company_email VARCHAR(255),                -- Email de facturación
    company_phone VARCHAR(20),                 -- Teléfono
    company_address TEXT,                      -- Dirección fiscal
    facturapi_api_key VARCHAR(255),            -- API Key de Facturapi
    pac_provider VARCHAR(50) DEFAULT 'facturapi', -- Proveedor PAC
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

### 3.4 Tabla: `admin_payment_accounts`

**Propósito**: Gestiona las cuentas bancarias y tarjetas del administrador para recibir pagos.

**Estructura**:
```sql
CREATE TABLE admin_payment_accounts (
    id SERIAL PRIMARY KEY,
    account_type VARCHAR(50) NOT NULL,          -- Tipo: stripe, mercadopago, bank_account
    account_name VARCHAR(100) NOT NULL,       -- Nombre descriptivo
    provider_account_id VARCHAR(255),          -- ID en Stripe/Mercado Pago
    bank_name VARCHAR(100),                    -- Nombre del banco
    last_4 VARCHAR(4),                         -- Últimos 4 dígitos
    clabe VARCHAR(18),                         -- CLABE para cuentas bancarias
    account_holder VARCHAR(255),               -- Titular
    rfc VARCHAR(13),                            -- RFC del titular
    is_primary BOOLEAN DEFAULT false,          -- Cuenta principal
    is_active BOOLEAN DEFAULT true,           -- Cuenta activa
    payment_gateway VARCHAR(50),               -- stripe, mercadopago
    metadata JSONB,                            -- Datos adicionales
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Funciones SQL**:
- `get_active_payment_accounts()` - Retorna cuentas activas
- `get_primary_payment_account()` - Retorna cuenta principal
- `set_primary_payment_account(account_id)` - Establece cuenta principal

---

## 4. Servicios de Backend

### 4.1 MexicanBanksService.php

**Propósito**: Servicio centralizado para la gestión de bancos mexicanos y métodos de pago.

**Métodos Principales**:

```php
class MexicanBanksService {
    // ===== BANCOS MEXICANOS =====
    public function getActiveBanks()
    // Retorna todos los bancos activos
    
    public function getBankById($bankId)
    // Retorna un banco específico por ID
    
    public function addBank($data)
    // Agrega un nuevo banco con validación de CLABE
    
    public function updateBank($bankId, $data)
    // Actualiza datos de un banco existente
    
    public function deleteBank($bankId)
    // Elimina (soft delete) un banco
    
    public function getBanksByPaymentMethod($method)
    // Filtra bancos que soportan un método específico
    
    // ===== MÉTODOS DE PAGO =====
    public function getActivePaymentMethods()
    // Retorna métodos de pago habilitados
    
    public function getPaymentMethod($methodCode)
    // Retorna configuración de un método específico
    
    public function calculatePaymentFee($amount, $methodCode)
    // Calcula comisiones para un monto y método
    
    // ===== VALIDACIÓN =====
    public function validateClabe($clabe)
    // Valida CLABE con algoritmo de dígito de control
    
    // ===== CONFIGURACIÓN SAT =====
    public function getSatFiscalConfig()
    // Retorna configuración fiscal de la empresa
    
    public function updateSatFiscalConfig($data)
    // Actualiza configuración fiscal con validación de RFC
}
```

**Validación de CLABE**:
- Verifica longitud de 18 dígitos
- Valida que sean solo dígitos
- Aplica algoritmo de dígito de control (módulo 10)

---

### 4.2 PaymentGatewayService.php

**Propósito**: Integración con pasarelas de pago externas (Stripe y Mercado Pago).

**Métodos Principales**:

```php
class PaymentGatewayService {
    // ===== STRIPE =====
    public function processStripePayment($amount, $paymentMethodId, $orderData)
    // Procesa pago con Stripe usando PaymentIntent
    
    public function confirmStripePayment($paymentIntentId)
    // Confirma un pago Stripe existente
    
    public function refundStripePayment($paymentIntentId, $amount = null)
    // Reembolsa pago Stripe (parcial o total)
    
    // ===== MERCADO PAGO =====
    public function processMercadoPagoPayment($amount, $paymentData, $orderData)
    // Procesa pago con Mercado Pago
    
    public function getMercadoPagoPaymentStatus($paymentId)
    // Consulta estado de pago en Mercado Pago
    
    public function refundMercadoPagoPayment($paymentId, $amount = null)
    // Reembolsa pago Mercado Pago
    
    // ===== MÉTODOS LOCALES =====
    public function processSpeiPayment($amount, $bankData, $orderData)
    // Registra pago SPEI pendiente de confirmación
    
    public function processCashOnDelivery($amount, $orderData)
    // Registra pago contra entrega
    
    // ===== UTILIDADES =====
    private function getSetting($key)
    // Obtiene configuración de sistema o variables de entorno
}
```

**Configuración**:
- API keys de Stripe y Mercado Pago desde `system_config` o `.env`
- Soporta modo sandbox y producción
- Manejo de errores con logging

---

### 4.3 SatBillingService.php

**Propósito**: Integración con Facturapi para emisión de facturas CFDI 4.0.

**Métodos Principales**:

```php
class SatBillingService {
    // ===== EMISIÓN DE FACTURAS =====
    public function issueInvoice($orderData, $customerFiscalData)
    // Emite factura CFDI 4.0 para una orden
    
    public function issueGlobalInvoice($orders, $period)
    // Emite factura global para múltiples órdenes
    
    // ===== CANCELACIÓN =====
    public function cancelInvoice($invoiceId, $reason)
    // Cancela factura con motivo SAT (01, 02, 03, 04)
    
    // ===== CONSULTA =====
    public function getInvoiceStatus($invoiceId)
    // Consulta estado de factura en SAT
    
    public function downloadInvoicePdf($invoiceId)
    // Descarga PDF de factura
    
    public function downloadInvoiceXml($invoiceId)
    // Descarga XML de factura
    
    // ===== VALIDACIÓN =====
    public function validateRfc($rfc)
    // Valida formato de RFC mexicano
    
    // ===== MAPPING =====
    private function mapPaymentMethodToSatCode($method)
    // Mapea métodos de pago a códigos SAT
}
```

**Códigos de Uso CFDI**:
- G01 - Adquisición de mercancías
- G02 - Devoluciones, descuentos o bonificaciones
- G03 - Gastos en general
- P01 - Por definir

**Motivos de Cancelación**:
- 01 - Comprobante emitido con errores con relación
- 02 - Comprobante emitido con errores sin relación
- 03 - No se realizó la operación
- 04 - Operación nominativa relacionada en factura global

---

## 5. APIs del Sistema

### 5.1 API: admin_payment_config.php

**Endpoint**: `/api/admin_payment_config.php`

**Propósito**: Gestión completa de configuración de pagos y bancos mexicanos.

**Acciones Disponibles**:

#### Bancos Mexicanos

```
GET /api/admin_payment_config.php?action=get_banks
Respuesta: { success: true, banks: [...] }

GET /api/admin_payment_config.php?action=get_bank&bank_id=1
Respuesta: { success: true, bank: {...} }

POST /api/admin_payment_config.php?action=add_bank
Body: { bank_name, bank_code, clabe, account_holder, ... }
Respuesta: { success: true, bank_id: 1 }

POST /api/admin_payment_config.php?action=update_bank&bank_id=1
Body: { bank_name, clabe, ... }
Respuesta: { success: true }

POST /api/admin_payment_config.php?action=delete_bank&bank_id=1
Respuesta: { success: true }

GET /api/admin_payment_config.php?action=get_banks_by_method&method=spei
Respuesta: { success: true, banks: [...] }
```

#### Métodos de Pago

```
GET /api/admin_payment_config.php?action=get_payment_methods
Respuesta: { success: true, methods: [...] }

GET /api/admin_payment_config.php?action=get_payment_method&method_code=card
Respuesta: { success: true, method: {...} }

GET /api/admin_payment_config.php?action=calculate_fee&amount=1000&method_code=card
Respuesta: { success: true, fee: 35.50 }
```

#### Configuración Fiscal SAT

```
GET /api/admin_payment_config.php?action=get_sat_config
Respuesta: { success: true, config: {...} }

POST /api/admin_payment_config.php?action=update_sat_config
Body: { company_rfc, company_tax_name, company_tax_regime, ... }
Respuesta: { success: true }
```

#### Cuentas de Pago del Administrador

```
GET /api/admin_payment_config.php?action=get_payment_accounts
Respuesta: { success: true, accounts: [...] }

POST /api/admin_payment_config.php?action=add_payment_account
Body: { account_name, payment_gateway, provider_account_id, ... }
Respuesta: { success: true, account_id: 1 }

POST /api/admin_payment_config.php?action=update_payment_account&account_id=1
Body: { account_name, payment_gateway, ... }
Respuesta: { success: true }

POST /api/admin_payment_config.php?action=set_primary_account&account_id=1
Respuesta: { success: true }

POST /api/admin_payment_config.php?action=delete_payment_account&account_id=1
Respuesta: { success: true }
```

**Seguridad**:
- Requiere autenticación (admin o employee)
- CSRF token para operaciones POST
- Validación de CLABE y RFC
- Manejo de errores con códigos HTTP apropiados

---

### 5.2 API: admin_online_billing_api.php

**Endpoint**: `/api/admin_online_billing_api.php`

**Propósito**: Gestión de facturación, timbrado SAT y operaciones de facturas.

**Acciones Principales**:

```
POST /api/admin_online_billing_api.php?action=save_config
Body: { mercadopago_access_token, stripe_secret_key, ... }
Respuesta: { success: true }

POST /api/admin_online_billing_api.php?action=issue_invoice
Body: { order_id, customer_rfc, cfdi_use, ... }
Respuesta: { success: true, invoice_id: "xxx", pdf_url: "..." }

POST /api/admin_online_billing_api.php?action=cancel_sat_invoice
Body: { order_id, sat_reason, notes }
Respuesta: { success: true }

GET /api/admin_online_billing_api.php?action=get_invoices
Respuesta: { success: true, invoices: [...] }
```

---

## 6. Interfaces de Administración

### 6.1 admin_online_billing.php

**URL**: `/admin_online_billing.php`

**Propósito**: Interfaz principal de administración para facturación fiscal y pasarelas de pago.

**Requisitos de Acceso**:
- Usuario autenticado
- Rol: admin o employee

**Estructura de la Página**:

#### Header de Navegación
```
┌─────────────────────────────────────────────────────────┐
│  [Logo]  [Admin Tienda ▼]  [Admin Local ▼]  [Usuario]  │
└─────────────────────────────────────────────────────────┘
```

#### Hero Section
```
┌─────────────────────────────────────────────────────────┐
│  FT  Módulo de Facturación Fiscal y Pasarelas de Cobro │
│  Gestión integral de cobros en línea, timbrado fiscal  │
│  CFDI 4.0, cancelaciones formales y cumplimiento       │
│  [ENTORNO: PRODUCCIÓN (EN VIVO)]                       │
└─────────────────────────────────────────────────────────┘
```

#### Tabs de Navegación
```
┌─────────────────────────────────────────────────────────┐
│ [Pasarelas de Cobro] [Configuración SAT] [Guía Legal]  │
│ [Monitor de Facturas] [Factura Global] [Respaldos]     │
└─────────────────────────────────────────────────────────┘
```

---

#### Tab 1: Pasarelas de Cobro en Línea

**Sección 1: Configuración de API Keys**

**Campos**:
- **Entorno de Procesamiento**: Producción / Sandbox
- **Mercado Pago - Public Key**: Clave pública para frontend
- **Mercado Pago - Access Token**: Token de acceso para backend
- **Stripe - Publishable Key**: Clave pública para frontend
- **Stripe - Secret Key**: Clave secreta para backend

**Funcionalidad**:
- Guardar configuración en `system_config`
- Validar formato de API keys
- Cambiar entre sandbox y producción

**Sección 2: Configuración SPEI**

**Campos**:
- **Banco Emisor para SPEI**: Nombre del banco
- **CLABE Interbancaria**: 18 dígitos
- **Titular de la Cuenta**: Razón social o nombre exacto

**Funcionalidad**:
- Validación de CLABE (18 dígitos)
- Guardar en `sat_fiscal_config`

**Sección 3: Cuentas y Tarjetas para Recibir Pagos** (NUEVA)

**Visualización**:
```
┌─────────────────────────────────────────────────────────┐
│ 💳 Cuentas y Tarjetas para Recibir Pagos                │
│ Configura las cuentas bancarias y tarjetas donde        │
│ recibirás los pagos de tus clientes.                    │
│                                                          │
│ [Lista de cuentas configuradas]                         │
│                                                          │
│ [+ Agregar Nueva Cuenta/Tarjeta]                        │
└─────────────────────────────────────────────────────────┘
```

**Card de Cuenta**:
```
┌─────────────────────────────────────────────────────────┐
│ Cuenta Principal BBVA [PRINCIPAL]                         │
│ 💳 Stripe - BBVA - ****1234                               │
│ [Establecer Principal] [Editar] [Eliminar]                │
└─────────────────────────────────────────────────────────┘
```

**Modal para Agregar/Editar Cuenta**:

**Campos**:
- **Nombre de la Cuenta**: Identificador descriptivo
- **Pasarela de Pago**: Stripe / Mercado Pago / Cuenta Bancaria Directa
- **ID de Cuenta Stripe** (si es Stripe): `acct_xxxxxxxx`
- **ID de Cuenta Mercado Pago** (si es MP): `collector_xxxxxxxx`
- **Banco** (si es cuenta bancaria): Nombre del banco
- **CLABE** (si es cuenta bancaria): 18 dígitos
- **Últimos 4 dígitos**: Para identificación
- **Titular**: Nombre del titular
- **RFC** (opcional): RFC del titular

**Funcionalidad**:
- Agregar nueva cuenta
- Editar cuenta existente
- Establecer como principal
- Eliminar cuenta (soft delete)
- Validación de campos según tipo de cuenta

---

#### Tab 2: Configuración Fiscal CFDI 4.0

**Campos**:
- **Facturapi API Key**: Clave para timbrado SAT
- **RFC de la Sucursal/Empresa**: RFC del emisor
- **Razón Social Exacta**: Nombre fiscal sin régimen
- **Régimen Fiscal (SAT)**: Selección del catálogo SAT
- **Código Postal Fiscal**: CP de emisión
- **Email de Facturación**: Para recibir notificaciones
- **Teléfono**: Contacto fiscal
- **Dirección Fiscal**: Domicilio completo

**Catálogos SAT**:
- Regímenes fiscales (601, 603, 606, etc.)
- Usos de CFDI (G01, G02, G03, P01)

**Funcionalidad**:
- Validación de RFC con regex
- Guardar en `sat_fiscal_config`
- Integración con Facturapi

---

#### Tab 3: Guía Legal y Normativa SAT

**Contenido**:
- Requisitos para emisor CFDI 4.0
- Catálogos obligatorios del SAT
- Motivos de cancelación permitidos
- Plazos para cancelación
- Guía de cumplimiento fiscal

---

#### Tab 4: Monitor de Facturas y Cancelaciones

**KPIs**:
- Total de facturas emitidas
- Facturas activas en SAT
- Facturas canceladas
- Facturas público en general

**Tabla de Facturas**:
- ID de factura
- Número de orden
- RFC del cliente
- Monto total
- Estado (activa/cancelada)
- Fecha de emisión
- Motivo de cancelación (si aplica)

**Acciones**:
- Ver PDF de factura
- Descargar XML
- Cancelar factura (con motivo SAT)

---

#### Tab 5: Factura Global

**Funcionalidad**:
- Emitir factura global para múltiples operaciones
- Selección de periodo (diario, semanal, mensual)
- Agrupación de operaciones por RFC
- Generación de PDF y XML

---

#### Tab 6: Respaldos BD & CSD SAT

**Funcionalidad**:
- Respaldos de base de datos
- Gestión de CSD (Certificados de Sello Digital)
- Exportación de configuración fiscal

---

### 6.2 admin_payment_config.php

**URL**: `/admin_payment_config.php`

**Propósito**: Interfaz específica para configuración de bancos mexicanos y métodos de pago.

**Requisitos de Acceso**:
- Usuario autenticado
- Rol: admin o employee

**Estructura de la Página**:

#### Sección 1: Bancos Mexicanos

**Visualización**:
```
┌─────────────────────────────────────────────────────────┐
│ 🏦 Bancos Mexicanos Configurados                         │
│                                                          │
│ [Grid de tarjetas de bancos]                            │
│                                                          │
│ [+ Agregar Nuevo Banco]                                  │
└─────────────────────────────────────────────────────────┘
```

**Card de Banco**:
```
┌─────────────────────────────────────────────────────────┐
│ BBVA Bancomer                                            │
│ Código: 012                                              │
│ CLABE: 012180001234567890                                │
│ Titular: Ferretería FOX S.A. de C.V.                    │
│ SPEI: ✅  Tarjeta: ❌  Transferencia: ✅                 │
│ [Editar] [Eliminar]                                       │
└─────────────────────────────────────────────────────────┘
```

**Modal para Agregar/Editar Banco**:

**Campos**:
- **Nombre del Banco**: Ej: BBVA Bancomer
- **Código SAT**: 3 dígitos (ej: 012)
- **CLABE**: 18 dígitos con validación
- **Número de Cuenta**: Opcional
- **Titular de la Cuenta**: Nombre/Razón social
- **RFC del Titular**: 13 caracteres
- **URL del Logo**: Imagen del banco
- **Soporta SPEI**: Checkbox
- **Soporta Tarjeta**: Checkbox
- **Soporta Transferencia**: Checkbox
- **Activo**: Checkbox

**Funcionalidad**:
- Validación de CLABE en tiempo real
- Agregar nuevo banco
- Editar banco existente
- Eliminar banco (soft delete)

---

#### Sección 2: Métodos de Pago

**Visualización**:
```
┌─────────────────────────────────────────────────────────┐
│ 💳 Métodos de Pago Configurados                        │
│                                                          │
│ [Lista de métodos con toggle]                           │
└─────────────────────────────────────────────────────────┘
```

**Item de Método**:
```
┌─────────────────────────────────────────────────────────┐
│ Tarjeta de Crédito/Débito                                │
│ Comisión: 3.5% + $3.00 | Min: $100                       │
│ Pagos con tarjeta Visa, Mastercard, Amex               │
│ [Toggle ON/OFF]                                          │
└─────────────────────────────────────────────────────────┘
```

**Funcionalidad**:
- Habilitar/deshabilitar métodos
- Ver comisiones configuradas
- Ver montos mínimos/máximos

---

#### Sección 3: Configuración Fiscal SAT

**Campos**:
- **RFC de la Empresa**: 13 caracteres
- **Razón Social**: Nombre fiscal
- **Régimen Fiscal**: Selección del catálogo
- **Código Postal Fiscal**: 5 dígitos
- **Email de Facturación**: Para notificaciones
- **Teléfono**: Contacto
- **Dirección Fiscal**: Domicilio completo
- **API Key de Facturapi**: Para timbrado
- **Proveedor PAC**: Facturapi, Finkok, SW

**Funcionalidad**:
- Validación de RFC
- Guardar configuración
- Integración con Facturapi

---

## 7. Proceso de Pago del Cliente

### 7.1 checkout.php

**URL**: `/checkout.php`

**Propósito**: Proceso de pago del cliente con selección de método y banco.

**Estructura de la Página**:

#### Secciones del Formulario

1. **Información de Contacto**
   - Nombre
   - Email
   - Teléfono

2. **Dirección de Entrega**
   - Calle y número
   - Colonia
   - Ciudad
   - Estado
   - Código Postal
   - Referencias

3. **Método de Envío**
   - Estándar (3-5 días)
   - Express (1-2 días)
   - Recoger en tienda

4. **Código Promocional**
   - Ingresar cupón
   - Aplicar descuento

5. **Notas del Pedido**
   - Instrucciones especiales

6. **Datos Fiscales CFDI 4.0** (opcional)
   - RFC del cliente
   - Razón Social
   - Régimen Fiscal
   - Uso de CFDI
   - Código Postal Fiscal

7. **Método de Pago** (NUEVA FUNCIONALIDAD)

**Opciones Disponibles**:
```
┌─────────────────────────────────────────────────────────┐
│ Método de Pago                                          │
│                                                          │
│ ○ Tarjeta de Crédito/Débito                             │
│ ○ SPEI (Transferencia Electrónica)                      │
│ ○ Transferencia Bancaria                                │
│ ○ Mercado Pago                                          │
│ ○ Efectivo (Pago contra entrega)                        │
└─────────────────────────────────────────────────────────┘
```

**Selección Dinámica de Banco**:

**Para SPEI**:
```
┌─────────────────────────────────────────────────────────┐
│ Selecciona tu banco para SPEI:                          │
│ [Dropdown con bancos que soportan SPEI]                 │
│                                                          │
│ Información del banco seleccionado:                      │
│ Banco: BBVA Bancomer                                     │
│ CLABE: 012180001234567890                                │
│ Titular: Ferretería FOX S.A. de C.V.                    │
│                                                          │
│ Instrucciones:                                           │
│ 1. Realiza la transferencia SPEI a la CLABE indicada    │
│ 2. Usa tu número de pedido como referencia              │
│ 3. El pago se confirmará en 1-2 horas hábiles            │
└─────────────────────────────────────────────────────────┘
```

**Para Transferencia**:
```
┌─────────────────────────────────────────────────────────┐
│ Selecciona tu banco para transferencia:                  │
│ [Dropdown con bancos que soportan transferencia]        │
│                                                          │
│ Información del banco seleccionado:                      │
│ Banco: Banorte                                           │
│ CLABE: 072180001234567890                                │
│ Titular: Ferretería FOX S.A. de C.V.                    │
│                                                          │
│ Instrucciones:                                           │
│ 1. Realiza la transferencia a la CLABE indicada         │
│ 2. Usa tu número de pedido como referencia              │
│ 3. El pago se confirmará en 24-48 horas                 │
└─────────────────────────────────────────────────────────┘
```

8. **Resumen del Pedido**
   - Lista de productos
   - Subtotal
   - Envío
   - Descuento
   - Comisión del método de pago
   - Total

---

### 7.2 JavaScript del Checkout

**Funciones Principales**:

```javascript
// Cargar bancos mexicanos al iniciar
function loadMexicanBanks() {
    fetch('/api/admin_payment_config.php?action=get_banks')
        .then(response => response.json())
        .then(data => {
            mexicanBanks = data.banks;
            populateBankSelects();
        });
}

// Poblar selects de bancos según método
function populateBankSelects() {
    const speiBanks = mexicanBanks.filter(b => b.supports_spei);
    const transferBanks = mexicanBanks.filter(b => b.supports_transfer);
    
    // Llenar dropdowns
}

// Manejar cambio de método de pago
function handlePaymentMethodChange() {
    const method = document.querySelector('input[name="paymentMethod"]:checked').value;
    
    // Mostrar sección de bancos según método
    if (method === 'spei') {
        document.getElementById('bankSelectionSection').style.display = 'block';
    } else if (method === 'transfer') {
        document.getElementById('transferBankSection').style.display = 'block';
    }
}

// Mostrar datos del banco seleccionado
function handleBankSelection(bankId) {
    const bank = mexicanBanks.find(b => b.id === bankId);
    
    // Mostrar CLABE, titular, instrucciones
}
```

---

### 7.3 Flujo de Pago

```
┌─────────────────────────────────────────────────────────┐
│ 1. Cliente completa datos del pedido                     │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Cliente selecciona método de pago                     │
│    - Tarjeta: Redirige a Stripe/Mercado Pago            │
│    - SPEI: Muestra bancos disponibles                    │
│    - Transferencia: Muestra bancos disponibles           │
│    - Efectivo: Marca como pago contra entrega           │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Si es SPEI/Transferencia:                             │
│    - Cliente selecciona su banco                         │
│    - Sistema muestra CLABE y datos del titular            │
│    - Cliente realiza transferencia externa              │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Si es Tarjeta:                                        │
│    - Cliente ingresa datos de tarjeta                   │
│    - Sistema procesa pago con Stripe/Mercado Pago       │
│    - Pago confirmado o rechazado                        │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Sistema crea orden en estado:                        │
│    - "paid" (tarjeta confirmada)                         │
│    - "pending_payment" (spei/transferencia)             │
│    - "cod" (pago contra entrega)                        │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 6. Si cliente proporcionó datos fiscales:               │
│    - Sistema emite factura CFDI 4.0 automáticamente      │
│    - Factura asociada a la orden                        │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 7. Cliente recibe confirmación:                          │
│    - Email con detalles del pedido                       │
│    - PDF de factura (si aplica)                         │
│    - Instrucciones de pago (si es SPEI/transferencia)   │
└─────────────────────────────────────────────────────────┘
```

---

## 8. Integración con SAT CFDI 4.0

### 8.1 Flujo de Emisión de Factura

```
┌─────────────────────────────────────────────────────────┐
│ 1. Cliente proporciona datos fiscales en checkout       │
│    - RFC                                                 │
│    - Razón Social                                        │
│    - Régimen Fiscal                                      │
│    - Uso de CFDI                                         │
│    - Código Postal Fiscal                                │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Sistema valida datos fiscales                        │
│    - Formato de RFC (regex)                              │
│    - Catálogos SAT válidos                              │
│    - Código postal correcto                              │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Sistema recupera configuración fiscal del emisor     │
│    - RFC de la empresa                                   │
│    - Razón Social                                        │
│    - Régimen Fiscal                                      │
│    - API Key de Facturapi                               │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Sistema construye objeto de factura CFDI 4.0         │
│    - Datos del emisor                                    │
│    - Datos del receptor (cliente)                        │
│    - Conceptos (productos)                               │
│    - Impuestos (IVA)                                     │
│    - Método de pago                                     │
│    - Forma de pago                                      │
│    - Uso de CFDI                                         │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Sistema envía factura a Facturapi API                 │
│    POST https://api.facturapi.mx/v1/invoices            │
│    Headers: Authorization: Bearer {api_key}              │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 6. Facturapi timbra factura ante el SAT                 │
│    - Genera XML con CFDI 4.0                             │
│    - Firma con CSD del emisor                            │
│    - Envía al SAT para validación                        │
│    - Retorna folio fiscal y UUID                         │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 7. Sistema guarda factura en base de datos              │
│    - Folio fiscal                                        │
│    - UUID                                                │
│    - URL del PDF                                         │
│    - URL del XML                                         │
│    - Estado: "active"                                    │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 8. Sistema envía factura al cliente                     │
│    - Email con PDF adjunto                               │
│    - Enlace para descargar XML                           │
└─────────────────────────────────────────────────────────┘
```

---

### 8.2 Mapeo de Métodos de Pago a Códigos SAT

| Método del Sistema | Código SAT Forma de Pago | Código SAT Método de Pago |
|-------------------|-------------------------|-------------------------|
| Tarjeta           | 04 - Tarjeta de crédito | PPD - Pago en parcialidades |
| SPEI              | 03 - Transferencia electrónica | PUE - Pago en una sola exhibición |
| Transferencia     | 03 - Transferencia electrónica | PPD - Pago en parcialidades |
| Efectivo          | 01 - Efectivo | PUE - Pago en una sola exhibición |
| Mercado Pago      | 04 - Tarjeta de crédito | PPD - Pago en parcialidades |
| Stripe            | 04 - Tarjeta de crédito | PPD - Pago en parcialidades |

---

### 8.3 Catálogos SAT Utilizados

**Regímenes Fiscales (c_RegimenFiscal)**:
- 601 - General de Ley Personas Morales
- 603 - Personas Morales con Fines no Lucrativos
- 606 - Régimen Simplificado de Confianza
- 612 - Personas Físicas con Actividades Empresariales
- 626 - Régimen Simplificado de Confianza (RESICO)

**Usos de CFDI (c_UsoCFDI)**:
- G01 - Adquisición de mercancías
- G02 - Devoluciones, descuentos o bonificaciones
- G03 - Gastos en general
- P01 - Por definir

---

## 9. Flujos de Trabajo

### 9.1 Flujo de Configuración Inicial (Administrador)

```
┌─────────────────────────────────────────────────────────┐
│ Paso 1: Configurar Pasarelas de Pago                    │
│ 1. Acceder a admin_online_billing.php                   │
│ 2. Ir a tab "Pasarelas de Cobro"                        │
│ 3. Ingresar API keys de Stripe y Mercado Pago          │
│ 4. Seleccionar entorno (producción/sandbox)             │
│ 5. Guardar configuración                                │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ Paso 2: Configurar Datos Fiscales SAT                    │
│ 1. Ir a tab "Configuración Fiscal CFDI 4.0"             │
│ 2. Ingresar RFC de la empresa                           │
│ 3. Ingresar Razón Social                                │
│ 4. Seleccionar Régimen Fiscal                           │
│ 5. Ingresar Código Postal Fiscal                        │
│ 6. Ingresar API Key de Facturapi                        │
│ 7. Guardar configuración                                │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ Paso 3: Configurar Bancos Mexicanos                     │
│ 1. Acceder a admin_payment_config.php                   │
│ 2. Ir a sección "Bancos Mexicanos"                      │
│ 3. Editar bancos preconfigurados con CLABES reales      │
│ 4. Agregar bancos adicionales si es necesario          │
│ 5. Marcar métodos soportados (SPEI, Tarjeta, Transfer) │
│ 6. Guardar cambios                                      │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ Paso 4: Configurar Cuentas de Pago para Recibir        │
│ 1. Volver a admin_online_billing.php                    │
│ 2. Ir a sección "Cuentas y Tarjetas para Recibir Pagos" │
│ 3. Agregar cuenta Stripe con ID de cuenta               │
│ 4. Agregar cuenta Mercado Pago con ID de cuenta         │
│ 5. Agregar cuentas bancarias con CLABE                  │
│ 6. Establecer cuenta principal                          │
│ 7. Guardar cambios                                      │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ Paso 5: Configurar Métodos de Pago                     │
│ 1. En admin_payment_config.php                          │
│ 2. Ir a sección "Métodos de Pago"                       │
│ 3. Configurar comisiones por método                     │
│ 4. Establecer montos mínimos/máximos                    │
│ 5. Habilitar/deshabilitar métodos según necesidad       │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ Sistema listo para recibir pagos de clientes           │
└─────────────────────────────────────────────────────────┘
```

---

### 9.2 Flujo de Pago del Cliente (Tarjeta)

```
┌─────────────────────────────────────────────────────────┐
│ 1. Cliente agrega productos al carrito                  │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Cliente va a checkout.php                             │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Cliente completa datos de envío y contacto           │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Cliente selecciona "Tarjeta de Crédito/Débito"      │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Sistema muestra formulario de tarjeta                │
│    - Número de tarjeta                                   │
│    - Fecha de expiración                                │
│    - CVV                                                 │
│    - Nombre del titular                                 │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 6. Cliente ingresa datos y confirma pago                │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 7. Sistema envía datos a Stripe/Mercado Pago           │
│    - Crea PaymentIntent (Stripe)                        │
│    - Crea preferencia (Mercado Pago)                    │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 8. Pasarela procesa pago                                │
│    - Valida tarjeta                                     │
│    - Verifica fondos                                     │
│    - Autoriza o rechaza transacción                     │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 9. Sistema recibe respuesta                             │
│    - Si exitoso: Orden en estado "paid"                │
│    - Si fallido: Muestra error al cliente              │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 10. Si cliente proporcionó datos fiscales:              │
│     - Sistema emite factura CFDI 4.0                    │
│     - Envía factura por email                           │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 11. Cliente recibe confirmación                         │
│     - Email con detalles del pedido                     │
│     - PDF de factura                                    │
│     - Número de seguimiento                            │
└─────────────────────────────────────────────────────────┘
```

---

### 9.3 Flujo de Pago del Cliente (SPEI)

```
┌─────────────────────────────────────────────────────────┐
│ 1. Cliente agrega productos al carrito                  │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Cliente va a checkout.php                             │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Cliente completa datos de envío y contacto           │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Cliente selecciona "SPEI"                             │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Sistema carga bancos configurados por admin          │
│    - Filtra bancos que soportan SPEI                    │
│    - Muestra dropdown de bancos                         │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 6. Cliente selecciona su banco                          │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 7. Sistema muestra información del banco                │
│    - Nombre del banco                                    │
│    - CLABE configurada por admin                        │
│    - Titular de la cuenta                               │
│    - Instrucciones de pago                              │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 8. Cliente confirma pedido                              │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 9. Sistema crea orden en estado "pending_payment"       │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 10. Cliente realiza transferencia SPEI externa          │
│     - Desde su app bancaria                             │
│     - A la CLABE mostrada                               │
│     - Usando número de pedido como referencia           │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 11. Sistema monitorea recepción de pago                 │
│     - Webhook de banco (si está configurado)            │
│     - Verificación manual por admin                      │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 12. Cuando se confirma el pago:                        │
│     - Orden cambia a estado "paid"                      │
│     - Si hay datos fiscales, se emite factura           │
│     - Cliente recibe confirmación por email             │
└─────────────────────────────────────────────────────────┘
```

---

### 9.4 Flujo de Cancelación de Factura

```
┌─────────────────────────────────────────────────────────┐
│ 1. Admin accede a admin_online_billing.php               │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Admin va a tab "Monitor de Facturas"                 │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Admin busca factura a cancelar                      │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Admin hace clic en "Cancelar"                        │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Sistema muestra modal de cancelación                │
│    - Solicita motivo SAT (01, 02, 03, 04)              │
│    - Solicita notas de cancelación                      │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 6. Admin selecciona motivo y confirma                   │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 7. Sistema envía solicitud de cancelación a Facturapi   │
│    POST /invoices/{id}/_cancel                          │
│    Body: { motivo, notes }                              │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 8. Facturapi envía solicitud de cancelación al SAT      │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 9. SAT procesa cancelación                              │
│    - Valida motivo                                      │
│    - Verifica plazos (máximo 72 horas en algunos casos) │
│    - Aprueba o rechaza cancelación                      │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 10. Sistema actualiza estado de factura                  │
│     - Estado: "cancelled"                               │
│     - Guarda motivo y fecha de cancelación              │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 11. Sistema notifica al cliente                         │
│     - Email con acuse de cancelación                    │
│     - XML de cancelación                                 │
└─────────────────────────────────────────────────────────┘
```

---

## 10. Guía de Configuración

### 10.1 Configuración de Stripe

**Pasos**:

1. Crear cuenta en [Stripe Dashboard](https://dashboard.stripe.com/)
2. Obtener API keys:
   - Publishable key (pk_live_...) para frontend
   - Secret key (sk_live_...) para backend
3. Crear cuenta conectada para recibir pagos:
   - Ir a Settings → Connect
   - Crear cuenta conectada
   - Obtener account ID (acct_...)
4. Configurar en admin_online_billing.php:
   - Ingresar Publishable key
   - Ingresar Secret key
   - Seleccionar entorno (producción/sandbox)
5. Configurar cuenta de pago:
   - Ir a sección "Cuentas y Tarjetas para Recibir Pagos"
   - Agregar cuenta Stripe
   - Ingresar account ID de cuenta conectada
   - Establecer como principal

**Webhooks (Opcional)**:
- Configurar webhook en Stripe Dashboard
- URL: `https://tu-dominio.com/api/stripe_webhook.php`
- Eventos: payment_intent.succeeded, payment_intent.failed

---

### 10.2 Configuración de Mercado Pago

**Pasos**:

1. Crear cuenta en [Mercado Pago](https://www.mercadopago.com.mx/)
2. Obtener credenciales:
   - Access Token (APP_USR-...)
   - Public Key (APP_USR-...)
3. Crear aplicación para recibir pagos:
   - Ir a Crear aplicación
   - Configurar redirect URLs
   - Obtener collector ID
4. Configurar en admin_online_billing.php:
   - Ingresar Public Key
   - Ingresar Access Token
   - Seleccionar entorno (producción/sandbox)
5. Configurar cuenta de pago:
   - Ir a sección "Cuentas y Tarjetas para Recibir Pagos"
   - Agregar cuenta Mercado Pago
   - Ingresar collector ID
   - Establecer como principal

---

### 10.3 Configuración de Facturapi

**Pasos**:

1. Crear cuenta en [Facturapi](https://www.facturapi.mx/)
2. Obtener API Key:
   - Ir a Configuración → API Keys
   - Crear nueva API key
   - Copiar sk_live_... (producción) o sk_test_... (sandbox)
3. Cargar CSD (Certificado de Sello Digital):
   - Subir archivos .cer y .key
   - Configurar contraseña del CSD
4. Configurar en admin_online_billing.php:
   - Ir a tab "Configuración Fiscal CFDI 4.0"
   - Ingresar API Key de Facturapi
   - Ingresar RFC de la empresa
   - Ingresar Razón Social
   - Seleccionar Régimen Fiscal
   - Ingresar Código Postal Fiscal
   - Guardar configuración

**Prueba de Timbrado**:
- Crear factura de prueba en modo sandbox
- Verificar que se genere XML y PDF
- Validar XML en portal del SAT

---

### 10.4 Configuración de Bancos Mexicanos

**Pasos**:

1. Acceder a admin_payment_config.php
2. Ir a sección "Bancos Mexicanos"
3. Editar bancos preconfigurados:
   - Hacer clic en "Editar" en un banco
   - Ingresar CLABE real (18 dígitos)
   - Verificar validación de CLABE
   - Ingresar titular correcto
   - Marcar métodos soportados
   - Guardar cambios
4. Agregar bancos adicionales:
   - Hacer clic en "+ Agregar Nuevo Banco"
   - Ingresar nombre del banco
   - Ingresar código SAT (3 dígitos)
   - Ingresar CLABE
   - Ingresar titular
   - Marcar métodos soportados
   - Guardar

**Validación de CLABE**:
- Sistema valida automáticamente
- Aplica algoritmo de dígito de control
- Rechaza CLABEs inválidas

---

### 10.5 Configuración de Cuentas de Pago para Recibir

**Pasos**:

1. Acceder a admin_online_billing.php
2. Ir a sección "Cuentas y Tarjetas para Recibir Pagos"
3. Agregar cuenta Stripe:
   - Hacer clic en "+ Agregar Nueva Cuenta/Tarjeta"
   - Nombre: "Cuenta Principal Stripe"
   - Pasarela: "Stripe"
   - ID de Cuenta Stripe: acct_xxxxxxxx
   - Guardar
4. Agregar cuenta Mercado Pago:
   - Hacer clic en "+ Agregar Nueva Cuenta/Tarjeta"
   - Nombre: "Cuenta Mercado Pago"
   - Pasarela: "Mercado Pago"
   - ID de Cuenta: collector_xxxxxxxx
   - Guardar
5. Agregar cuenta bancaria:
   - Hacer clic en "+ Agregar Nueva Cuenta/Tarjeta"
   - Nombre: "Cuenta BBVA"
   - Pasarela: "Cuenta Bancaria Directa"
   - Banco: BBVA Bancomer
   - CLABE: 18 dígitos
   - Últimos 4: ****
   - Titular: Nombre/Razón social
   - RFC: RFC del titular
   - Guardar
6. Establecer cuenta principal:
   - Hacer clic en "Establecer Principal" en la cuenta deseada
   - Esta cuenta se usará por defecto para recibir pagos

---

### 10.6 Configuración de Métodos de Pago

**Pasos**:

1. Acceder a admin_payment_config.php
2. Ir a sección "Métodos de Pago"
3. Configurar comisiones:
   - Editar método de pago
   - Configurar fee_percentage (ej: 3.5 para 3.5%)
   - Configurar fee_fixed (ej: 3.00 para $3.00 fijos)
   - Configurar min_amount (monto mínimo)
   - Configurar max_amount (monto máximo)
   - Guardar
4. Habilitar/deshabilitar métodos:
   - Usar toggle para habilitar/deshabilitar
   - Solo métodos habilitados aparecen en checkout

---

## 11. Seguridad y Validaciones

### 11.1 Validaciones Implementadas

**CLABE**:
- Longitud exacta de 18 dígitos
- Solo caracteres numéricos
- Algoritmo de dígito de control (módulo 10)

**RFC**:
- Formato: `[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}`
- Personas morales: 3 letras + 6 dígitos + 3 alfanuméricos
- Personas físicas: 4 letras + 6 dígitos + 3 alfanuméricos

**API Keys**:
- Formato específico de cada proveedor
- Validación de longitud y prefijos

**CSRF Protection**:
- Token generado en cada sesión
- Validado en todas las operaciones POST
- Incluido en meta tag de páginas

**Autenticación**:
- Requiere login para todas las interfaces de admin
- Validación de roles (admin/employee)
- Redirección a login si no autenticado

---

### 11.2 Manejo de Errores

**Errores de API**:
- Logging con AppLogger
- Mensajes de error descriptivos
- Códigos HTTP apropiados (400, 401, 403, 500)

**Errores de Validación**:
- Mensajes específicos por campo
- Resaltado visual de campos inválidos
- Sugerencias de corrección

**Errores de Pasarela**:
- Reintento automático (configurable)
- Fallback a métodos alternativos
- Notificación al admin por email

---

## 12. Monitoreo y Logs

### 12.1 Eventos Logueados

- Creación/actualización de bancos
- Cambios en configuración fiscal
- Emisión de facturas
- Cancelación de facturas
- Procesamiento de pagos
- Errores de API
- Intentos de acceso no autorizado

### 12.2 Métricas Disponibles

- Total de facturas emitidas
- Facturas por método de pago
- Monto total facturado
- Tasa de cancelación
- Tiempo promedio de emisión
- Errores de timbrado

---

## 13. Soporte y Mantenimiento

### 13.1 Problemas Comunes

**Factura no se emite**:
- Verificar API Key de Facturapi
- Verificar configuración fiscal del emisor
- Verificar que el RFC del cliente sea válido
- Revisar logs de errores

**Pago no se procesa**:
- Verificar API keys de Stripe/Mercado Pago
- Verificar que la cuenta de pago esté activa
- Verificar que el método de pago esté habilitado
- Revisar webhooks de pasarelas

**CLABE inválida**:
- Verificar que tenga 18 dígitos
- Verificar que sean solo números
- Usar herramienta de validación de banco

### 13.2 Actualizaciones

**Actualizar catálogos SAT**:
- Los catálogos se actualizan periódicamente
- Revisar documentación del SAT
- Actualizar en `src/utils/SatCatalogs.php`

**Actualizar SDKs**:
- Stripe SDK: `composer require stripe/stripe-php`
- Mercado Pago SDK: `composer require mercadopago/dx-php`

**Migraciones de base de datos**:
- Ejecutar `php run_migrations.php`
- Revisar logs de migración
- Hacer backup antes de migrar

---

## 14. Conclusión

El sistema de pagos y facturación de Ferretería FOX es una solución integral que:

- **Soporta múltiples métodos de pago**: Tarjeta, SPEI, transferencia, efectivo
- **Integra pasarelas internacionales**: Stripe, Mercado Pago
- **Cumple con normativas SAT**: CFDI 4.0 completo
- **Gestiona bancos mexicanos**: Configuración flexible de CLABEs
- **Permite configuración dinámica**: Admin puede ajustar todo sin código
- **Ofrece seguridad robusta**: CSRF, autenticación, validaciones
- **Proporciona monitoreo**: Logs, métricas, alertas

El sistema está diseñado para ser escalable, mantenible y fácil de usar tanto para administradores como para clientes.

---

**Documento Versión**: 1.0  
**Fecha de Creación**: 2026  
**Última Actualización**: 2026  
**Autor**: Sistema de Documentación Automática
