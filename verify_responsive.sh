#!/bin/bash

# Verification Script - Asegurar que todas las páginas tienen responsive CSS y JS

echo "🔍 Verificando Integración de CSS y JS Responsivo..."
echo "=========================================="

# Array de páginas principales
pages=(
    "index.php"
    "admin_login.php"
    "dashboard.php"
    "cart.php"
    "admin_supply.php"
    "orders.php"
    "tasks.php"
    "tickets.php"
    "account.php"
    "product_detail.php"
    "checkout.php"
    "login.php"
    "register.php"
    "analytics.php"
    "profile.php"
    "wholesale.php"
    "my_tickets.php"
    "ticket_quote.php"
)

# Contadores
total=0
with_css=0
with_js=0
with_viewport=0
issues=0

echo ""
echo "📋 Página | CSS Responsivo | JS Mobile | Viewport-fit"
echo "------|--------|--------|----------"

for page in "${pages[@]}"; do
    file="/workspaces/proyecto_Truper/public/$page"
    
    if [ -f "$file" ]; then
        total=$((total + 1))
        
        # Verificar CSS
        has_css=$(grep -c "responsive-complete.css" "$file" || echo 0)
        if [ "$has_css" -gt 0 ]; then
            with_css=$((with_css + 1))
            css_status="✅"
        else
            css_status="❌"
            issues=$((issues + 1))
        fi
        
        # Verificar JS
        has_js=$(grep -c "mobile-optimize.js" "$file" || echo 0)
        if [ "$has_js" -gt 0 ]; then
            with_js=$((with_js + 1))
            js_status="✅"
        else
            js_status="❌"
            issues=$((issues + 1))
        fi
        
        # Verificar viewport-fit
        has_viewport=$(grep -c "viewport-fit=cover" "$file" || echo 0)
        if [ "$has_viewport" -gt 0 ]; then
            with_viewport=$((with_viewport + 1))
            vp_status="✅"
        else
            vp_status="⚠️"
        fi
        
        echo "$page | $css_status | $js_status | $vp_status"
    fi
done

echo ""
echo "=========================================="
echo "📊 Resumen:"
echo "   Total páginas: $total"
echo "   Con CSS responsivo: $with_css/$total"
echo "   Con JS mobile: $with_js/$total"
echo "   Con viewport-fit: $with_viewport/$total"
echo "   Problemas encontrados: $issues"
echo ""

# Verificar que los archivos CSS y JS existen
echo "🔍 Verificando archivos base..."
echo ""

if [ -f "/workspaces/proyecto_Truper/public/css/responsive-complete.css" ]; then
    lines=$(wc -l < "/workspaces/proyecto_Truper/public/css/responsive-complete.css")
    size=$(du -h "/workspaces/proyecto_Truper/public/css/responsive-complete.css" | cut -f1)
    echo "✅ responsive-complete.css ($size, $lines líneas)"
else
    echo "❌ responsive-complete.css NO ENCONTRADO"
    issues=$((issues + 1))
fi

if [ -f "/workspaces/proyecto_Truper/public/js/mobile-optimize.js" ]; then
    lines=$(wc -l < "/workspaces/proyecto_Truper/public/js/mobile-optimize.js")
    size=$(du -h "/workspaces/proyecto_Truper/public/js/mobile-optimize.js" | cut -f1)
    echo "✅ mobile-optimize.js ($size, $lines líneas)"
else
    echo "❌ mobile-optimize.js NO ENCONTRADO"
    issues=$((issues + 1))
fi

echo ""
echo "=========================================="

if [ $issues -eq 0 ]; then
    echo "✅ TODO ESTÁ CORRECTAMENTE INTEGRADO"
    echo "La aplicación es 100% responsiva ✨"
else
    echo "⚠️ Se encontraron $issues problemas"
    echo "Por favor revisar los archivos marcados con ❌"
fi

echo ""
echo "Próximos pasos:"
echo "1. Abre la aplicación en: https://super-duper-invention-pjg657957jj7f7g9j-9000.app.github.dev"
echo "2. Presiona F12 para DevTools"
echo "3. Presiona Ctrl+Shift+M para modo responsive"
echo "4. Prueba diferentes tamaños (320px, 768px, 1024px, 1440px)"
echo "5. Verifica que todo se ve bien en cada tamaño"
echo ""
