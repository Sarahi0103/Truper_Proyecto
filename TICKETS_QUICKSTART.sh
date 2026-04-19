#!/bin/bash

#############################################
# 🎟️ GUÍA RÁPIDA - SISTEMA DE TICKETS
#############################################

echo "╔═══════════════════════════════════════╗"
echo "║  SISTEMA DE GESTIÓN DE TICKETS      ║"
echo "║  Guía de Instalación y Primeros Pasos║"
echo "╚═══════════════════════════════════════╝"
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================
# PASO 1: Verificar archivos
# ============================================
echo -e "${BLUE}📋 PASO 1: Verificando archivos necesarios...${NC}"
echo ""

files_to_check=(
    "public/tickets.php:Panel administrativo"
    "public/api/tickets.php:API REST"
    "backend/models/SalesTicket.php:Modelo de datos"
    "public/migrate.php:Migración de BD"
    "TICKETS_SYSTEM.md:Documentación"
)

for file_desc in "${files_to_check[@]}"; do
    file="${file_desc%:*}"
    desc="${file_desc#*:}"
    
    if [ -f "$file" ]; then
        echo -e "${GREEN}✅${NC} $file - $desc"
    else
        echo -e "${RED}❌${NC} $file - $desc (NO ENCONTRADO)"
    fi
done

echo ""
echo -e "${YELLOW}⚠️  Si algún archivo no existe, la instalación fue incompleta.${NC}"
echo ""

# ============================================
# PASO 2: Instrucciones de inicialización
# ============================================
echo -e "${BLUE}🔧 PASO 2: Inicializando base de datos...${NC}"
echo ""
echo "Opción A: Vía navegador web (RECOMENDADO)"
echo "   1. Abre: http://localhost/public/migrate.php"
echo "   2. Si es localhost/admin: Las tablas se crearán automáticamente"
echo "   3. Deberías ver: '✅ Sistema de tickets inicializado correctamente'"
echo ""
echo "Opción B: Vía terminal (Linux/Mac)"
echo "   php public/migrate.php"
echo ""

# ============================================
# PASO 3: Acceder al panel
# ============================================
echo -e "${BLUE}🚀 PASO 3: Acceder al panel de administración${NC}"
echo ""
echo "1. Asegúrate de estar logueado como ADMIN"
echo "2. Ve a: http://localhost/public/tickets.php"
echo "3. Deberías ver:"
echo "   - 📊 Estadísticas (tickets, ventas, devoluciones)"
echo "   - 🔍 Filtros de búsqueda"
echo "   - 📋 Tabla de tickets"
echo "   - ➕ Botón para crear ticket"
echo ""

# ============================================
# PASO 4: Pruebas
# ============================================
echo -e "${BLUE}🧪 PASO 4: Realizar pruebas${NC}"
echo ""
echo "Test 1: Crear Ticket"
echo "   1. Click en '➕ Crear Ticket'"
echo "   2. Llenar datos:"
echo "      - Cliente: 1 (admin user)"
echo "      - Tipo: Venta"
echo "      - Subtotal: 1000"
echo "      - Impuesto: 190"
echo "      - Total: 1190"
echo "   3. Click 'Crear Ticket'"
echo "   4. Deberías ver el folio generado (YYYYMM-XXXXX)"
echo ""

echo "Test 2: Verificar en API"
echo "   curl http://localhost/api/tickets.php?action=list"
echo "   Deberías ver tu ticket creado en JSON"
echo ""

echo "Test 3: Verificar autenticidad (público)"
echo "   curl http://localhost/api/tickets.php?action=verify&folio=XXXXXX-XXXXX"
echo "   Deberías ver datos limitados del ticket"
echo ""

# ============================================
# PASO 5: Características principales
# ============================================
echo -e "${BLUE}📌 PASO 5: Características principales${NC}"
echo ""
echo "✅ Folios únicos secuenciales (YYYYMM-XXXXX)"
echo "✅ Filtros avanzados (folio, tipo, estado, fechas)"
echo "✅ Estadísticas en tiempo real"
echo "✅ Auditoría de cambios"
echo "✅ Exportación a CSV"
echo "✅ Archivamiento automático del mes anterior"
echo "✅ Verificación pública de autenticidad"
echo "✅ Paginación (20 por página)"
echo ""

# ============================================
# PASO 6: Endpoints API
# ============================================
echo -e "${BLUE}🔌 PASO 6: Endpoints API disponibles${NC}"
echo ""
echo "GET  /api/tickets.php?action=list"
echo "   → Listar tickets con filtros"
echo ""
echo "POST /api/tickets.php?action=create"
echo "   → Crear nuevo ticket"
echo ""
echo "GET  /api/tickets.php?action=get-by-folio&folio=XXX"
echo "   → Obtener ticket por folio"
echo ""
echo "GET  /api/tickets.php?action=verify&folio=XXX"
echo "   → Verificar autenticidad (PÚBLICO)"
echo ""
echo "GET  /api/tickets.php?action=get-stats"
echo "   → Obtener estadísticas del mes"
echo ""
echo "POST /api/tickets.php?action=archive-previous-month"
echo "   → Archivar mes anterior"
echo ""

# ============================================
# PASO 7: Troubleshooting
# ============================================
echo -e "${BLUE}❓ PASO 7: Troubleshooting${NC}"
echo ""
echo "Problema: 'Tabla no existe'"
echo "Solución: Ejecutar /public/migrate.php nuevamente"
echo ""
echo "Problema: 'Acceso denegado'"
echo "Solución: Asegurate de estar logueado como ADMIN"
echo ""
echo "Problema: 'Folio duplicado'"
echo "Solución: Verificar tabla ticket_folio_counter"
echo ""
echo "Problema: 'Error de CSRF'"
echo "Solución: Recargar página admin antes de enviar forms"
echo ""

# ============================================
# FIN
# ============================================
echo ""
echo -e "${GREEN}✅ Instalación completa!${NC}"
echo ""
echo "Documentación completa: /TICKETS_SYSTEM.md"
echo "Contacto: DevOps Team"
echo ""
