# KDVN Nextcloud custom apps

Four apps: kdvn_rooms (meeting-room admin page), kdvn_sensors (indoor air display, new, not deployed), kdvn_directory (staff directory, new, not deployed) and kdvn_visitors (guest passes, reception
tablet, room displays). Each has its own CLAUDE.md, loaded when working in that folder.
Status: docs/progress.md. Before any deploy, read docs/deploy-apps.md.

## Layout
- Folder name = app id. Plain PHP and vanilla JS, no build step.
- Installed in the nextcloud_custom_apps volume, mounted at /var/www/html/custom_apps of
  the nextcloud-app container. Production: Nextcloud 35.0.3, PostgreSQL 17. Apps declare Nextcloud 30 to 35.
- The server stack is a separate repo (TODO: confirm URL). This repo cannot see its
  CLAUDE.md, so the critical rules are repeated below.

## Rules
- Claude commits when Thanh asks; a push needs his explicit approval (show the command first). Source changes only through git; never edit files
  inside the container by hand.
- Show every server command and wait for approval. Read-only checks are fine
  (`occ app:list`, `occ log:tail`). Always state the backup step before `occ upgrade`,
  recreating nextcloud-app, or any upgrade.
- occ is always `docker exec -u www-data nextcloud-app php occ ...`.
- Never print, commit or paste tablet/display links, pass links, app passwords or *.env
  contents. Keys are shown once in the admin page and stored hashed.
- Never name a route URL parameter `action`, `caller` or `_route` (the Nextcloud router
  consumes them; `action` once made every Let in/Decline button return HTTP 500).
- The calresources_* tables belong to calendar_resource_management. Only kdvn_rooms
  writes to them, and never changes their schema.
- Check every new Nextcloud API call against the stable35 public API (OCP).
  Say when it is unverified. A local copy: `git clone --depth 1 --filter=blob:none --sparse --branch stable35
  https://github.com/nextcloud/server.git <dir> && git -C <dir> sparse-checkout set lib/public` (checked on
  2026-11-24: all OCP classes used by the three apps exist in stable35, none deprecated).
- Do not copy Booking_Rooms.json (old n8n workflow with an exposed token) into git.

## Pitfalls
- On Nextcloud 32 (check again on 34) `--color-error` is a pale pink background colour; use `--color-error-text`
  for text. Core heading CSS colours h2/h3 dark, so custom dark public pages need
  `color: inherit`.
- Apps have no public API to edit or delete calendar events. Cancelling a visit only
  reminds the host to delete the event.
