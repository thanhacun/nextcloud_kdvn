# Runbook: Nextcloud 34.0.6 to 35.0.x (DRAFT, not yet rehearsed)

Drafted 2026-11-24 from CLAUDE.md, docs/progress.md, docs/deploy-apps.md and the compatibility check of the same day.
Rules from CLAUDE.md apply: show each server command and wait for approval; state the backup step before `down`,
recreate, `occ upgrade` and maintenance mode; Nextcloud cannot downgrade; one major at a time.

## What was verified (2026-11-24)
- Image `nextcloud:35.0.3-apache` exists on Docker Hub (35.0.0 to 35.0.3). It pins an exact tag on purpose: the 34.0.6
  rebuild silently pulled a newer minor version.
- NC 35 needs PHP 8.3 or later (production: 8.5.13) and Debian 13 (production image is Debian 13). PostgreSQL 17: no change.
- All 33 store-installed apps have a release for 35 in the app store (checked through
  `https://apps.nextcloud.com/api/v1/platform/35.0.0/apps.json`). The other enabled apps are bundled (core/shipped.json).
- Our apps: every OCP class they use exists in stable35 `lib/public` and none is marked deprecated; the calls named in
  progress.md exist (`IUserManager::searchDisplayName`, `ICacheFactory::createDistributed/isAvailable`). Unverified:
  runtime behaviour (the rehearsal covers it) and the CSS variables of the new core theme.
- Big app jumps that `occ upgrade` will pull in: richdocuments 11.1.2 to 12.0.2, spreed 24.0.8 to 25.0.8,
  groupfolders 22.0.6 to 23.0.2, integration_openai 4 to 5, assistant 3 to 4, external 9 to 10, externalportal 2 to 3,
  impersonate 5 to 6, files_accesscontrol 5 to 6, files_automatedtagging 5 to 6, workflow_script 5 to 6.
  notify_push stays 1.4.2.

## Phase A: ahead of the upgrade, still on 34 (own commit, safe on 34)
Our three apps declare `max-version="34"`: Nextcloud 35 would disable them. The draft bumps `max-version` to 35 and the
app versions (kdvn_rooms 0.2.2, kdvn_visitors 0.6.4, kdvn_sensors 0.3.1). A 35 range is valid on 34.
1. Review and commit the three `appinfo/info.xml` changes on their own.
2. Deploy per docs/deploy-apps.md (backup.sh first; steps 2 to 4 run back to back, `occ upgrade` needs a few seconds of
   maintenance mode; note `occ app:list` before, it also updates waiting store apps).
3. Check `occ app:list | grep -A1 kdvn_` shows the new versions.

## Phase B: rehearsal on a test server (restore.sh)
1. Fresh backup on production: `backup.sh`; run `~/dropbox_uploads/nextcloud/pull_server.sh` on Thanh's PC, or copy the
   newest `backup/<date>/` to the test server.
2. Test server on the CURRENT config (main branch, image 34): run `restore.sh` with `BACKUP_DIR` set. If the 35
   override is checked out, restore.sh starts the 35 app against a 34 database and the upgrade happens inside the
   restore, so the recreate path is not rehearsed.
3. Switch the test server to the upgrade branch (image 35, `NC_TAG: 35.0.3-apache`), then:
   `docker compose build app` and `docker compose up -d app` (recreate), then
   `docker exec -u www-data nextcloud-app php occ upgrade`.
4. Rehearsal checks (also the production checks, section D):
   - `occ status` shows 35.0.3; `occ app:list --disabled` does not list our apps or anything unexpected.
   - Timings: build, recreate, `occ upgrade` (this is the user downtime estimate).
   - Talk: spreed 25 against the signaling server (call and join a test room); Collabora: open and save a docx/xlsx
     (richdocuments 12 against the Collabora image on 172.16.10.57); groupfolders: open a group folder, check ACLs.
   - SMB: re-run the encryption test in docs/progress.md (Samba is 4.22.11 in the image; the `/dev/null` smbclient
     override stays unless the test passes).
   - `docker exec -u www-data nextcloud-app php -m | grep -i intl`; walk-in host search with "nguyen van a" against
     "Nguyễn Văn A".
   - Room booking with calendar_resource_management 1.0.0 (kdvn_rooms: edit and save a room; book a meeting with a room
     and an outside email; display shows it; tablet check-in reaches the host in Talk).
   - notify_push: `occ notify_push:self-test`.
   - `occ log:tail` / `docker exec nextcloud-app tail -n 200 /mnt/ncdata/nextcloud.log`: no new errors.

## Rehearsal result (2026-11-24, test stack on 172.16.10.57, trimmed compose in ~/nc-test, no user files)
Restored backup `2026-11-24_01-06` on the 34.0.6 image, deployed the Phase A app bump, then switched to 35.0.3.
- Timings: restore of the DB dump 85 s; image build 46 to 75 s; Phase A deploy (copy + `occ upgrade`) 14 s;
  recreate of nextcloud-app with the 35 image: core upgrade done in about 35 s (entrypoint runs it: maintenance mode on,
  schema, apps, off). Re-enabling the apps (below) about 36 s more.
