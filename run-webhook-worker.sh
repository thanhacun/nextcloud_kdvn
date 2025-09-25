#!/bin/bash
# Nextcloud Flow/Webhook worker with container check

CONTAINER_NAME="nextcloud-app"
LOG_FILE="/home/kdvn_admin/logs/flow-worker.log"
SLEEP_BEFORE_RETRY=5

# Ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"

# Ensure log file exists
touch "$LOG_FILE"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting Flow/Webhook worker..." | tee -a "$LOG_FILE"

while true; do
    if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Container ${CONTAINER_NAME} not running. Waiting ${SLEEP_BEFORE_RETRY}s..." | tee -a "$LOG_FILE"
        sleep $SLEEP_BEFORE_RETRY
        continue
    fi

    docker exec -u www-data "$CONTAINER_NAME" php /var/www/html/occ background-job:worker -v -t 60 'OCA\WebhookListeners\BackgroundJobs\WebhookCall' 2>&1 | tee -a "$LOG_FILE"
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Worker stopped. Restarting in ${SLEEP_BEFORE_RETRY}s..." | tee -a "$LOG_FILE"
    sleep $SLEEP_BEFORE_RETRY
done
