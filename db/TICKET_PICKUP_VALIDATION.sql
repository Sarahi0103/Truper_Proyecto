-- ============================================
-- SISTEMA DE VALIDACIÓN DE ENTREGA DE TICKETS EN SUCURSAL
-- Migración para agregar funcionalidad de recolección física
-- ============================================

-- 1. Agregar columnas de recolección/entrega a la tabla sales_tickets
ALTER TABLE sales_tickets ADD COLUMN IF NOT EXISTS pickup_status VARCHAR(20) DEFAULT 'pending'
    CHECK (pickup_status IN ('pending', 'picked_up', 'cancelled', 'expired'));
ALTER TABLE sales_tickets ADD COLUMN IF NOT EXISTS pickup_date TIMESTAMP NULL;
ALTER TABLE sales_tickets ADD COLUMN IF NOT EXISTS pickup_verified_by INT REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE sales_tickets ADD COLUMN IF NOT EXISTS pickup_notes TEXT;
ALTER TABLE sales_tickets ADD COLUMN IF NOT EXISTS expiration_date TIMESTAMP NULL;

-- 2. Crear índices de rendimiento en sales_tickets para búsquedas rápidas
CREATE INDEX IF NOT EXISTS idx_tickets_pickup_status ON sales_tickets(pickup_status);
CREATE INDEX IF NOT EXISTS idx_tickets_pickup_date ON sales_tickets(pickup_date);
CREATE INDEX IF NOT EXISTS idx_tickets_expiration ON sales_tickets(expiration_date);

-- 3. Crear la tabla de historial detallado de visitas y validaciones de recolección
CREATE TABLE IF NOT EXISTS ticket_pickup_log (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
    admin_id INT REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(50) NOT NULL, -- 'attempt', 'validated', 'cancelled', 'reactivated'
    notes TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_pickup_log_ticket ON ticket_pickup_log(ticket_id);
CREATE INDEX IF NOT EXISTS idx_pickup_log_date ON ticket_pickup_log(created_at DESC);

-- 4. Crear función para calcular fecha de expiración (30 días)
CREATE OR REPLACE FUNCTION calculate_ticket_expiration()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.expiration_date IS NULL AND NEW.issued_date IS NOT NULL THEN
        NEW.expiration_date = NEW.issued_date + INTERVAL '30 days';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 5. Crear trigger para calcular expiración automáticamente
DROP TRIGGER IF EXISTS trigger_calculate_expiration ON sales_tickets;
CREATE TRIGGER trigger_calculate_expiration
    BEFORE INSERT ON sales_tickets
    FOR EACH ROW
    EXECUTE FUNCTION calculate_ticket_expiration();

-- 6. Actualizar tickets existentes sin fecha de expiración
UPDATE sales_tickets
SET expiration_date = issued_date + INTERVAL '30 days'
WHERE expiration_date IS NULL AND issued_date IS NOT NULL;

-- 7. Crear vista para tickets pendientes de recolección
CREATE OR REPLACE VIEW v_pending_pickup_tickets AS
SELECT
    st.id,
    st.folio,
    st.ticket_type,
    st.total_amount,
    st.payment_status,
    st.issued_date,
    st.expiration_date,
    u.email AS customer_email,
    u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END AS customer_name,
    u.phone,
    COUNT(sti.id) AS item_count
FROM sales_tickets st
LEFT JOIN users u ON st.user_id = u.id
LEFT JOIN ticket_items sti ON st.id = sti.ticket_id
WHERE st.status = 'active'
  AND st.pickup_status = 'pending'
  AND st.payment_status = 'completed'
GROUP BY st.id, u.email, u.first_name, u.last_name, u.phone
ORDER BY st.issued_date DESC;

-- 8. Crear vista para historial de recolecciones por cliente
CREATE OR REPLACE VIEW v_client_pickup_history AS
SELECT
    u.id AS user_id,
    u.email AS customer_email,
    u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END AS customer_name,
    st.folio,
    st.ticket_type,
    st.total_amount,
    st.pickup_status,
    st.pickup_date,
    tpl.action AS last_action,
    tpl.created_at AS action_date,
    tpl.notes AS action_notes,
    admin.first_name || CASE WHEN admin.last_name IS NOT NULL AND admin.last_name <> '' THEN ' ' || admin.last_name ELSE '' END AS admin_name
FROM users u
LEFT JOIN sales_tickets st ON u.id = st.user_id
LEFT JOIN ticket_pickup_log tpl ON st.id = tpl.ticket_id
LEFT JOIN users admin ON tpl.admin_id = admin.id
WHERE st.status = 'active'
ORDER BY tpl.created_at DESC;

-- Comentarios para documentación
COMMENT ON TABLE ticket_pickup_log IS 'Historial detallado de visitas y validaciones de recolección de tickets en sucursal';
COMMENT ON COLUMN sales_tickets.pickup_status IS 'Estado de recolección física: pending, picked_up, cancelled, expired';
COMMENT ON COLUMN sales_tickets.pickup_date IS 'Fecha en que se realizó la recolección física';
COMMENT ON COLUMN sales_tickets.pickup_verified_by IS 'ID del admin que validó la recolección';
COMMENT ON COLUMN sales_tickets.pickup_notes IS 'Notas adicionales sobre la recolección';
COMMENT ON COLUMN sales_tickets.expiration_date IS 'Fecha de expiración del ticket (30 días desde emisión)';
