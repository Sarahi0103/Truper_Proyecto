-- Data Warehouse y Materialized Views para Estadísticas
-- Mejoras de rendimiento para consultas analíticas

-- Tabla de Data Warehouse para métricas agregadas
CREATE TABLE IF NOT EXISTS analytics_dw (
    id SERIAL PRIMARY KEY,
    metric_date DATE NOT NULL,
    metric_type VARCHAR(50) NOT NULL, -- daily, weekly, monthly
    year SMALLINT NOT NULL,
    month SMALLINT NOT NULL,
    day SMALLINT,
    total_orders INTEGER DEFAULT 0,
    total_amount DECIMAL(15, 2) DEFAULT 0,
    total_items INTEGER DEFAULT 0,
    unique_customers INTEGER DEFAULT 0,
    avg_order_value DECIMAL(10, 2) DEFAULT 0,
    category_breakdown JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (metric_date, metric_type)
);

-- Índices para Data Warehouse
CREATE INDEX IF NOT EXISTS idx_analytics_dw_date ON analytics_dw(metric_date);
CREATE INDEX IF NOT EXISTS idx_analytics_dw_type ON analytics_dw(metric_type);
CREATE INDEX IF NOT EXISTS idx_analytics_dw_year_month ON analytics_dw(year, month);
CREATE INDEX IF NOT EXISTS idx_analytics_dw_created ON analytics_dw(created_at);

-- Vista materializada para métricas mensuales
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_monthly_metrics AS
SELECT 
    DATE_TRUNC('month', created_at)::DATE AS metric_date,
    EXTRACT(YEAR FROM created_at)::SMALLINT AS year,
    EXTRACT(MONTH FROM created_at)::SMALLINT AS month,
    COUNT(DISTINCT id) AS total_orders,
    COALESCE(SUM(total_amount), 0) AS total_amount,
    COALESCE(SUM(
        (SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.order_id = orders.id)
    ), 0) AS total_items,
    COUNT(DISTINCT client_id) AS unique_customers,
    COALESCE(AVG(total_amount), 0) AS avg_order_value
FROM orders
WHERE created_at IS NOT NULL
GROUP BY DATE_TRUNC('month', created_at), EXTRACT(YEAR FROM created_at), EXTRACT(MONTH FROM created_at)
ORDER BY metric_date DESC;

-- Índice para vista materializada
CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_monthly_metrics_date ON mv_monthly_metrics(metric_date);

-- Vista materializada para métricas por categoría
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_category_metrics AS
SELECT 
    COALESCE(p.category, 'General') AS category,
    COUNT(DISTINCT o.id) AS total_orders,
    SUM(oi.quantity) AS total_quantity,
    SUM(oi.line_total) AS total_amount,
    AVG(oi.line_total) AS avg_line_total,
    COUNT(DISTINCT o.client_id) AS unique_customers
FROM order_items oi
JOIN products p ON oi.product_id = p.id
JOIN orders o ON oi.order_id = o.id
WHERE o.created_at IS NOT NULL
GROUP BY COALESCE(p.category, 'General')
ORDER BY total_amount DESC;

-- Índice para vista materializada de categorías
CREATE INDEX IF NOT EXISTS idx_mv_category_metrics_category ON mv_category_metrics(category);

-- Función para refrescar vistas materializadas
CREATE OR REPLACE FUNCTION refresh_analytics_views()
RETURNS void AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_monthly_metrics;
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_category_metrics;
END;
$$ LANGUAGE plpgsql;

-- Comentarios
COMMENT ON TABLE analytics_dw IS 'Data Warehouse para métricas agregadas de estadísticas';
COMMENT ON MATERIALIZED VIEW mv_monthly_metrics IS 'Vista materializada de métricas mensuales para rendimiento optimizado';
COMMENT ON MATERIALIZED VIEW mv_category_metrics IS 'Vista materializada de métricas por categoría para rendimiento optimizado';
COMMENT ON FUNCTION refresh_analytics_views() IS 'Función para refrescar vistas materializadas de analytics';
