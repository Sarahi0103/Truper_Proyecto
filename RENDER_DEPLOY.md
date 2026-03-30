# Deploy en Render (Truper)

## 1) Crear servicio en Render
1. Ir a Render Dashboard.
2. New + > Blueprint.
3. Seleccionar el repo: https://github.com/Sarahi0103/Truper_Proyecto
4. Confirmar el blueprint detectando render.yaml.

## 2) Variables de entorno requeridas
Configura en Render los valores reales de tu base MySQL:
- DB_HOST
- DB_PORT (normalmente 3306)
- DB_USER
- DB_PASS
- DB_NAME

Ya van configuradas por default:
- APP_ENV=production
- APP_DEBUG=false

## 3) Base de datos
Este proyecto usa MySQL. Render no provee MySQL nativo en todos los planes, por lo que puedes usar:
- Aiven MySQL
- PlanetScale
- Railway MySQL
- MySQL en otro proveedor

## 4) Importar esquema
Importa el archivo:
- db/trupper_db.sql

## 5) Verificar despliegue
Cuando termine el build, abre la URL pública de Render y prueba:
- Inicio
- Login
- Registro
- Dashboard

## 6) Solución de problemas rápida
- Si falla conexión DB: revisa DB_HOST, DB_USER, DB_PASS, DB_NAME.
- Si falla login/registro por token: refresca página para regenerar CSRF.
- Si ves error 500: abre logs del servicio en Render.
