-- =====================================================================
-- SCRIPT DE VACIADO COMPLETO DE BASE DE DATOS (LISTA PARA ENTREGA)
-- Proyecto Truper Platform
-- =====================================================================
-- Este script vacía TODAS las tablas de la base de datos de producción,
-- dejándola 100% limpia y lista para que la empresa empiece a registrar
-- sus propios usuarios, productos, categorías y operaciones reales.
-- =====================================================================

BEGIN;

-- 1. Vaciar tablas transaccionales y de operaciones
TRUNCATE TABLE order_items CASCADE;
TRUNCATE TABLE payments CASCADE;
TRUNCATE TABLE orders CASCADE;
TRUNCATE TABLE cash_drawer_movements CASCADE;
TRUNCATE TABLE cash_drawer_sessions CASCADE;
TRUNCATE TABLE cash_note_payments CASCADE;
TRUNCATE TABLE cash_control_notes CASCADE;
TRUNCATE TABLE cash_monthly_goals CASCADE;

-- 2. Vaciar tareas, logs, promociones e historial
TRUNCATE TABLE tasks CASCADE;
TRUNCATE TABLE promotions CASCADE;
TRUNCATE TABLE action_logs CASCADE;
TRUNCATE TABLE transaction_history CASCADE;
TRUNCATE TABLE supplier_orders CASCADE;
TRUNCATE TABLE supplier_calendar CASCADE;
TRUNCATE TABLE rate_limit_entries CASCADE;
TRUNCATE TABLE ai_predictions CASCADE;
TRUNCATE TABLE purchase_statistics CASCADE;
TRUNCATE TABLE barcode_registry CASCADE;
TRUNCATE TABLE deleted_product_skus CASCADE;

-- 3. Vaciar catálogo de productos e información comercial
TRUNCATE TABLE marketplace_ce_products CASCADE;
TRUNCATE TABLE products CASCADE;
TRUNCATE TABLE product_categories CASCADE;
TRUNCATE TABLE homepage_updates CASCADE;

-- 4. Vaciar usuarios, clientes y mayoristas
TRUNCATE TABLE wholesalers CASCADE;
TRUNCATE TABLE clients CASCADE;
TRUNCATE TABLE users CASCADE;

-- 5. Restablecer secuencias de IDs (Autoincrementables) a 1
ALTER SEQUENCE IF EXISTS users_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS clients_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS products_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS product_categories_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS orders_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS order_items_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS payments_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS tasks_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS wholesalers_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS homepage_updates_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS marketplace_ce_products_id_seq RESTART WITH 1;

COMMIT;

-- Base de datos totalmente vacía y limpia. Lista para instalación en producción.
