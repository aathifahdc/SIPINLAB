#!/bin/bash

# Konfigurasi Database
DB_HOST="localhost"
DB_NAME="SIPINLAB"
DB_USER="root"
DB_PASS=""

# Konfigurasi Backup
BACKUP_DIR="/var/backups/lab_equipment"
DATE=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="SIPINLAB_$DATE.sql"
LOG_FILE="/var/log/SIPINLAB.log"

# Konfigurasi Cloud/Remote (opsional)
REMOTE_HOST="backup.server.com"
REMOTE_USER="backup_user"
REMOTE_DIR="/backups/SIPINLAB"

# Fungsi logging
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> $LOG_FILE
}

# Buat direktori backup jika belum ada
mkdir -p $BACKUP_DIR

log_message "Starting backup process"

# Backup database
mysqldump -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/$BACKUP_FILE

if [ $? -eq 0 ]; then
    log_message "Database backup successful: $BACKUP_FILE"
    
    # Kompres backup
    gzip $BACKUP_DIR/$BACKUP_FILE
    COMPRESSED_FILE="$BACKUP_FILE.gz"
    
    log_message "Backup compressed: $COMPRESSED_FILE"
    
    # Upload ke server remote (opsional)
    if [ ! -z "$REMOTE_HOST" ]; then
        scp $BACKUP_DIR/$COMPRESSED_FILE $REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR/
        if [ $? -eq 0 ]; then
            log_message "Backup uploaded to remote server successfully"
        else
            log_message "ERROR: Failed to upload backup to remote server"
        fi
    fi
    
    # Hapus backup lokal yang lebih dari 7 hari
    find $BACKUP_DIR -name "SIPINLAB_*.sql.gz" -mtime +7 -delete
    log_message "Old backups cleaned up (older than 7 days)"
    
    # Backup file aplikasi (opsional)
    tar -czf $BACKUP_DIR/app_backup_$DATE.tar.gz /path/to/your/web/application
    log_message "Application files backed up"
    
else
    log_message "ERROR: Database backup failed"
    exit 1
fi

log_message "Backup process completed"

# Kirim notifikasi email (opsional)
if command -v mail &> /dev/null; then
    echo "Lab Equipment backup completed successfully on $(date)" | mail -s "Lab Backup Status" admin@lab.ac.id
fi
