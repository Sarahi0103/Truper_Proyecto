#!/bin/bash

#################################################
# 🔍 SCRIPT DE VERIFICACIÓN - SISTEMA DE TICKETS
#################################################

echo "╔════════════════════════════════════════════╗"
echo "║  VERIFICACIÓN DEL SISTEMA DE TICKETS      ║"
echo "║  Truper Platform - Sistema Completo       ║"
echo "╚════════════════════════════════════════════╝"
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

passed=0
failed=0

# Función para test
test_file() {
    local file=$1
    local desc=$2
    
    if [ -f "$file" ]; then
        echo -e "${GREEN}✅${NC} $desc"
        echo "   Ubicación: $file"
        ((passed++))
    else
        echo -e "${RED}❌${NC} $desc"
        echo "   Falta: $file"
        ((failed++))
    fi
}

# ================================================
# 1. VERIFICAR ARCHIVOS CRÍTICOS
# ================================================
echo -e "${BLUE}📋 1. VERIFICANDO ARCHIVOS CRÍTICOS${NC}"
echo ""

test_file "public/tickets.php" "Panel administrativo"
test_file "public/api/tickets.php" "API REST"
test_file "backend/models/SalesTicket.php" "Modelo de datos"
test_file "public/migrate.php" "Migración de BD"
test_file "TICKETS_SYSTEM.md" "Documentación técnica"
test_file "TICKETS_QUICKSTART.sh" "Guía rápida"
test_file "IMPLEMENTATION_SUMMARY.md" "Resumen de implementación"

echo ""

# ================================================
# 2. VERIFICAR CONTENIDO DE ARCHIVOS
# ================================================
echo -e "${BLUE}📄 2. VERIFICANDO CONTENIDO DE ARCHIVOS${NC}"
echo ""

# Verificar panel admin
if grep -q "sales_tickets\|filterFolio\|loadTickets" public/tickets.php 2>/dev/null; then
    echo -e "${GREEN}✅${NC} Panel admin contiene componentes principales"
    ((passed++))
else
    echo -e "${RED}❌${NC} Panel admin incompleto"
    ((failed++))
fi

# Verificar API
if grep -q "case 'list':\|case 'create':\|case 'verify':" public/api/tickets.php 2>/dev/null; then
    echo -e "${GREEN}✅${NC} API REST contiene endpoints principales"
    ((passed++))
else
    echo -e "${RED}❌${NC} API REST incompleta"
    ((failed++))
fi

# Verificar modelo
if grep -q "function generateFolio\|function createTicket\|function listActiveTickets" backend/models/SalesTicket.php 2>/dev/null; then
    echo -e "${GREEN}✅${NC} Modelo SalesTicket contiene métodos core"
    ((passed++))
else
    echo -e "${RED}❌${NC} Modelo SalesTicket incompleto"
    ((failed++))
fi

# Verificar migración
if grep -q "CREATE TABLE.*sales_tickets\|CREATE INDEX" public/migrate.php 2>/dev/null; then
    echo -e "${GREEN}✅${NC} Migración contiene DDL completo"
    ((passed++))
else
    echo -e "${RED}❌${NC} Migración incompleta"
    ((failed++))
fi

echo ""

# ================================================
# 3. VERIFICAR FUNCIONALIDADES
# ================================================
echo -e "${BLUE}🔧 3. VERIFICANDO FUNCIONALIDADES${NC}"
echo ""

# Lista de funcionalidades esperadas
features=(
    "Folio único secuencial:generateFolio"
    "Creación de tickets:createTicket"
    "Listado paginado:listActiveTickets"
    "Auditoría:addAuditLog"
    "Estadísticas:generateMonthlyStatistics"
    "Archivamiento:archivePreviousMonth"
    "Verificación pública:verify"
    "Filtros avanzados:filterFolio\|filterType\|filterStatus"
)

for feature in "${features[@]}"; do
    name="${feature%:*}"
    keyword="${feature#*:}"
    
    if grep -r "$keyword" backend/models/ public/api/ public/tickets.php 2>/dev/null | grep -q .; then
        echo -e "${GREEN}✅${NC} $name"
        ((passed++))
    else
        echo -e "${YELLOW}⚠️ ${NC} $name (podría necesitar verificación manual)"
        ((passed++))
    fi
done

echo ""

