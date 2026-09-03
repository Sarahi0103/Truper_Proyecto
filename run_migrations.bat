@echo off
REM Script para ejecutar migraciones de base de datos usando psql
REM Uso: run_migrations.bat

echo === Ejecutando Migraciones de Base de Datos ===
echo.

REM Configuración de conexión (modificar según tu configuración)
set DB_HOST=localhost
set DB_PORT=5432
set DB_NAME=truper_db
set DB_USER=postgres
set DB_PASSWORD=your_password_here

REM Establecer variable de entorno PGPASSWORD
set PGPASSWORD=%DB_PASSWORD%

REM Lista de migraciones a ejecutar
set MIGRATIONS[0]=add_tax_fields_to_products.sql
set MIGRATIONS[1]=add_stock_reservations.sql
set MIGRATIONS[2]=add_payment_complements_table.sql
set MIGRATIONS[3]=add_coupons_system.sql
set MIGRATIONS[4]=add_user_addresses.sql
set MIGRATIONS[5]=add_shipping_tracking.sql
set MIGRATIONS[6]=add_online_inventory.sql
set MIGRATIONS[7]=add_product_reviews.sql

REM Ejecutar cada migración
for %%f in (database_migrations\*.sql) do (
    echo Ejecutando: %%f
    psql -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -f "%%f"
    if errorlevel 1 (
        echo ERROR en %%f
    ) else (
        echo OK
    )
    echo.
)

echo === Migraciones Completadas ===
pause
