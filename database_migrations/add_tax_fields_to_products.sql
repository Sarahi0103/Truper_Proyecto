-- Migration: Add tax fields to products table
-- Purpose: Support tax calculation for SAT invoicing
-- Date: 2026-08-18

-- Add tax-related columns to products table
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS is_tax_exempt BOOLEAN DEFAULT false,
ADD COLUMN IF NOT EXISTS tax_rate DECIMAL(5,2) DEFAULT 16.00,
ADD COLUMN IF NOT EXISTS tax_type VARCHAR(50) DEFAULT 'IVA';

-- Add index for tax-related queries
CREATE INDEX IF NOT EXISTS idx_products_tax_exempt ON products(is_tax_exempt);

-- Update existing products to have default tax rate
UPDATE products SET tax_rate = 16.00 WHERE tax_rate IS NULL;

-- Add comment
COMMENT ON COLUMN products.is_tax_exempt IS 'Indica si el producto está exento de IVA';
COMMENT ON COLUMN products.tax_rate IS 'Tasa de impuesto aplicable (ej. 16.00 para IVA, 0.00 para exento)';
COMMENT ON COLUMN products.tax_type IS 'Tipo de impuesto (IVA, IEPS, etc.)';

-- Add columns to orders table for tax breakdown
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS subtotal_amount DECIMAL(12,2),
ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(12,2),
ADD COLUMN IF NOT EXISTS tax_rate_applied DECIMAL(5,2) DEFAULT 16.00;

-- Add columns to order_items table for tax breakdown
ALTER TABLE order_items
ADD COLUMN IF NOT EXISTS tax_rate DECIMAL(5,2) DEFAULT 16.00,
ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(12,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS line_total_with_tax DECIMAL(12,2);

-- Create table for global invoices
CREATE TABLE IF NOT EXISTS global_invoices (
    id SERIAL PRIMARY KEY,
    invoice_uuid VARCHAR(100) UNIQUE NOT NULL,
    invoice_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    sales_count INTEGER NOT NULL,
    xml_url TEXT,
    pdf_url TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Add index for global invoices
CREATE INDEX IF NOT EXISTS idx_global_invoices_date ON global_invoices(invoice_date);

-- Add foreign key column to orders for global invoice reference
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS global_invoice_id INTEGER REFERENCES global_invoices(id);

-- Add trigger for updated_at on global_invoices
CREATE OR REPLACE FUNCTION update_global_invoices_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_update_global_invoices_updated_at ON global_invoices;
CREATE TRIGGER trigger_update_global_invoices_updated_at
BEFORE UPDATE ON global_invoices
FOR EACH ROW
EXECUTE FUNCTION update_global_invoices_updated_at();
