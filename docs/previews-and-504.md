# 504 bursts, previews and logs (found 2026-11-20)

## What happened
Users got 504 from HAProxy in short bursts (02:59, 03:08 to 03:12 on 2026-11-20).
HAProxy marked `nextcloud_backend/nextcloud` DOWN ("Layer4 timeout", 2 s check) while
Apache in nextcloud-app was at `MaxRequestWorkers 150` (151 processes, all sleeping,
CPU idle, 151 idle PostgreSQL connections, no DB locks).

Trigger: a burst of about 200 `/core/preview` requests in 4 minutes. Nextcloud log
(last 3000 lines): 519x `onlyoffice: getConvertedUri: from pdf to jpg` and 297x
`Download with empty or not correct hash: Expired token`. So PDF thumbnails were
rendered by the ONLYOFFICE Document Server (app setting `preview` = true), one HTTP
round trip per thumbnail while an Apache worker waits. The DB log shows matching
`previews_file_uniq_idx` duplicate-key errors (concurrent generation of the same preview).

Second contributor: the image had no Ghostscript (`sh: 1: gs: not found`, 261 lines in
30 min), so the core PDF provider failed too.

Suspect, not confirmed: the broken SMB external storage (mount id 3, see progress.md):
398x `Storage with mount id 3 is not available` and "Error while getting file info"
can also hold workers.

## Where the logs are
- Nextcloud log: `log_type` and `logfile` are unset, so the default applies:
  `<datadirectory>/nextcloud.log` = `/mnt/ncdata/nextcloud.log` in the container
  (host: `/media/ncdata/nextcloud/nextcloud.log`, from docker-compose.yml).
  `/var/www/html/data/nextcloud.log` is an old file from September 2025: ignore it.
  Rotation: `log_rotate_size` = 50 MB. Level: `loglevel` = 2.
- `config.php` is in the volume `nextcloud-stack_nextcloud_config`, not in git. Settings
  below are therefore set with `occ config:system:set`, which writes to that file.
- Apache access/error log and PHP errors: `docker logs nextcloud-app`.
- HAProxy: `docker logs internal-haproxy`. PostgreSQL: `docker logs nextcloud-db`.
- Quick error summary:
  `docker exec nextcloud-app tail -n 3000 /mnt/ncdata/nextcloud.log` (one JSON per line).

## Changes (needs approval for each server step)

### 1. Ghostscript in the image (git, done in this commit)
`Dockerfile/nextcloud-app/Dockerfile`: `ghostscript` added next to ffmpeg.
Needs a rebuild and a recreate of nextcloud-app (`docker compose up -d app`): about a few
minutes of downtime for about 500 users. Before: `backup.sh` plus stack-back, check the
files are not empty. After: `docker exec nextcloud-app which gs ffmpeg`, and the SMB
override (`/dev/null` ini) is still mounted.

### 2. Previews (server settings, no restart, no downtime)
```
# stop the Document Server rendering thumbnails (app setting, DB)
docker exec -u www-data nextcloud-app php occ config:app:set onlyoffice preview --value=false

# drop providers that cannot work here (no LibreOffice in the image) and keep the rest
i=0; for p in PNG JPEG GIF BMP MarkDown MP3 Image HEIC TIFF PDF Movie; do
  docker exec -u www-data nextcloud-app php occ config:system:set enabledPreviewProviders $i --value="OC\\Preview\\$p"; i=$((i+1)); done

# limit parallel generation
docker exec -u www-data nextcloud-app php occ config:system:set preview_concurrency_new --type=integer --value=4
docker exec -u www-data nextcloud-app php occ config:system:set preview_concurrency_all --type=integer --value=8
```
Provider list to keep: PNG, JPEG, GIF, BMP, MarkDown, MP3, Image, HEIC, TIFF, PDF
(needs gs from step 1), Movie. Removed: MSOffice2003, MSOffice2007, OpenDocument.
Result: Word/Excel/PowerPoint files show a generic icon instead of a thumbnail
(the ONLYOFFICE provider was the only one producing them).

Pre-generate instead of generating when a user opens a folder (previewgenerator 5.15.0 is
already installed):
```
# once, outside working hours (heavy, 500 users of files)
docker exec -u www-data nextcloud-app php occ preview:generate-all -vv
# then every 10 minutes from the host cron of kdvn_admin
*/10 * * * * docker exec -u www-data nextcloud-app php occ preview:pre-generate >/dev/null 2>&1
```
Unsure (check before relying on it): whether the host crontab is the right place on this
server. It was empty on 2026-11-20 and cron.php still runs, so find out what triggers
cron.php first. `occ preview:pre-generate` needs the previewgenerator app to see new files
through the normal background jobs.

## Public share of an xlsx (open question)
OnlyOffice 10.2.1 serves public links itself: route `/apps/onlyoffice/s/{shareToken}`
(EditorController::publicPage). For anonymous visitors the app checks the group
restriction of the SHARER (`isUserAllowedToUse($shareBy)`), and opens the editor in `edit`
mode only when the share has the edit permission; otherwise `view` mode. `liveViewOnShare`
is false, so anonymous viewers do not co-edit. There is no app setting "never open on
public links", so the options are in the 504 report; none applied yet.

## Deployed 2026-11-20
- Backup first: backup.sh (`backup/2026-11-20_03-46`, all files non-empty) and a stack-back snapshot.
- Preview settings applied with occ (see above). previewgenerator full run: NOT done yet.
- Image rebuilt with ghostscript. The build re-pulled `34-apache`, so the recreate also upgraded
  Nextcloud 34.0.5 to 34.0.6 (Thanh chose to accept it, not rehearsed on a test server). The
  upgrade also updated calendar, deck, files_antivirus, nc_gantt, files_pdfviewer, text and
  calendar_resource_management 0.12.2 to 1.0.0 from the app store.
  TODO: re-test room booking (calresources_* tables must be unchanged).
- HAProxy: `/apps/onlyoffice/s/` returns 403 for cloud.kinden.com.vn (public links no longer open
  the editor). A single-file bind mount goes stale after `git pull`: the container kept the old
  haproxy.cfg until `docker compose up -d --force-recreate internal-haproxy`. Reload alone (HUP)
  is not enough after a pull. TODO: check what a visitor sees on a public xlsx link (download?).
- Setup checks still open (not touched): HaRP 0.3.0 is older than the required 0.4.8; missing DB
  indices (`occ db:add-missing-indices`); Talk expensive repair steps.

## ONLYOFFICE Document Server host (172.16.10.57), looked at 2026-11-20
- Container `onlyoffice` built locally from `/home/nextcloud/onlyoffice` (not in git):
  `build/Dockerfile` installs `onlyoffice-documentserver_9.3.0-139_amd64.deb` on Ubuntu 24.04;
  older builds kept in `build/old_builds` (9.0.4 btactic, 9.1.0, 9.2.0, 9.3.0-51). Japanese
  fonts in `build/jpfonts`. Compose: `docker-compose.yml`, port 8081, JWT on.
- State: up 28 days, 6 CPU / 15.6 GB RAM, container at 0.25% CPU and 1.2 GB. Healthcheck true.
- Log: `thumb_*` documents hit `handleDeadLetter` repeatedly (thumbnail conversions retried): the
  same preview storm as above. Should stop now that onlyoffice `preview` is false.
