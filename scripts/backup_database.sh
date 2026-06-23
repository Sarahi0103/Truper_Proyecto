#!/bin/bash
# ==================================
# TRUPER PLATFORM - Database Backup Script
# ==================================
# Usage: ./scripts/backup_database.sh
# Schedule: Add to crontab for automatic backups
# Example crontab: 0 2 * * * /path/to/scripts/backup_database.sh

# Load environment variables
if [ -f ../.env ]; then
    export $(cat ../.env | grep -v '^#' | xargs)
fi

# Configuration
BACKUP_DIR="../backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/truper_platform_$TIMESTAMP.sql"
RETENTION_DAYS=30

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Database connection
DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-5432}
DB_NAME=${DB_NAME:-truper_platform}
DB_USER=${DB_USER:-truper_admin}
DB_PASSWORD=${DB_PASSWORD}

echo "========================================"
echo "Starting backup: $TIMESTAMP"
echo "Database: $DB_NAME"
echo "========================================"

# Perform backup
PGPASSWORD=$DB_PASSWORD pg_dump -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME > "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    # Compress backup
    gzip "$BACKUP_FILE"
    BACKUP_FILE="${BACKUP_FILE}.gz"
    
    echo "✅ Backup completed successfully: $BACKUP_FILE"
    echo "📊 Size: $(du -h "$BACKUP_FILE" | cut -f1)"
    
    # Remove old backups (retention policy)
    find "$BACKUP_DIR" -name "truper_platform_*.sql.gz" -mtime +$RETENTION_DAYS -delete
    echo "🗑️  Old backups older than $RETENTION_DAYS days removed"
else
    echo "❌ Backup failed!"
    exit 1
fi

echo "========================================"
echo "Backup process completed"
echo "========================================"
