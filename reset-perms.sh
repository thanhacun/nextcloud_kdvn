#!/bin/bash
# reset-perms.sh - Fix Nextcloud folder permissions for Docker volumes

set -e

NC_BASE="/var/lib/docker/volumes"

# Nextcloud volumes
NC_PATH_CONFIG="$NC_BASE/nextcloud-stack_nextcloud_config/_data"
NC_PATH_APPS="$NC_BASE/nextcloud-stack_nextcloud_custom_apps/_data"
NC_PATH_THEMES="$NC_BASE/nextcloud-stack_nextcloud_themes/_data"
NC_PATH_DATA="$NC_BASE/nextcloud-stack_nextcloud_data/_data"

echo "Resetting ownership..."
sudo chown -R www-data:www-data \
  "$NC_PATH_CONFIG" \
  "$NC_PATH_APPS" \
  "$NC_PATH_THEMES" \
  "$NC_PATH_DATA"

echo "Setting directory permissions..."
sudo find "$NC_PATH_CONFIG" -type d -exec chmod 750 {} \;
sudo find "$NC_PATH_APPS"  -type d -exec chmod 755 {} \;
sudo find "$NC_PATH_THEMES" -type d -exec chmod 755 {} \;
sudo find "$NC_PATH_DATA"   -type d -exec chmod 750 {} \;

echo "Setting file permissions..."
sudo find "$NC_PATH_CONFIG" -type f -exec chmod 640 {} \;
sudo find "$NC_PATH_APPS"  -type f -exec chmod 644 {} \;
sudo find "$NC_PATH_THEMES" -type f -exec chmod 644 {} \;
sudo find "$NC_PATH_DATA"   -type f -exec chmod 640 {} \;

echo "Restarting Nextcloud app container..."
docker restart nextcloud-app

echo "Done. ✅ Permissions have been reset."
