# Nextcloud stack: Kinden Vietnam

Docker Compose project `nextcloud-stack` (volumes `nextcloud-stack_*`). Nextcloud 35.0.3
(apache image, upgraded from 34.0.6 on 2026-11-24), PostgreSQL 17, about 500 users. Server path:
/home/kdvn_admin/nextcloud-stack. Status and open items: docs/progress.md.
Our two Nextcloud apps (kdvn_rooms, kdvn_visitors) live in custom_apps/ (rules:
custom_apps/CLAUDE.md, deploy: docs/deploy-apps.md). custom_apps/ is also the Docker volume
content on the server, so apps installed from the app store land there too.

## Working rules
- Changes go through git. Never edit files on the server or inside containers by hand.
- Before running any command on the server, show it and wait for approval.
  Read-only checks are fine: `docker ps`, `docker compose logs`, `docker compose config`,
  `occ app:list`, `occ log:tail`.
- Always state the backup step and wait for explicit approval before: `down`, `rm`,
  volume changes, recreating a service, `occ upgrade`, maintenance mode.
- Claude commits when Thanh asks. Pushing: Claude shows the `git push` command and runs it only after Thanh approves.
- Never ask for, print, commit or paste secrets: db.env, redis.env, nextcloud.env,
  notify-push.env, harp.env, stack-back.env, collabora.env (all git-ignored), nor tablet or
  display links, pass links and app passwords. Use placeholders.
- Before suggesting a change, name the file and say whether it needs a restart, a recreate
  (`docker compose up -d <service>`) or maintenance mode, and warn about user downtime.
- Give complete copy-pasteable commands. Check advice against Nextcloud 35 and
  PostgreSQL 17, and say when unsure.

## Stack facts
- occ is always `docker exec -u www-data nextcloud-app php occ ...`.
- The stack mimics AIO to avoid the licence fee above 100 users. Do not suggest AIO.
- Local changes go in docker-compose.override.yml or zzz- prefixed files
  (php-conf.d/zzz-nextcloud.ini, apache2/conf-enabled/zzz-nextcloud.conf), not in images
  or container internals.
- Routing changes: check haproxy/haproxy.cfg, extra_hosts and trusted_domains/overwrite*
  settings together.
- Run reset-perms.sh after changing volume permissions.
- Office editor: Collabora (richdocuments) runs on 172.16.10.57 (`~/collabora`, image kdvn/collabora:...-jp), not on this
  host; haproxy `collabora_backend` points there. The ONLYOFFICE app is disabled and its container on 172.16.10.57 is
  stopped (rollback: `docker start onlyoffice`, `occ app:enable onlyoffice`, `occ app:disable richdocuments`).
  ONLYOFFICE 10.x is not to be used until the licence is clear. Details: docs/previews-and-504.md.
- haproxy.cfg is bind-mounted as a single file: after `git pull` the container still sees the old one. Validate the new
  file in a throwaway container, then `docker compose up -d --force-recreate internal-haproxy`.
- The host clock is UTC: write cron times in UTC (Vietnam = UTC+7). Nextcloud log: /mnt/ncdata/nextcloud.log inside the nextcloud-app container only (not on the host): `docker exec nextcloud-app tail -n 200 /mnt/ncdata/nextcloud.log`.
- Maintenance window: TODO: confirm.

## Backups and upgrades
- Backup before any Nextcloud upgrade: backup.sh plus stack-back, and check the files are
  not empty. Never use backup-nextcloud.sh (legacy; `docker exec -t` corrupts dumps).
- Upgrade one major at a time (the last ones went 32 to 33 to 34 to 35), rehearsed first on a test
  server built with restore.sh. Nextcloud cannot downgrade. Runbook and the 35 rehearsal results:
  docs/upgrade-35-runbook.md.
- Never recreate nextcloud-app (`docker compose up -d app`, image change) without a fresh
  backup. If the custom image (Dockerfile/nextcloud-app) is not in use, hand-installed
  packages (smbclient, ffmpeg, nano, ping) are lost. The custom image is in use (override builds
  kdvn/nextcloud-app:35 from Dockerfile/nextcloud-app/Dockerfile, NC_TAG pinned to an exact tag).
- After ANY recreate of nextcloud-app, check `curl -s https://cloud.kinden.com.vn/status.php` at once. Until 2026-11-24
  05:01 UTC HAProxy resolved `nextcloud-app` only at start, so the backend stayed DOWN and users got 503 (about 8 min).
  haproxy.cfg now has `resolvers docker` (commit 8c0e5c0), which should make this automatic; it is not yet proven by a
  real recreate, so if you get 503, run `docker restart internal-haproxy`.
- A major upgrade disables store apps that have no release for it until they are updated and re-enabled
  (`occ app:update <app>` then `occ app:enable <app>`); compare `occ app:list` with the list saved before.
- Image build: `pecl install smbclient` can fail transiently ("No releases available"); build before the
  downtime window and retry.
- The calresources_* tables belong to calendar_resource_management: never change their
  schema. Re-test after any update of that app or of Nextcloud.
