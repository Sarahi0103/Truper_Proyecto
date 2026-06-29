# ==================================
# TRUPER PLATFORM - Database Backup Script (Windows)
# ==================================
# Usage: .\scripts\backup_database.ps1
# Schedule: Use Task Scheduler for automatic backups

# Load environment variables
$envFile = "..\.env"
if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
        if ($_ -match '^([^#].+?)=(.+)$') {
            [Environment]::SetEnvironmentVariable($matches[1], $matches[2])
        }
    }
}

# Configuration
$backupDir = "..\backups"
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupFile = "$backupDir\truper_platform_$timestamp.sql"
$retentionDays = 30

# Create backup directory if it doesn't exist
if (!(Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir | Out-Null
}

# Database connection
$dbHost = $env:DB_HOST ?? "localhost"
$dbPort = $env:DB_PORT ?? "5432"
$dbName = $env:DB_NAME ?? "truper_platform"
$dbUser = $env:DB_USER ?? "truper_admin"
$dbPassword = $env:DB_PASSWORD

Write-Host "========================================"
Write-Host "Starting backup: $timestamp"
Write-Host "Database: $dbName"
Write-Host "========================================"

# Set PGPASSWORD environment variable
$env:PGPASSWORD = $dbPassword

# Perform backup
$backupCommand = "pg_dump -h $dbHost -p $dbPort -U $dbUser -d $dbName"
Invoke-Expression "$backupCommand | Out-File -FilePath $backupFile -Encoding utf8"

if ($LASTEXITCODE -eq 0) {
    # Compress backup
    Compress-Archive -Path $backupFile -DestinationPath "$backupFile.zip" -Force
    Remove-Item $backupFile
    $backupFile = "$backupFile.zip"
    
    Write-Host "✅ Backup completed successfully: $backupFile"
    $fileSize = (Get-Item $backupFile).Length / 1MB
    Write-Host "📊 Size: $([math]::Round($fileSize, 2)) MB"
    
    # Remove old backups (retention policy)
    Get-ChildItem $backupDir -Filter "truper_platform_*.zip" | 
        Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$retentionDays) } | 
        Remove-Item -Force
    Write-Host "🗑️  Old backups older than $retentionDays days removed"
} else {
    Write-Host "❌ Backup failed!"
    exit 1
}

Write-Host "========================================"
Write-Host "Backup process completed"
Write-Host "========================================"
