-- ============================================================
-- MIGRACIÓN CONSOLIDADA V2 Y CARACTERÍSTICAS AVANZADAS
-- Truper Platform - PostgreSQL
-- Multi-almacén, Kardex de inventario, Historial de precios,
-- Backorders, Soft-deletes y Cupones/Tracking/Direcciones
-- ============================================================

-- 1. Tablas de Multi-Almacén y Sucursales
CREATE TABLE IF NOT EXISTS warehouses (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    phone VARCHAR(30),
    is_main BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertar almacén central predeterminado si no existe
INSERT INTO warehouses (code, name, address, is_main, is_active)
VALUES ('ALM-CENTRAL', 'Almacén Central / Tienda Principal', 'Matriz Truper', true, true)
ON CONFLICT (code) DO NOTHING;

-- Existencias por Almacén
CREATE TABLE IF NOT EXISTS warehouse_stock (
    id SERIAL PRIMARY KEY,
    warehouse_id INTEGER NOT NULL REFERENCES warehouses(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    stock_quantity INTEGER NOT NULL DEFAULT 0,
    min_stock INTEGER DEFAULT 5,
    max_stock INTEGER DEFAULT 1000,
    aisle VARCHAR(50),
    shelf VARCHAR(50),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(warehouse_id, product_id)
);

-- Traspasos entre almacenes
CREATE TABLE IF NOT EXISTS stock_transfers (
    id SERIAL PRIMARY KEY,
    transfer_number VARCHAR(50) UNIQUE NOT NULL,
    from_warehouse_id INTEGER NOT NULL REFERENCES warehouses(id),
    to_warehouse_id INTEGER NOT NULL REFERENCES warehouses(id),
    status VARCHAR(30) DEFAULT 'pending', -- pending, in_transit, completed, cancelled
    requested_by INTEGER REFERENCES users(id),
    received_by INTEGER REFERENCES users(id),
    notes TEXT,
    transfer_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stock_transfer_items (
    id SERIAL PRIMARY KEY,
    transfer_id INTEGER NOT NULL REFERENCES stock_transfers(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    quantity INTEGER NOT NULL,
    received_quantity INTEGER DEFAULT 0,
    notes TEXT
);

-- 2. Kardex / Movimientos Contables de Inventario
CREATE TABLE IF NOT EXISTS inventory_kardex (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    warehouse_id INTEGER REFERENCES warehouses(id),
    movement_type VARCHAR(40) NOT NULL, -- initial, purchase, sale, return, transfer_in, transfer_out, adjustment, scrap
    quantity INTEGER NOT NULL, -- Positivo o negativo según movimiento
    unit_cost DECIMAL(12, 2) DEFAULT 0,
    unit_price DECIMAL(12, 2) DEFAULT 0,
    balance_quantity INTEGER NOT NULL,
    reference_folio VARCHAR(80),
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Historial de Precios y Costos de Productos
CREATE TABLE IF NOT EXISTS product_price_history (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    old_unit_price DECIMAL(12, 2),
    new_unit_price DECIMAL(12, 2) NOT NULL,
    old_net_price DECIMAL(12, 2),
    new_net_price DECIMAL(12, 2),
    old_supplier_cost DECIMAL(12, 2),
    new_supplier_cost DECIMAL(12, 2),
    reason TEXT,
    changed_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Backorders y Pre-órdenes (Gestión de Stock Faltante)
CREATE TABLE IF NOT EXISTS order_backorders (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    requested_qty INTEGER NOT NULL,
    fulfilled_qty INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(30) DEFAULT 'waiting_stock', -- waiting_stock, partially_fulfilled, ready_to_ship, fulfilled, cancelled
    estimated_arrival DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Asegurar columnas de Soft Delete y campos extendidos
DO $$ 
BEGIN
    -- users soft delete
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='users' AND column_name='deleted_at') THEN
        ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL;
    END IF;

    -- products soft delete & tax info
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='products' AND column_name='deleted_at') THEN
        ALTER TABLE products ADD COLUMN deleted_at TIMESTAMP NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='products' AND column_name='sat_code') THEN
        ALTER TABLE products ADD COLUMN sat_code VARCHAR(20) DEFAULT '27111500';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='products' AND column_name='sat_unit') THEN
        ALTER TABLE products ADD COLUMN sat_unit VARCHAR(20) DEFAULT 'H87';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='products' AND column_name='iva_rate') THEN
        ALTER TABLE products ADD COLUMN iva_rate DECIMAL(5,2) DEFAULT 16.00;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='products' AND column_name='ieps_rate') THEN
        ALTER TABLE products ADD COLUMN ieps_rate DECIMAL(5,2) DEFAULT 0.00;
    END IF;

    -- orders soft delete & delivery notes
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='orders' AND column_name='deleted_at') THEN
        ALTER TABLE orders ADD COLUMN deleted_at TIMESTAMP NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='orders' AND column_name='shipping_cost') THEN
        ALTER TABLE orders ADD COLUMN shipping_cost DECIMAL(12,2) DEFAULT 0.00;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='orders' AND column_name='tracking_carrier') THEN
        ALTER TABLE orders ADD COLUMN tracking_carrier VARCHAR(80);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='orders' AND column_name='tracking_number') THEN
        ALTER TABLE orders ADD COLUMN tracking_number VARCHAR(100);
    END IF;

    -- clients soft delete & tax profile
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='clients' AND column_name='deleted_at') THEN
        ALTER TABLE clients ADD COLUMN deleted_at TIMESTAMP NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='clients' AND column_name='tax_regime') THEN
        ALTER TABLE clients ADD COLUMN tax_regime VARCHAR(10);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='clients' AND column_name='tax_zip') THEN
        ALTER TABLE clients ADD COLUMN tax_zip VARCHAR(10);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='clients' AND column_name='cfdi_use') THEN
        ALTER TABLE clients ADD COLUMN cfdi_use VARCHAR(10) DEFAULT 'G01';
    END IF;
END $$;

-- 6. Índices para rendimiento óptimo
CREATE INDEX IF NOT EXISTS idx_warehouse_stock_lookup ON warehouse_stock(warehouse_id, product_id);
CREATE INDEX IF NOT EXISTS idx_kardex_product ON inventory_kardex(product_id);
CREATE INDEX IF NOT EXISTS idx_kardex_created ON inventory_kardex(created_at);
CREATE INDEX IF NOT EXISTS idx_kardex_movement ON inventory_kardex(movement_type);
CREATE INDEX IF NOT EXISTS idx_backorders_order ON order_backorders(order_id);
CREATE INDEX IF NOT EXISTS idx_backorders_product ON order_backorders(product_id);
CREATE INDEX IF NOT EXISTS idx_price_history_product ON product_price_history(product_id);
