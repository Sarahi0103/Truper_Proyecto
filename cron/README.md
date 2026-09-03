# Cron Jobs - Truper Platform

## Configuración

Los cron jobs deben configurarse en el servidor para ejecutarse automáticamente.

## Factura Global Diaria

**Archivo:** `cron/daily_global_invoice.php`

**Propósito:** Genera factura global para ventas del día anterior que no tienen factura individual (requisito SAT).

**Frecuencia recomendada:** Diariamente a las 1:00 AM

**Ejemplo de configuración en crontab:**
```bash
0 1 * * * /usr/bin/php /var/www/proyecto_Truper/cron/daily_global_invoice.php
```

**En Windows (Task Scheduler):**
- Crear tarea programada
- Ejecutar: `php C:\Users\ksgom\proyecto_Truper\cron\daily_global_invoice.php`
- Frecuencia: Diariamente a las 1:00 AM

## Logs

Los logs se guardan en el sistema de logging de la aplicación (`AppLogger`).

## Pruebas

Para probar manualmente:
```bash
php cron/daily_global_invoice.php
```
