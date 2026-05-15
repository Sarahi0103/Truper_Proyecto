@echo off
setlocal

:: Configuración del directorio de backups
set BACKUP_DIR=.\backups

:: Obtener la fecha y hora para el nombre del archivo
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set DATETIME=%%I
set TIMESTAMP=%DATETIME:~0,4%%DATETIME:~4,2%%DATETIME:~6,2%_%DATETIME:~8,2%%DATETIME:~10,2%%DATETIME:~12,2%

:: Crear el directorio si no existe
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

echo ====================================================
echo   Iniciando Backup del Sistema Truper Platform
echo ====================================================
echo.

echo [1/2] Realizando backup de la Base de Datos...
docker exec truper-db pg_dump -U postgres truper_db > "%BACKUP_DIR%\db_backup_%TIMESTAMP%.sql"
if %ERRORLEVEL% equ 0 (
    echo       - BD respaldada con exito.
) else (
    echo       - Error al respaldar la BD. Verifica que el contenedor truper-db este corriendo.
)

echo.
echo [2/2] Realizando backup de las Imagenes (puede tardar unos segundos)...
tar -czf "%BACKUP_DIR%\images_backup_%TIMESTAMP%.tar.gz" public\images\products
if %ERRORLEVEL% equ 0 (
    echo       - Imagenes respaldadas con exito.
) else (
    echo       - Error al respaldar las imagenes.
)

echo.
echo ====================================================
echo   Backup completado! 
echo   Archivos guardados en: %BACKUP_DIR%
echo ====================================================
pause
