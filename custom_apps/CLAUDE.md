# KDVN Nextcloud custom apps

Two apps: kdvn_rooms (meeting-room admin page) and kdvn_visitors (guest passes, reception
tablet, room displays). Each has its own CLAUDE.md, loaded when working in that folder.
Status: docs/progress.md. Before any deploy, read docs/deploy-apps.md.

## Layout
- Folder name = app id. Plain PHP and vanilla JS, no build step.
- Installed in the nextcloud_custom_apps volume, mounted at /var/www/html/custom_apps of
  the nextcloud-app container. Production: Nextcloud 34.0.5, PostgreSQL 17. Apps declare Nextcloud 30 to 34.
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
- Check every new Nextcloud API call against the stable34 public API (OCP).
  Say when it is unverified. TODO: no local copy of that source yet.
- Do not copy Booking_Rooms.json (old n8n workflow with an exposed token) into git.

## Pitfalls
- On Nextcloud 32 (check again on 34) `--color-error` is a pale pink background colour; use `--color-error-text`
  for text. Core heading CSS colours h2/h3 dark, so custom dark public pages need
  `color: inherit`.
- Apps have no public API to edit or delete calendar events. Cancelling a visit only
  reminds the host to delete the event.
