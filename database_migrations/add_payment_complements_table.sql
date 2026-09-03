-- Migration: Add payment_complements table
-- Purpose: Support CFDI 4.0 payment complements for partial payments
-- Date: 2026-08-18

-- Create table for payment complements
CREATE TABLE IF NOT EXISTS payment_complements (
    id SERIAL PRIMARY KEY,
    original_invoice_uuid VARCHAR(100) NOT NULL,
    complement_uuid VARCHAR(100) UNIQUE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_date TIMESTAMP NOT NULL,
    xml_url TEXT,
    pdf_url TEXT,
    order_id INTEGER REFERENCES orders(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Add index for queries
CREATE INDEX IF NOT EXISTS idx_payment_complements_original ON payment_complements(original_invoice_uuid);
CREATE INDEX IF NOT EXISTS idx_payment_complements_order ON payment_complements(order_id);

-- Add foreign key to orders if not exists
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS first_invoice_uuid VARCHAR(100);

-- Add trigger for updated_at
CREATE OR REPLACE FUNCTION update_payment_complements_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_update_payment_complements_updated_at ON payment_complements;
CREATE TRIGGER trigger_update_payment_complements_updated_at
BEFORE UPDATE ON payment_complements
FOR EACH ROW
EXECUTE FUNCTION update_payment_complements_updated_at();
