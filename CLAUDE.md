# Nextcloud stack: Kinden Vietnam

Docker Compose project `nextcloud-stack` (volumes `nextcloud-stack_*`). Nextcloud 34.0.5
(apache image), PostgreSQL 17, about 500 users. Server path:
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
- Thanh commits and pushes himself.
- Never ask for, print, commit or paste secrets: db.env, redis.env, nextcloud.env,
  notify-push.env, harp.env, stack-back.env, collabora.env (all git-ignored), nor tablet or
  display links, pass links and app passwords. Use placeholders.
- Before suggesting a change, name the file and say whether it needs a restart, a recreate
  (`docker compose up -d <service>`) or maintenance mode, and warn about user downtime.
- Give complete copy-pasteable commands. Check advice against Nextcloud 34 and
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
- Maintenance window: TODO: confirm.

## Backups and upgrades
- Backup before any Nextcloud upgrade: backup.sh plus stack-back, and check the files are
  not empty. Never use backup-nextcloud.sh (legacy; `docker exec -t` corrupts dumps).
- Upgrade one major at a time (the last one went 32 to 33 to 34), rehearsed first on a test
  server built with restore.sh. Nextcloud cannot downgrade.
- Never recreate nextcloud-app (`docker compose up -d app`, image change) without a fresh
  backup. If the custom image (Dockerfile/nextcloud-app) is not in use, hand-installed
  packages (smbclient, ffmpeg, nano, ping) are lost. TODO: confirm whether it is in use.
- The calresources_* tables belong to calendar_resource_management: never change their
  schema. Re-test after any update of that app or of Nextcloud.
