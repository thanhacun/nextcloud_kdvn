# Manually setup Nextcloud Docker Stack (mimic AIO) to avoid License Fee for over 100 users
## Basic
### Containers
- Postgresql
- Redis
- Nextcloud
- AV antivirus
- OnlyOffice
- ~~Collabora~~
- Stack-back
- HARP
- Push
- Talk-HPB
### Backups
- Using stack-back at ghcr.io/lawndoc/stack-back
- Using rcb commands inside the docker to manipulate backup/status
- [Stack Back Guide](https://stack-back.readthedocs.io/en/latest/)
### Security
### Windmill Flow Engine
- Create run-flow-worker.sh to handle event listening faster
- Create systemd service for this running and loging
## Notes
- Add users to a group
php occ user:list | awk '{print $2}' | sed 's/://g' | while read u; do php occ group:adduser Everyone $u; done

## OPcache or customize php ini files
- ~~Need to rename opcahce-recommended.ini file in /usr/local/etc/php/conf.d folder to activate the mounted 99-opcache.ini file~~
- Need to name a customize ini files and the last order. Trick using: zzz-filename.ini mount/copy to folder /usr/local/php/conf.d/

## Include conf files for Apache2
- mount/copy conf files to /etc/apache2/conf-enabled folder using the same trick zzz-filename.conf
- upload_max_filesize: upload limit to single file (php) and currently mimic the AIO settings
- post_max_size: total post request size (>= upload_max_filesize: can upload multiple files up to)

## Extra apps/libraries need to install for nextcloud-app
- ffmpeg
- php-smbclient
``` bash
    apt update && apt -y install libsmbclient-dev libmagickwand-dev
    pecl install smbclient && echo "extension=smbclient.so" > /usr/local/etc/php/conf.d/docker-php-ext-smbclient.ini
```
- nano
- ping
```
   apt update && apt -y install nano ffmpeg iputils-ping libsmbclient-dev libmagickwand-dev && pecl install smbclient && echo "extension=smbclient.so" > /usr/local/etc/php/conf.d/docker-php-ext-smbclient.ini
```
### External Storage
- SMB in KDVN using domain account. Shared in external storage cannot add without password, so should know domain account before adding any thing
- Using Amazon S3 for Digital Ocean Storage
- For Dropbox (business or not), need extra steps:
* In host VM, install rclone
* Using rclone config to add dropbox connection. Please note that for server without UI (cannot access browser or using headless browser):
-- Should have an access to another PC that can access UI Browser, then using as guide
* Mount dropbox to a folder in VM using rclone mount command with option --allow-other. Also note that in order for the option work, need to uncomment allow-other in VM /etc/fuse.conf.
* Then in docker compose, mount again this folder to a folder inside container (actually calling binding) with rshared option like: /mnt:/mnt/db:rshared
* Then add as a local in External Storage.
