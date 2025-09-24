#!/bin/bash
set -euo pipefail

# --- SETTINGS ---
BACKUP_ROOT="/home/kdvn_admin/nextcloud-stack/backup"
DATE=$(date +%F_%H-%M)
BACKUP_DIR="$BACKUP_ROOT/$DATE"

mkdir -p "$BACKUP_DIR"

log() {
  echo "[$(date +%F_%T)] $*"
}

# --- CONFIG ---
log "Backing up config..."
docker run --rm \
  -v nextcloud-stack_nextcloud_config:/data \
  -v "$BACKUP_DIR":/backup \
  alpine sh -c "tar czf /backup/config.tar.gz -C /data ."

# --- CUSTOM APPS ---
log "Backing up custom apps..."
docker run --rm \
  -v nextcloud-stack_nextcloud_custom_apps:/data \
  -v "$BACKUP_DIR":/backup \
  alpine sh -c "tar czf /backup/custom_apps.tar.gz -C /data ."

# --- THEMES ---
log "Backing up themes..."
docker run --rm \
  -v nextcloud-stack_nextcloud_themes:/data \
  -v "$BACKUP_DIR":/backup \
  alpine sh -c "tar czf /backup/themes.tar.gz -C /data ."

# --- DATABASE ---
log "Backing up PostgreSQL database..."
docker exec -t nextcloud-db pg_dump -U nextcloud nextcloud | gzip > "$BACKUP_DIR/db.sql.gz"

# --- DATABASE GLOBAL ROLES + GRANTS ---
log "Backing up PostgreSQL roles + grants..."
docker exec -t nextcloud-db pg_dumpall -U nextcloud --globals-only | gzip > "$BACKUP_DIR/db_globals.sql.gz"

# --- VERIFY ---
log "Verifying backup files..."
for f in config.tar.gz custom_apps.tar.gz themes.tar.gz db.sql.gz db_globals.sql.gz; do
  if [ ! -s "$BACKUP_DIR/$f" ]; then
    log "ERROR: Backup file $f is empty or missing!"
    exit 1
  fi
done

# --- CLEANUP OLD BACKUPS (keep 7 days) ---
find "$BACKUP_ROOT" -maxdepth 1 -type d -mtime +7 -exec rm -rf {} \;

log "Backup finished successfully at $BACKUP_DIR"
