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

## OPcache or customize php ini files
- ~~Need to rename opcahce-recommended.ini file in /usr/local/etc/php/conf.d folder to activate the mounted 99-opcache.ini file~~
- Need to name a customize ini files and the last order. Trick using: zzz-filename.ini

## Include conf files for Apache2
- mount conf files to /etc/apache2/conf-enabled folder
- upload_max_filesize: upload limit to single file (php) and currently mimic the AIO settings
- post_max_size: total post request size (>= upload_max_filesize: can upload multiple files up to)

## Extra apps/libraries need to install for nextcloud-app
- ffmpeg
- smbclient
- nano
