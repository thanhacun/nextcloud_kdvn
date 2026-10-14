#!/bin/bash
set -euo pipefail

# --- SETTINGS ---
# Path where you transferred your backup files (e.g. /home/kdvn_admin/transferred_backups/2026-07-31_16-00)
BACKUP_DIR="/path/to/your/backup/folder"

log() {
  echo "[$(date +%F_%T)] $*"
}

# 1. INITIAL STACK PREPARATION
log "Starting Database and Redis..."
docker compose up -d db redis
log "Waiting for database to initialize (10s)..."
sleep 10

# 2. DATABASE RESTORE
# Fixed: Uses -i to prevent stream corruption. Targets 'nextcloud' user per your db.env.
log "Restoring PostgreSQL database from dump..."
gunzip -c "$BACKUP_DIR/db.sql.gz" | docker exec -i nextcloud-db psql -U nextcloud -d nextcloud

log "Restoring PostgreSQL globals..."
gunzip -c "$BACKUP_DIR/db_globals.sql.gz" | docker exec -i nextcloud-db psql -U nextcloud

# 3. COMPONENT RESTORE (Config, Apps, Themes)
# This extracts your backups back into the Docker volumes.
log "Restoring config volume..."
docker run --rm -v nextcloud-stack_nextcloud_config:/data -v "$BACKUP_DIR":/backup alpine sh -c "rm -rf /data/* && tar xzf /backup/config.tar.gz -C /data"

log "Restoring custom apps volume..."
docker run --rm -v nextcloud-stack_nextcloud_custom_apps:/data -v "$BACKUP_DIR":/backup alpine sh -c "rm -rf /data/* && tar xzf /backup/custom_apps.tar.gz -C /data"

log "Restoring themes volume..."
docker run --rm -v nextcloud-stack_nextcloud_themes:/data -v "$BACKUP_DIR":/backup alpine sh -c "rm -rf /data/* && tar xzf /backup/themes.tar.gz -C /data"

# 4. START APPLICATION
log "Starting Nextcloud application..."
docker compose up -d app

# 5. METADATA SYNCHRONIZATION
# Because physical user files on the HDD are restored separately, we must rescan the metadata.
log "Disabling maintenance mode..."
docker exec -u www-data nextcloud-app php occ maintenance:mode --off

log "Scanning for file metadata (synchronizing DB with HDD)..."
# Get user IDs using simple text processing instead of jq
users=$(docker exec -u www-data nextcloud-app php occ user:list | awk '{print $2}' | tr -d ':')

for user in $users; do
    echo "Scanning local files for user: $user"
    # Target only the 'files' folder to avoid external SMB mounts
    docker exec -u www-data nextcloud-app php occ files:scan "$user" --path="$user/files"
done

# Will not use this command because it also scan external storage
#docker exec -u www-data nextcloud-app php occ files:scan --all

log "Restore test complete. Please check the Web UI at your new server address."
