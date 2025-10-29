# Manually setup Nextcloud Docker Stack (mimic AIO) to avoid License Fee for over 100 users
## Basic
### Containers
### Backups
### Security
### Windmill Flow Engine
- Create run-flow-worker.sh to handle event listening faster
- Create systemd service for this running and loging
## Notes
- Add users to a group
php occ user:list | awk '{print $2}' | sed 's/://g' | while read u; do php occ group:adduser Everyone $u; done

## OPcache
- Need to rename opcahce-recommended.ini file in /usr/local/etc/php/conf.d folder to activate the mounted 99-opcache.ini file

## Extra apps/libraries need to install for nextcloud-app
- ffmpeg
- smbclient
- nano
