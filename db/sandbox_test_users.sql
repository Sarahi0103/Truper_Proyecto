-- ============================================================
-- SANDBOX TEST USERS -- Solo para el simulador de facturacion
-- Identificados con email @sandbox.test y role='sandbox'
-- NO pueden hacer login (password_hash invalido intencional)
-- ============================================================

-- Usuario 1: Persona Fisica con CFDI (comprador online con factura)
INSERT INTO users (email, password_hash, first_name, last_name, role,
    phone, is_active, is_verified, rfc, tax_name, tax_regime,
    zip_code_fiscal, customer_segment, user_code, created_at, updated_at)
VALUES (
    'juan.garcia@sandbox.test',
    '$2y$10$SANDBOX_HASH_NOLOGIN_1xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'Juan', 'Garcia Perez', 'sandbox',
    '3312345601', true, true,
    'GAPJ800101HXX', 'JUAN GARCIA PEREZ', '616',
    '44100', 'sandbox', 'SBX-USR-001',
    NOW(), NOW()
) ON CONFLICT (email) DO NOTHING;

-- Usuario 2: Persona Moral / Empresa B2B
INSERT INTO users (email, password_hash, first_name, last_name, role,
    phone, is_active, is_verified, rfc, tax_name, tax_regime,
    zip_code_fiscal, customer_segment, user_code, created_at, updated_at)
VALUES (
    'constructora@sandbox.test',
    '$2y$10$SANDBOX_HASH_NOLOGIN_2xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'Constructora', 'ABC SA de CV', 'sandbox',
    '3312345602', true, true,
    'CAB920315GH0', 'CONSTRUCTORA ABC SA DE CV', '601',
    '45040', 'sandbox', 'SBX-USR-002',
    NOW(), NOW()
) ON CONFLICT (email) DO NOTHING;

-- Usuario 3: Publico General (sin factura, mostrador)
INSERT INTO users (email, password_hash, first_name, last_name, role,
    phone, is_active, is_verified, rfc, tax_name, tax_regime,
    zip_code_fiscal, customer_segment, user_code, created_at, updated_at)
VALUES (
    'publico.general@sandbox.test',
    '$2y$10$SANDBOX_HASH_NOLOGIN_3xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'Publico', 'General', 'sandbox',
    '3312345603', true, true,
    'XAXX010101000', 'PUBLICO EN GENERAL', '616',
    '00000', 'sandbox', 'SBX-USR-003',
    NOW(), NOW()
) ON CONFLICT (email) DO NOTHING;

-- Usuario 4: RESICO (Regimen Simplificado de Confianza)
INSERT INTO users (email, password_hash, first_name, last_name, role,
    phone, is_active, is_verified, rfc, tax_name, tax_regime,
    zip_code_fiscal, customer_segment, user_code, created_at, updated_at)
VALUES (
    'resico.maria@sandbox.test',
    '$2y$10$SANDBOX_HASH_NOLOGIN_4xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'Maria', 'Lopez Ramirez', 'sandbox',
    '3312345604', true, true,
    'LORM850220MXX', 'MARIA LOPEZ RAMIREZ', '626',
    '44200', 'sandbox', 'SBX-USR-004',
    NOW(), NOW()
) ON CONFLICT (email) DO NOTHING;

-- Usuario 5: Mayorista / Distribuidora
INSERT INTO users (email, password_hash, first_name, last_name, role,
    phone, is_active, is_verified, rfc, tax_name, tax_regime,
    zip_code_fiscal, customer_segment, user_code, created_at, updated_at)
VALUES (
    'mayorista@sandbox.test',
    '$2y$10$SANDBOX_HASH_NOLOGIN_5xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'Distribuidora', 'Norte SRL', 'sandbox',
    '3312345605', true, true,
    'DNO991010FH5', 'DISTRIBUIDORA NORTE S DE RL DE CV', '601',
    '64000', 'sandbox', 'SBX-USR-005',
    NOW(), NOW()
) ON CONFLICT (email) DO NOTHING;

-- ============================================================
-- Crear entradas en tabla clients para cada usuario sandbox
-- ============================================================
INSERT INTO clients (user_id, company_name, rfc, is_wholesale, credit_limit, credit_available, created_at, updated_at)
SELECT u.id, u.last_name, u.rfc, false, 0, 0, NOW(), NOW()
FROM users u
WHERE u.role = 'sandbox'
  AND NOT EXISTS (SELECT 1 FROM clients c WHERE c.user_id = u.id)
ON CONFLICT DO NOTHING;

-- Verificar resultado
SELECT u.id as user_id, u.email, u.first_name, u.last_name, u.rfc, u.tax_regime, u.user_code, c.id as client_id
FROM users u
LEFT JOIN clients c ON c.user_id = u.id
WHERE u.role = 'sandbox'
ORDER BY u.id;