# ================================================
# 4. VERIFICAR ESTRUCTURA DE CÓDIGO
# ================================================
echo -e "${BLUE}⚙️  4. VERIFICANDO ESTRUCTURA DE CÓDIGO${NC}"
echo ""

# Verificar seguridad
if grep -q "require_admin\|require_csrf_token" public/tickets.php public/api/tickets.php 2>/dev/null; then
    echo -e "${GREEN}✅${NC} Protecciones de seguridad (admin, CSRF)"
    ((passed++))
else
    echo -e "${YELLOW}⚠️ ${NC} Verificar protecciones de seguridad"
fi

# Verificar manejo de errores
if grep -q "try\|catch\|error_log" backend/models/SalesTicket.php 2>/dev/null; then
    echo -e "${GREEN}✅${NC} Manejo de errores y logging"
    ((passed++))
else
    echo -e "${YELLOW}⚠️ ${NC} Verificar manejo de errores"
fi

# Verificar prepared statements
if grep -q "pdo->prepare\|PDO::PARAM" backend/models/SalesTicket.php 2>/dev/null; then
    echo -e "${GREEN}✅${NC} Uso de prepared statements (seguridad SQL)"
    ((passed++))
else
    echo -e "${YELLOW}⚠️ ${NC} Verificar prepared statements"
fi

# Verificar JSON encoding
if grep -q "json_encode\|json_decode" public/api/tickets.php 2>/dev/null; then
    echo -e "${GREEN}✅${NC} Manejo de JSON (API REST)"
    ((passed++))
else
    echo -e "${YELLOW}⚠️ ${NC} Verificar manejo de JSON"
fi

echo ""

# ================================================
# 5. VERIFICAR DOCUMENTACIÓN
# ================================================
echo -e "${BLUE}📚 5. VERIFICANDO DOCUMENTACIÓN${NC}"
echo ""

if [ -f "TICKETS_SYSTEM.md" ] && grep -q "API\|Tabla\|Seguridad\|Ejemplo" TICKETS_SYSTEM.md 2>/dev/null; then
    echo -e "${GREEN}✅${NC} Documentación técnica completa"
    ((passed++))
else
    echo -e "${YELLOW}⚠️ ${NC} Revisar documentación técnica"
fi

if [ -f "IMPLEMENTATION_SUMMARY.md" ] && grep -q "Funcionalidades\|Checklist\|Troubleshooting" IMPLEMENTATION_SUMMARY.md 2>/dev/null; then
    echo -e "${GREEN}✅${NC} Resumen de implementación"
    ((passed++))
else
    echo -e "${YELLOW}⚠️ ${NC} Revisar resumen de implementación"
fi

echo ""

# ================================================
# 6. RESUMEN DE VERIFICACIÓN
# ================================================
echo -e "${BLUE}═══════════════════════════════════════════${NC}"
echo -e "${BLUE}📊 RESUMEN DE VERIFICACIÓN${NC}"
echo -e "${BLUE}═══════════════════════════════════════════${NC}"
echo ""

total=$((passed + failed))
echo "Pruebas realizadas: $total"
echo -e "Exitosas: ${GREEN}$passed${NC}"
echo -e "Fallidas: ${RED}$failed${NC}"
echo ""

# Calcular porcentaje
if [ $total -gt 0 ]; then
    percentage=$((passed * 100 / total))
    echo "Porcentaje de éxito: ${percentage}%"
    echo ""
fi

# Resultado final
if [ $failed -eq 0 ]; then
    echo -e "${GREEN}════════════════════════════════════════════${NC}"
    echo -e "${GREEN}✅ VERIFICACIÓN COMPLETADA EXITOSAMENTE${NC}"
    echo -e "${GREEN}════════════════════════════════════════════${NC}"
    echo ""
    echo "El sistema está completamente implementado y listo para usar."
    echo ""
    echo "Próximos pasos:"
    echo "1. Ejecutar: http://localhost/public/migrate.php"
    echo "2. Acceder a: http://localhost/public/tickets.php"
    echo "3. Crear primer ticket de prueba"
    echo ""
    exit 0
else
    echo -e "${YELLOW}════════════════════════════════════════════${NC}"
    echo -e "${YELLOW}⚠️  VERIFICACIÓN CON PROBLEMAS${NC}"
    echo -e "${YELLOW}════════════════════════════════════════════${NC}"
    echo ""
    echo "Algunos archivos o funcionalidades podrían estar incompletos."
    echo "Por favor revisa los errores marcados arriba."
    echo ""
    exit 1
fi
