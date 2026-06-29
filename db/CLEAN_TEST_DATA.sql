-- =====================================================================
-- SCRIPT DE LIMPIEZA DE DATOS DE PRUEBA (CONSERVANDO PRODUCTOS)
-- Proyecto Truper Platform
-- =====================================================================
-- Este script elimina datos de prueba generados durante el desarrollo
-- (pedidos, pagos, movimientos de caja, logs, tickets de prueba)
-- PERO MANTIENE INTACTO el catálogo de productos, categorías y configuración.
-- =====================================================================

BEGIN;

-- 1. Limpiar órdenes y transacciones de venta de prueba
TRUNCATE TABLE order_items CASCADE;
TRUNCATE TABLE payments CASCADE;
TRUNCATE TABLE orders CASCADE;

-- 2. Limpiar movimientos y sesiones de caja de prueba
TRUNCATE TABLE cash_drawer_movements CASCADE;
TRUNCATE TABLE cash_drawer_sessions CASCADE;
TRUNCATE TABLE cash_note_payments CASCADE;
TRUNCATE TABLE cash_control_notes CASCADE;

-- 3. Limpiar tareas, promociones e historial de prueba
TRUNCATE TABLE tasks CASCADE;
TRUNCATE TABLE promotions CASCADE;
TRUNCATE TABLE action_logs CASCADE;
TRUNCATE TABLE transaction_history CASCADE;
TRUNCATE TABLE supplier_orders CASCADE;
TRUNCATE TABLE supplier_calendar CASCADE;
TRUNCATE TABLE rate_limit_entries CASCADE;

-- 4. Mantener la secuencia de IDs de productos y categorías intacta
-- (NO se ejecutan TRUNCATE sobre 'products', 'product_categories', ni 'marketplace_ce_products')

COMMIT;

-- Mensaje de confirmación
-- Limpieza completada exitosamente. El catálogo de productos se ha conservado al 100%.
