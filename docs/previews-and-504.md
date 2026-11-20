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

### OnlyOffice 10.0.0 trial (2026-11-20, throwaway container on 172.16.10.57, now removed)
- Edit test on production (9.3.0.51 reported by Nextcloud): editor open, file download and two
  `/apps/onlyoffice/track` callbacks seen at 04:50: editing works. Redis is not running inside
  the container and the connection statistics stay at 0; neither breaks editing.
- Official image `onlyoffice/documentserver:10.0.0` (build 169) started healthy on localhost:8091 with the
  jpfonts mounted. CSV to PDF and CSV to XLSX conversions worked; Japanese and Vietnamese text kept.
- Its info.json reports connections=2147483647, hasLicense=false. NOT verified what limit it enforces.
- Migration trap: the official image refuses to fetch from private IPs (conversion error -4).
  Needs `services.CoAuthoring.request-filtering-agent.allowPrivateIPAddress` and
  `allowMetaIPAddress` = true in local.json (the production mount json/local-production-linux.json
  is commented out in its compose). Edit local.json so it stays owned by ds:ds, mode 640, or the
  services will not start (happened in the trial).
- Not tested: the Nextcloud connector against 10.0.0 (needs a switch of the production
  DocumentServerUrl), co-editing, large xlsx.

## HaRP upgrade to 0.4.8 and the expired FRP certificates (2026-11-20)
- compose now uses `ghcr.io/nextcloud/nextcloud-appapi-harp:release` (was `:main`, which ran 0.3.0).
- The new HaRP exited at start: `frpc` "login to the server failed: session shutdown". Cause: the FRP TLS
  certificates in `haproxy/certs/frp` (git-ignored, generated 2025-11-07) expired on 2026-11-07. The old
  0.3.0 container only kept working because it started on 2026-11-03.
- Fix: renamed `haproxy/certs/frp` to `frp.expired-2026-11-07` (kept), restarted harp, HaRP generated new
  certificates valid until 2040-07-29. Setup checks "AppAPI deploy daemon" and "HaRP version check" pass.
- ExApp `test-deploy` (test app only) failed to re-enable afterwards: its frpc most likely still has the old
  client certificate. Nothing in production uses it. Re-register it if the app is needed again.
- After updating Nextcloud, restart HaRP (HaRP 0.4.8 loads the ExApp route verbs at start).

## Collabora re-check (2026-11-20), trial container on 172.16.10.57
- Why Collabora was dropped (git 6da5641, 2025-10-04): "handle opening big MS Office files". The old HAProxy
  `haproxy/lua/xlsx_block.lua` (disabled) refused every .xlsx ("Prevent Collabora from loading huge XLSX
  files (> 10 MB)"). Production state: `collabora` container (collabora/code:latest from 2026-02-25, compose
  profile `manual-start`) idle, richdocuments 11.1.2 installed but DISABLED.
- Trial: `collabora/code:latest` = Collabora Online Development Edition 26.04.6.1, container `collabora-test`,
  port 9980, memory cap 6 GB, WOPI host `http://172.16.10.57:8100` (mock host `/tmp/coltest/wopi_mock.py`,
  never overwrites originals; saves go to `<name>.saved`).
- Limits: startup log says max open documents 1000000 and max connections 1000000 (no practical cap).
  Per document defaults: limit_load_secs 100, limit_convert_secs 100, memproportion 80%.
- Big xlsx: synthetic 86 MB, 750k rows x 14 columns, 2 sheets, Japanese/Vietnamese text. Server-side
  load + export to CSV: 12 s, about 800 MB peak, no error. Browser behaviour (popup, speed) NOT measured by
  Claude; must be tried by hand.
- Japanese: works only when the fonts are inside the jail template. Mounting jpfonts at
  `/usr/share/fonts/custom` is NOT enough (jail bind-mount fails: "Bind-Mounting fails", also with SYS_ADMIN,
  EACCES); mounting them at `/opt/cool/systemplate/usr/share/fonts/custom` works (NotoSerifJP, NotoSansJP
  used). Unknown Japanese fonts (MS Gothic, MS Mincho, Yu Mincho, Meiryo) fall back to NotoSansSC (Chinese
  glyph shapes): needs a font replacement table. Tested only through PDF export.
