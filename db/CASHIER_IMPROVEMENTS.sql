-- Mejoras para el sistema de Caja
-- Agregar índices, campos de auditoría y funcionalidad de conciliación

-- Agregar índices compuestos para rendimiento
CREATE INDEX IF NOT EXISTS idx_cash_drawer_sessions_user_opened ON cash_drawer_sessions(opened_by, opened_at);
CREATE INDEX IF NOT EXISTS idx_cash_drawer_sessions_status ON cash_drawer_sessions(status);
CREATE INDEX IF NOT EXISTS idx_cash_drawer_movements_session_created ON cash_drawer_movements(session_id, created_at);
CREATE INDEX IF NOT EXISTS idx_cash_drawer_movements_type ON cash_drawer_movements(movement_type);

-- Agregar campos de auditoría a cash_drawer_sessions
ALTER TABLE cash_drawer_sessions ADD COLUMN IF NOT EXISTS ip_address INET;
ALTER TABLE cash_drawer_sessions ADD COLUMN IF NOT EXISTS user_agent TEXT;
ALTER TABLE cash_drawer_sessions ADD COLUMN IF NOT EXISTS discrepancy_notified BOOLEAN DEFAULT false;
ALTER TABLE cash_drawer_sessions ADD COLUMN IF NOT EXISTS ticket_count INTEGER DEFAULT 0;
ALTER TABLE cash_drawer_sessions ADD COLUMN IF NOT EXISTS total_sales DECIMAL(12,2) DEFAULT 0;

-- Agregar campos de auditoría a cash_drawer_movements
ALTER TABLE cash_drawer_movements ADD COLUMN IF NOT EXISTS ip_address INET;
ALTER TABLE cash_drawer_movements ADD COLUMN IF NOT EXISTS user_agent TEXT;
ALTER TABLE cash_drawer_movements ADD COLUMN IF NOT EXISTS ticket_id INTEGER; -- Vincular con tickets de venta

-- Crear tabla para logs de auditoría de caja
CREATE TABLE IF NOT EXISTS cash_drawer_audit_log (
    id SERIAL PRIMARY KEY,
    session_id INTEGER REFERENCES cash_drawer_sessions(id) ON DELETE CASCADE,
    action VARCHAR(50) NOT NULL, -- open, close, movement, reconcile
    user_id INTEGER NOT NULL REFERENCES users(id),
    ip_address INET,
    user_agent TEXT,
    old_values JSONB,
    new_values JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_cash_drawer_audit_log_session ON cash_drawer_audit_log(session_id);
CREATE INDEX IF NOT EXISTS idx_cash_drawer_audit_log_user ON cash_drawer_audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_cash_drawer_audit_log_action ON cash_drawer_audit_log(action);
CREATE INDEX IF NOT EXISTS idx_cash_drawer_audit_log_created ON cash_drawer_audit_log(created_at);

-- Función para conciliación automática de caja
DROP FUNCTION IF EXISTS reconcile_cash_drawer(INTEGER);
CREATE OR REPLACE FUNCTION reconcile_cash_drawer(p_session_id INTEGER)
RETURNS TABLE(
    expected_amount DECIMAL(12,2),
    actual_amount DECIMAL(12,2),
    difference DECIMAL(12,2),
    has_discrepancy BOOLEAN,
    discrepancy_amount DECIMAL(12,2)
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        s.opening_amount + COALESCE(SUM(CASE WHEN m.movement_type = 'in' THEN m.amount ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN m.movement_type = 'out' THEN m.amount ELSE 0 END), 0) +
        COALESCE(s.total_sales, 0) AS expected_amount,
        COALESCE(s.closing_amount, s.opening_amount) AS actual_amount,
        (s.opening_amount + COALESCE(SUM(CASE WHEN m.movement_type = 'in' THEN m.amount ELSE 0 END), 0) -
         COALESCE(SUM(CASE WHEN m.movement_type = 'out' THEN m.amount ELSE 0 END), 0) +
         COALESCE(s.total_sales, 0)) - COALESCE(s.closing_amount, s.opening_amount) AS difference,
        ABS((s.opening_amount + COALESCE(SUM(CASE WHEN m.movement_type = 'in' THEN m.amount ELSE 0 END), 0) -
             COALESCE(SUM(CASE WHEN m.movement_type = 'out' THEN m.amount ELSE 0 END), 0) +
             COALESCE(s.total_sales, 0)) - COALESCE(s.closing_amount, s.opening_amount)) > 100 AS has_discrepancy,
        ABS((s.opening_amount + COALESCE(SUM(CASE WHEN m.movement_type = 'in' THEN m.amount ELSE 0 END), 0) -
             COALESCE(SUM(CASE WHEN m.movement_type = 'out' THEN m.amount ELSE 0 END), 0) +
             COALESCE(s.total_sales, 0)) - COALESCE(s.closing_amount, s.opening_amount)) AS discrepancy_amount
    FROM cash_drawer_sessions s
    LEFT JOIN cash_drawer_movements m ON m.session_id = s.id
    WHERE s.id = p_session_id
    GROUP BY s.id, s.opening_amount, s.closing_amount, s.total_sales;
END;
$$ LANGUAGE plpgsql;

-- Comentarios
COMMENT ON FUNCTION reconcile_cash_drawer(INTEGER) IS 'Función para conciliación automática de cajón de dinero';
COMMENT ON COLUMN cash_drawer_sessions.discrepancy_notified IS 'Indica si ya se notificó sobre discrepancia en este turno';
COMMENT ON COLUMN cash_drawer_sessions.ticket_count IS 'Número de tickets procesados en el turno';
COMMENT ON COLUMN cash_drawer_sessions.total_sales IS 'Total de ventas del turno';
COMMENT ON COLUMN cash_drawer_movements.ticket_id IS 'ID del ticket de venta vinculado al movimiento';
