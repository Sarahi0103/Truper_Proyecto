-- Correcciones de datos históricos para Tickets
-- Script para corregir datos inconsistentes en el sistema de tickets

-- Agregar índices para rendimiento
CREATE INDEX IF NOT EXISTS idx_tickets_created ON tickets(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_folio ON tickets(folio);

-- Función para corregir folios duplicados
CREATE OR REPLACE FUNCTION fix_duplicate_folios()
RETURNS INTEGER AS $$
DECLARE
    duplicate_count INTEGER;
BEGIN
    -- Identificar folios duplicados
    WITH duplicates AS (
        SELECT folio, COUNT(*) as cnt
        FROM tickets
        WHERE folio IS NOT NULL
        GROUP BY folio
        HAVING COUNT(*) > 1
    )
    SELECT COUNT(*) INTO duplicate_count FROM duplicates;

    -- Corregir folios duplicados agregando sufijo numérico
    WITH duplicates AS (
        SELECT id, folio, ROW_NUMBER() OVER (PARTITION BY folio ORDER BY id) as rn
        FROM tickets
        WHERE folio IN (
            SELECT folio FROM tickets
            WHERE folio IS NOT NULL
            GROUP BY folio
            HAVING COUNT(*) > 1
        )
    )
    UPDATE tickets t
    SET folio = t.folio || '-' || d.rn
    FROM duplicates d
    WHERE t.id = d.id AND d.rn > 1;

    RETURN duplicate_count;
END;
$$ LANGUAGE plpgsql;

-- Función para corregir montos negativos o inconsistentes
CREATE OR REPLACE FUNCTION fix_invalid_amounts()
RETURNS INTEGER AS $$
DECLARE
    fixed_count INTEGER;
BEGIN
    -- Corregir montos negativos (establecer a 0)
    UPDATE tickets
    SET total_amount = ABS(total_amount)
    WHERE total_amount < 0;

    GET DIAGNOSTICS fixed_count = ROW_COUNT;

    -- Corregir montos nulos (establecer a 0)
    UPDATE tickets
    SET total_amount = 0
    WHERE total_amount IS NULL;

    fixed_count := fixed_count + ROW_COUNT;

    RETURN fixed_count;
END;
$$ LANGUAGE plpgsql;

-- Función para corregir fechas futuras
CREATE OR REPLACE FUNCTION fix_future_dates()
RETURNS INTEGER AS $$
DECLARE
    fixed_count INTEGER;
BEGIN
    -- Corregir fechas futuras (establecer a fecha actual)
    UPDATE tickets
    SET issued_date = CURRENT_DATE
    WHERE issued_date > CURRENT_DATE;

    GET DIAGNOSTICS fixed_count = ROW_COUNT;

    RETURN fixed_count;
END;
$$ LANGUAGE plpgsql;

-- Función para normalizar estados de tickets
CREATE OR REPLACE FUNCTION normalize_ticket_statuses()
RETURNS INTEGER AS $$
DECLARE
    fixed_count INTEGER;
BEGIN
    -- Normalizar estados a valores estándar
    UPDATE tickets
    SET status = CASE
        WHEN LOWER(status) IN ('pagado', 'paid', 'completo', 'complete') THEN 'completed'
        WHEN LOWER(status) IN ('pendiente', 'pending', 'espera') THEN 'pending'
        WHEN LOWER(status) IN ('cancelado', 'cancelled', 'anulado') THEN 'cancelled'
        WHEN LOWER(status) IN ('parcial', 'partial') THEN 'partial'
        ELSE 'pending'
    END
    WHERE status NOT IN ('completed', 'pending', 'cancelled', 'partial');

    GET DIAGNOSTICS fixed_count = ROW_COUNT;

    RETURN fixed_count;
END;
$$ LANGUAGE plpgsql;

-- Función para ejecutar todas las correcciones
CREATE OR REPLACE FUNCTION run_all_ticket_corrections()
RETURNS TABLE(
    folio_corrections INTEGER,
    amount_corrections INTEGER,
    date_corrections INTEGER,
    status_corrections INTEGER
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        fix_duplicate_folios() AS folio_corrections,
        fix_invalid_amounts() AS amount_corrections,
        fix_future_dates() AS date_corrections,
        normalize_ticket_statuses() AS status_corrections;
END;
$$ LANGUAGE plpgsql;

-- Comentarios
COMMENT ON FUNCTION fix_duplicate_folios() IS 'Corrige folios duplicados agregando sufijo numérico';
COMMENT ON FUNCTION fix_invalid_amounts() IS 'Corrige montos negativos o nulos';
COMMENT ON FUNCTION fix_future_dates() IS 'Corrige fechas futuras';
COMMENT ON FUNCTION normalize_ticket_statuses() IS 'Normaliza estados de tickets a valores estándar';
COMMENT ON FUNCTION run_all_ticket_corrections() IS 'Ejecuta todas las correcciones de datos históricos de tickets';