- Licence: CODE is the free development edition; not verified from a vendor page in this session.
- OnlyOffice 10.0.0 with more than 20 sessions: NOT tested. A scripted Socket.IO client connected 30
  sockets but never got an `auth` reply (client problem). Only evidence: info.json
  licenseInfo.connections = 2147483647.

### Collabora moved to 172.16.10.57 (2026-11-20, decision by Thanh)
- Project `~/collabora` on 172.16.10.57 (local git, not in this repo): image `kdvn/collabora:26.04.6.1.1-jp`
  (CODE 26.04.6.1.1 + jpfonts baked into `/opt/cool/systemplate/usr/share/fonts/custom`; base image has no
  shell, so only COPY steps; `COPY --chmod` applies to directories too, use 755 for the fonts dir).
  Compose: port 9980, TLS terminated by HAProxy (`ssl.termination=true`), `aliasgroup1=https://cloud.kinden.com.vn:443`,
  6 GB, `extra_hosts` cloud.kinden.com.vn to 172.16.10.247. No secrets in it.
- haproxy.cfg: `collabora_backend` -> 172.16.10.57:9980, `timeout tunnel 1h`.
- Japanese: Noto Sans/Serif JP work by name. Windows names (MS Gothic, MS Mincho, Meiryo, Yu *) are NOT mapped:
  a replacement table in coolkitconfig.xcu, fontconfig rules and DefaultLocale_CJK=ja-JP had no effect in the PDF
  test; everything falls back to NotoSansSC (Chinese glyph shapes). M PLUS 1p and IPAex are installed but not used
  by name. Open: check on screen with a real Japanese file.
- CODE licence terms for 500 users: not verified. ONLYOFFICE 10.0.0 stopped until the licence is clear.

## Cron and backups on 172.16.10.247 (found and fixed 2026-11-20)
- The host clock is UTC: a "02:00" cron line runs at 09:00 Vietnam time. stack-back (its own container) uses
  Vietnam time. Plan cron times in UTC (Vietnam = UTC+7).
- The nightly 02:00 line ran `backup-nextcloud.sh` (legacy). Its `docker exec -t pg_dump` leaves CR characters in
  db.sql.gz (seen in backup/2026-11-19_02-00 and 2026-11-20_02-00; the manual backup.sh run is clean): those
  dumps are not safe to restore. stack-back snapshots were not affected. Replaced by `backup.sh` at 18:00 UTC
  (01:00 Vietnam; it turns maintenance mode on for about 3 minutes).
- The 02:30 `preview:pre-generate` line used `docker exec -it`, which fails under cron (no TTY): nothing was
  pre-generated. Now without `-it`, at 19:30 UTC, with a log and `flock` so that it skips while the one-time
  `preview:generate-all` runs.
- One-time `occ preview:generate-all` at 18:30 UTC on 2026-11-20 (01:30 Vietnam, after the backup), logged to
  `~/logs/preview-generate-all.log`, removes its own crontab line when done. Stop it with
  `docker exec nextcloud-app pkill -f preview:generate-all`. Crontab before the change:
  `~/crontab.backup-2026-11-20` on the server.
- Moved to night (2026-11-20): freshclam from 03:30 to 20:00 UTC (03:00 Vietnam) and the antivirus background scan
  from 04:00 to 20:30 UTC (03:30 Vietnam); they ran in working hours before.
- Host crontab, UTC (Vietnam = +7): every 5 min cron.php; 18:00 backup.sh; 18:30 one-time preview:generate-all
  (2026-11-20 only); 19:30 preview:pre-generate; 20:00 freshclam; 20:30 antivirus scan.
- Large uploads: default quota is 5 GB per user. Chunked uploads bypass the PHP size limits. A Flow rule
  (files_accesscontrol "Block access to a file" with a file-size and MIME-type check) can block big files; untested.
- Port 9980 on 172.16.10.57 (Collabora) is open to the LAN. A DOCKER-USER rule to allow only 172.16.10.247 was tried on
  2026-11-20 and blocked everything, including 172.16.10.247: Office was down from about 07:42 UTC until the rule was
  removed (HAProxy: "collabora_backend has no server available", 503 on /hosting/discovery). Cause not found (rules
  could not be read without sudo). If retried: run it as one command that removes itself after 60 s, and test from
  172.16.10.247 inside that minute.
