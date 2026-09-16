-- Allow 'failed' and 'refunded' in chk_orders_payment_status
ALTER TABLE orders DROP CONSTRAINT IF EXISTS chk_orders_payment_status;
ALTER TABLE orders ADD CONSTRAINT chk_orders_payment_status 
    CHECK (payment_status IN ('pending', 'partial', 'paid', 'failed', 'refunded'));