- **The core upgrade DISABLES 11 store apps as incompatible with 35**: assistant, deck, external, files_accesscontrol,
  files_automatedtagging, groupfolders, impersonate, integration_openai, richdocuments (Office), spreed (Talk),
  workflow_script. Users have no Office editing, no Talk and no group folders until they are updated. The entrypoint
  does NOT update them. Fix (worked, 31 s update + 4 s enable): for each app
  `occ app:update <app>` then `occ app:enable <app>`. Result: assistant 4.1.0, deck 1.19.2, external 10.0.0,
  files_accesscontrol 6.0.1, files_automatedtagging 6.0.1, groupfolders 23.0.2, impersonate 6.0.0,
  integration_openai 5.0.0, richdocuments 12.0.2, spreed 25.0.8, workflow_script 6.1.0.
  Run them right after the upgrade, in one pasted block, before opening the site to users.
- Our kdvn_rooms, kdvn_visitors and kdvn_sensors stayed enabled (the `max-version` bump in Phase A works).
- richdocuments 12.0.2 `occ richdocuments:activate-config` reaches the Collabora discovery on 172.16.10.57: OK.
- `occ setupchecks` clean apart from test-only items (no HaRP container in the test stack).
- Build: `pecl install smbclient` failed once with "No releases available for package pecl.php.net/smbclient" and
  worked on the retry. Build the image BEFORE the downtime window and retry if it fails.
- NOT tested here: real Talk calls (signaling removed in the test), Office editing in a browser, SMB mounts,
  Samba encryption test, tablet and displays, SSO login, user files, performance with 639 GB.
- `restore.sh` restores `db.sql.gz` BEFORE `db_globals.sql.gz`: the dump needs the role `oc_admin`, so every object
  failed with "role oc_admin does not exist". Restore the globals first, then the dump (done that way here).
- Test-only artefacts, not production issues: the empty data dir has no `appdata_<id>/` folder, so the app store
  cache and the Collabora discovery cache cannot be written (create `appdata_<id>/appstore` and
  `appdata_<id>/richdocuments/remoteData` in a test); a new data dir needs `.ncdata` and `chown 33:33`;
  opcache memory must stay at 512 (`PHP_OPCACHE_MEMORY_CONSUMPTION`), zzz-nextcloud.ini expects it;
  `network: host` in the build section needs a bake entitlement on a newer Compose and was dropped in the test.

## Phase C: production upgrade (holiday window)
Announce the downtime first (about 500 users). Times are from the rehearsal.
1. Backup, check non-empty: `/home/kdvn_admin/nextcloud-stack/backup.sh` (maintenance mode about 2 min), then the
   stack-back snapshot `docker exec stack-backup rcb backup` and `docker exec stack-backup rcb snapshots | tail -n 4`,
   then on Thanh's PC `~/dropbox_uploads/nextcloud/pull_server.sh` (log: `Uploaded + verified`, `done OK`).
2. Record the state: `occ status`, `occ app:list > ~/apps-before-35.txt`, `docker images | grep nextcloud-app`.
3. Merge the upgrade branch (image 35, `NC_TAG: 35.0.3-apache`) and `git pull` on the server.
4. `docker compose build app` (no downtime), then with approval and downtime:
   `docker compose up -d app` (recreates nextcloud-app; the entrypoint runs the upgrade) and watch
   `docker logs -f nextcloud-app`. If the entrypoint did not finish: `docker exec -u www-data nextcloud-app php occ upgrade`.
5. Check `occ status` (version 35.0.3, maintenance false). Then re-enable the 11 apps the upgrade disabled (see the
   rehearsal result): `for a in assistant deck external files_accesscontrol files_automatedtagging groupfolders
   impersonate integration_openai richdocuments spreed workflow_script; do docker exec -u www-data nextcloud-app
   php occ app:update $a && docker exec -u www-data nextcloud-app php occ app:enable $a; done`.
   Then `occ app:list --disabled` must show only the apps that were disabled before (compare with
   `~/apps-before-35.txt`).
6. Rebuild/recreate of dependent containers if they complain: notify (`docker compose up -d notify`), harp.
7. Rollback: Nextcloud cannot downgrade. Rollback means the old image `kdvn/nextcloud-app:34` plus the DB dump
   `db.sql.gz`, `config.tar.gz`, `custom_apps.tar.gz` from step 1 (restore.sh logic, user files stay untouched).
   Decide the go/no-go time before starting.

## D. After the upgrade
Same checks as the rehearsal, on production: Talk call, open and save an Office file, group folder, SMB mounts
(`occ files_external:verify <id>`), room booking and display, tablet check-in, push notifications, login through SSO and
the break-glass direct login. Watch the log for an hour. Update docs/progress.md, CLAUDE.md (version, image), and the
memory notes.

## Open items for the upgrade day
- Base `docker-compose.yml` still says `nextcloud:32.0.15` / `NC_TAG: 32.0.15-apache` for `app`; the override replaces
  them. Consider cleaning it up in the same commit.
- Decide go/no-go with Thanh; no deploy without his approval.
