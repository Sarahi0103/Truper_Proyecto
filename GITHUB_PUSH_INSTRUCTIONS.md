# Instrucciones para Push a GitHub

## Pasos para Conectar con GitHub

### 1. Crear Repositorio en GitHub

Ir a https://github.com/new y crear un repositorio llamado `Trupper_Proyecto`

### 2. Agregar Remote Origin

En PowerShell:
```powershell
cd "c:\Users\ksgom\Trupper_Proyecto\trupper_web"
git remote add origin https://github.com/TU_USERNAME/Trupper_Proyecto.git
```

### 3. Renombrar rama a main (opcional)

```powershell
git branch -M main
```

### 4. Hacer Push

```powershell
git push -u origin main
```

Si requiere autenticaciÃ³n:
- Usar Personal Access Token (recomendado)
- O usar SSH key

## Configurar SSH (Alternativa segura)

1. Generar clave SSH:
```powershell
ssh-keygen -t rsa -b 4096 -f "$env:USERPROFILE\.ssh\id_rsa"
```

2. Agregar a GitHub: Settings > SSH and GPG keys > New SSH key

3. Usar URL SSH:
```powershell
git remote set-url origin git@github.com:TU_USERNAME/Trupper_Proyecto.git
```

## Comandos Ãštiles DespuÃ©s

```powershell
# Ver commit history
git log --oneline

# Ver estado
git status

# Hacer cambios despuÃ©s
git add .
git commit -m "mensaje"
git push

# Crear rama de desarrollo
git checkout -b develop
git push -u origin develop
```

## Commit Actual

El commit inicial ha sido realizado localmente:
- Autor: Truper Development
- Email: info@truper.com
- Mensaje: Truper v1.0.0: Sistema completo de gestiÃ³n de inventario y ventas
- 42 archivos agregados
- 4658 lÃ­neas de cÃ³digo

---

**Nota**: Reemplazar `TU_USERNAME` con tu username de GitHub



