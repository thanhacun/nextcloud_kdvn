#!/bin/bash
set -euo pipefail

# --- SETTINGS ---
# Local directory for storing application state and DB snapshots
BACKUP_ROOT="/home/kdvn_admin/nextcloud-stack/backup"
DATE=$(date +%F_%H-%M)
BACKUP_DIR="$BACKUP_ROOT/$DATE"

mkdir -p "$BACKUP_DIR"

log() {
  echo "[$(date +%F_%T)] $*"
}

# 1. MAINTENANCE MODE
# Vital for a consistent backup. Prevents database/file mismatches.
log "Enabling Nextcloud maintenance mode..."
docker exec -u www-data nextcloud-app php occ maintenance:mode --on

# Fail-safe: ensure maintenance mode turns OFF even if script crashes
trap "log 'Disabling maintenance mode...'; docker exec -u www-data nextcloud-app php occ maintenance:mode --off" EXIT

# 2. DATABASE BACKUP
# Corrected: removed -t to prevent binary corruption. User set to 'nextcloud' per db.env.
log "Backing up PostgreSQL database..."
docker exec -i nextcloud-db pg_dump -U nextcloud nextcloud | gzip > "$BACKUP_DIR/db.sql.gz"

log "Backing up PostgreSQL roles + grants..."
docker exec -i nextcloud-db pg_dumpall -U nextcloud --globals-only | gzip > "$BACKUP_DIR/db_globals.sql.gz"

# 3. COMPONENT BACKUPS (EXCLUDING HDD USER DATA)
# These capture your NC environment state. User files are on your separate HDD backup.
log "Backing up config..."
docker run --rm -v nextcloud-stack_nextcloud_config:/data -v "$BACKUP_DIR":/backup alpine tar czf /backup/config.tar.gz -C /data .

log "Backing up custom apps..."
docker run --rm -v nextcloud-stack_nextcloud_custom_apps:/data -v "$BACKUP_DIR":/backup alpine tar czf /backup/custom_apps.tar.gz -C /data .

log "Backing up themes..."
docker run --rm -v nextcloud-stack_nextcloud_themes:/data -v "$BACKUP_DIR":/backup alpine tar czf /backup/themes.tar.gz -C /data .

# 4. VERIFICATION
log "Verifying backup files..."
for f in config.tar.gz custom_apps.tar.gz themes.tar.gz db.sql.gz db_globals.sql.gz; do
  if [ ! -s "$BACKUP_DIR/$f" ]; then
    log "ERROR: Backup file $f is empty or missing!"
    exit 1
  fi
done

# 5. CLEANUP
log "Cleaning up backups older than 7 days..."
find "$BACKUP_ROOT" -maxdepth 1 -type d -mtime +7 -exec rm -rf {} \;

log "Backup finished successfully at $BACKUP_DIR"

