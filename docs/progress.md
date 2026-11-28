# Nextcloud stack and apps: status (dated 2026-11-27)

## 2026-11-27: room display hardware, BMS scope with Azbil, HR leavers workflow (n8n)
- Room display hardware: MSI Pro MP165 E6 (16 inch, 1920x1080) plus Raspberry Pi 5, `layout=condensed`, 3 rooms per screen
  fits. Checks, Pi setup steps and what is still open: docs/pi-display-kiosk.md. The same page also fixed the old plan of a
  15-inch screen below.
- BMS scope (Azbil): Azbil proposes dropping the SVC and savic-net G5; the DDC (BTL BACnet device) would send data only
  through the BACnet Service REST API (read only). Consequences for kdvn_sensors, history and alerting, plus the questions to
  send Azbil: end of docs/plan-sensor-display.md. Decision pending, nothing deployed.
- n8n HR sync (workflows live in n8n, notes in ~/n8n/sync-ak-users/PROGRESS.md): 18 overdue leavers (7 Hanoi, 11 HCMC) were
  deactivated in Authentik and disabled in Nextcloud on 2026-11-27 with a new approval-gated bulk workflow, verified by a
  re-read. Step 2 now reports a failed Nextcloud disable instead of marking it done. Nextcloud side effects: 10 accounts
  disabled (5 Database, 5 user_saml). The 8 others never had a Nextcloud account.
- Touch room display (committed 28acea5 and pushed; DEPLOYED to production .247 on 2026-11-27 without a version bump, then
  `apachectl -k graceful` made the new routes work; rollback copy /home/kdvn_admin/backup-kdvn_visitors-20261127; TESTED by Thanh on
  production 2026-11-27: QR booking and face booking (Odoo identify API, key pasted by him) both worked end to end. Then added (not deployed yet): when the face is not recognised after 15 s, or is ambiguous, the screen
  shows "We could not recognise you" with Try again and Use my phone instead (no automatic jump to the QR), and the screen says in
  vi/en/ja that the picture is only used to recognise the person and is not saved (QR: only the booking is saved). Odoo still keeps
  one log row per identify call (time, state, employee, ms; 30 days): HR should know. Still to do: HR
  consent before staff see the camera, the tablet hardware and its https kiosk setup, delete the test bookings, proxy_mode in Odoo): new interactive link `/touch/{key}` in kdvn_visitors, the read-only
  `/display/{key}` is unchanged. Tap Details for a room's meetings, or Book now for 15/30/60 minutes starting now. Nobody types
  a name: the person is PROVEN. Stage 1 (built): QR code on the screen, the phone (Nextcloud login, SSO included) opens
  `/confirm/{nonce}` and taps Confirm, the screen collects a signed single-use ticket (`TouchTicket`, 60 s) and `book` accepts
  nothing but a ticket; the event goes into that person's calendar with the room invited (CalendarBooking), the server re-checks
  the room, 10 bookings per minute per IP. Stage 2 (Nextcloud side built; Odoo side built 2026-11-27, kdvn_face_kiosk 19.0.0.10.0 installed in odoo19_dev, switch OFF, route
  404 until the dev server is restarted; full chain NOT tested; frames 640 px q0.8 once a second, give up after 15 s as Odoo advises): when the page has `data-face-url` (only when "Use face recognition on touch
  screens" is on in Administration settings > Visitors, with the Odoo address and key; the key is stored encrypted, the picture is
  never kept), Book opens the
  camera, posts a frame every 0.5 s and shows "Hello X (3, 2, 1) Cancel"; "Use my phone" falls back to the QR.
  The relay (`TouchController::face` + `FaceRelay`) calls Odoo `POST /kdvn_face/ext/identify` with header `X-KDVN-Key`,
  allowed to reach a private address like Talk; Odoo errors, wrong key or a missing Nextcloud account send the screen to the QR.
  Odoo answers 403 (key, address or switch), 400 bad_image, 429 rate_limit (screen waits), 503 engine; setup in Odoo Face ID menu:
  client with allowed address = the Nextcloud server, "New key" (shown once), "Identify API: switch". The contract: POST form `image` (jpeg), `roomId`, `minutes`,
  `building` -> JSON `state` none | many | unknown | ambiguous | ok, and for ok `name` and `ticket` (= `TouchTicket::issue(uid,
  roomId, minutes, building)`). Odoo returns the employee's user login; the Nextcloud uid is that login without the domain
  part (Thanh, 2026-11-27: same user name in Odoo and Nextcloud), checked with `IUserManager::get()` before the ticket is issued. Own key
  (`touch_key_hash`), created with "Create touch links" in Administration settings > Visitors. Demos with fake data and a cartoon
  avatar instead of a camera (no real faces), plus the harness: ~/room-display-demo/ (room-display-demo.mp4 = read-only board,
  touch-booking-demo.mp4 = touch booking, face then QR). Untested on the real server: routes, the cache (needs a distributed
  cache for tickets, Redis is in the stack), booking into a real calendar, the `requesttoken` header on the confirm page on
  NC 35. Not decided: HR/legal consent for a face camera (Thanh asks HR), the Odoo endpoint (separate task in ~/odoo19_thcn,
  kdvn_face_kiosk is a prototype on odoo19_dev), the touch hardware (a tablet with camera, not the MSI plus Pi). Deploy:
  docs/deploy-apps.md; a new `<version>` means `occ upgrade` (maintenance mode), a copied file without a version change does
  not, but we have not checked whether Nextcloud then picks up the new routes.
- Open: fresh app password for the n8n credential "Cloud (admin)" (401), then drop the personal "Cloud (tqthanh)".

## 2026-11-24: Nextcloud 35.0.3 upgrade COMPLETE, Dropbox backup, backup audit
Status: the upgrade 34.0.6 to 35.0.3 is finished (Thanh did the browser checks: Office, Talk video, group folders, SSO,
tablets and displays). Runbook and rehearsal results: docs/upgrade-35-runbook.md. Short version:
- Production upgraded 34.0.6 to 35.0.3 (image kdvn/nextcloud-app:35 from nextcloud:35.0.3-apache, PHP 8.5.13). Core upgrade
  about 75 s; the upgrade disabled 11 store apps until their 35 releases were installed (assistant, deck, external,
  files_accesscontrol, files_automatedtagging, groupfolders, impersonate, integration_openai, richdocuments, spreed,
  workflow_script); `sharing` is new. kdvn_rooms 0.2.2, kdvn_visitors 0.6.4, kdvn_sensors 0.3.1 declare 30 to 35 (deployed
  on 34 first).
- Outage of about 8 min (01:59 to 02:07 UTC): HAProxy kept the old nextcloud-app IP after the recreate (503 for users).
  Fix applied: `docker restart internal-haproxy`. Proper fix done at 05:01 UTC (commit 8c0e5c0): haproxy.cfg has a
  `resolvers docker` section (127.0.0.11:53) and `resolvers docker init-addr libc,none` on the nextcloud-app, notify and
  harp servers, so HAProxy follows a new container IP by itself; recreated with
  `docker compose up -d --force-recreate internal-haproxy`. Not yet tested by a real recreate of nextcloud-app: after the
  next one still check `curl -s https://cloud.kinden.com.vn/status.php`.
- After the upgrade: `occ db:add-missing-indices` (3 indices) and `occ maintenance:repair --include-expensive` done.
- Talk signaling server (HPB) done 2026-11-24: it runs on the external host `website@128.199.215.193` (a shared server with Odoo and KBM;
  turn.kinden.com.vn), project `~/Projects/talk-hpb` (docker-compose.yml with the image pinned by digest, secrets in
  `talk-secrets.env`, mode 600; old image pins kept as comments in the compose file, `docker-compose.yml.bak-2026-11-24` kept;
  the env backup with the old, leaked secret was deleted after Thanh's successful video call test). Upgraded 2.1.1 to 2.2.0 (aio-talk image of 2026-11-19) and SIGNALING_SECRET
  rotated (twice: the first new value was also printed into a chat by mistake; `occ config:app:set` echoes its value, so
  send its output to /dev/null; never run `occ talk:signaling:list`, it prints the secret). Nextcloud setup check for
  the high-performance backend is green. Rotation recipe: new value from `openssl rand -hex 32` sent over ssh through
  stdin to `talk-secrets.env`, `docker compose up -d` there, then the same value into `spreed signaling_servers` on
  the Nextcloud host (about 45 s without Talk signaling). TURN_SECRET and INTERNAL_SECRET were not rotated.
- Open after the upgrade:
  - notify_push: app 1.4.2 = newest; the push server runs the binary shipped inside the app (also 1.4.2). The self-test
    failed right after the upgrade ("invalid digit found in string") only because it ran during the 8-minute HAProxy outage
    (the push server calls Nextcloud through the public URL via HAProxy and got the HTML 503 page). Re-run at 12:2x UTC after the
    fix: all 6 checks pass. Lesson: run `occ notify_push:self-test` only when the public URL answers.
  - Server info warning "Buffer cache hit ratio 97.3% (aim 99%)": cumulative since the DB started on 2026-11-03; live
    shared_buffers is already 1GB; last 90 s was 100%. The nightly dumps are NOT the cause (oc_filecache had 121M block
    reads vs about 128k per dump). Thanh decided to watch it. If it stays low: snapshot pg_stat_database blks_hit and
    blks_read now and after a night (do NOT run pg_stat_reset(), autovacuum uses those counters), consider pg_stat_statements.
  - SMB mounts: /scan-ho FIXED (below). /vtrm works through the smbclient binary on Linux Samba (`lost+found`
    AccessDenied noise in the log). Why the others fail: 172.16.10.77 is Linux Samba; 192.168.100.4 is a Windows PC that cannot
    answer the share-root stat; 192.168.1.249 (/hcmc-tamada, share Japanese/Tama Ino) and 172.16.10.157 (/tamada-it, share
    Tamada$, folder IT, domain kinden.com.vn) are reachable on 445 but reject logins. /hcmc-tamada: credentials file
    `~/.smb-credentials-hcmc-tamada` exists on .247 but the server answers NT_STATUS_LOGON_FAILURE (4 attempts, stop retrying
    to avoid an account lockout); needs the exact Windows account name and domain from Thanh. /tamada-it: no password, skipped.
  - The Settings hint "The php-smbclient library provides improved compatibility and performance" is expected while the
    `/dev/null` override keeps the PHP extension off (only /vtrm uses SMB code now).
  - Checked 2026-11-24: every store app we had installed has a 35 release (app store API), and `occ setupchecks` shows
    no warnings apart from the items above and "Database configuration" (the cache hit ratio one).
- Backups: backup.sh (2 min, maintenance mode) and stack-back (40 s) both write to the SAME root disk as the live data.
  New: `~/dropbox_uploads/nextcloud` on Thanh's PC pulls the newest backup/ folder daily at 02:00 (Vietnam), zips, age-encrypts
  and uploads to Dropbox `/nextcloud` (Healthchecks ping). From 2026-11-24 the nightly file `nextcloud.tar.zip.age` holds only
  the DB dump, roles, config and themes (about 350 MB); `custom_apps.tar.gz` goes weekly (Sundays) as
  `nextcloud-apps.tar.zip.age` (about 295 MB); `FORCE_APPS=1 ./pull_server.sh` forces it. After a restore from a mid-week file the app
  code can be older than the DB: run `occ app:update --all`. The job lives on Thanh's PC (`~/dropbox_uploads/nextcloud`), not in git. User files (639 GB on the separate disk /dev/sdb1) have no backup
  that Claude could see; Thanh says it is handled. Restore from Dropbox needs the age private key (restore tested once).
- restore.sh fixed: roles (db_globals.sql.gz) are restored before the database dump.
- /scan-ho via a host mount (option D, done 2026-11-24 04:31 UTC): on .247 `cifs-utils` installed, `/etc/fstab` line
  `//192.168.100.4/admin /mnt/scan-ho cifs credentials=/etc/smb-credentials-scan-ho,uid=33,gid=33,file_mode=0660,dir_mode=0770,vers=3.0,noperm,_netdev,nofail`,
  credentials file root-only (the account has an EMPTY password). docker-compose.override.yml binds `/mnt/scan-ho:/mnt/scan-ho:rshared`.
  Nextcloud mount 19 (SMB, broken) was deleted and replaced by mount 20 (Local, datadir /mnt/scan-ho, user truong-quang-thanh,
  filesystem_check_changes 1). Verified: 19 files visible, no errors. Recreate took 13 s including `docker restart internal-haproxy`.
  Optional: add `x-systemd.automount` to the fstab line so the share is retried if the Windows PC was down at boot.
  To do the same for /hcmc-tamada and /tamada-it: create a root-only credentials file per share (Thanh types the password,
  Claude never sees it), fstab line, one more volume line, one recreate.

## 2026-11-20 to 23: BentoPDF and check-in routing in haproxy.cfg (Thanh, other session; committed 2026-11-24)
These edits were made on the server only and committed from there on 2026-11-24 (the running container uses the same file).
Written from the file's own comments and the diff: Thanh please correct if wrong. Server copies before each step:
`haproxy/haproxy.cfg.bak-*` on .247 (untracked).
- `/pdf/` now goes to `bentopdf_backend` = BentoPDF on 172.16.10.57:8084 (client-side PDF tools, built with BASE_URL=/pdf/),
  not to `stirlingpdf_fat_backend` any more (that backend stays defined, unused). Visitors without a Nextcloud login cookie are
  redirected to https://cloud.kinden.com.vn/login.
- `/checkins-hcm/` goes to `checkin_hcm_backend` = 192.168.1.18:3000 (combined viewer+listener, HCM instance with
  ROUTE_PREFIX=/checkins-hcm, host "atthcm"), same login redirect; the backend sets `X-Real-IP` to the client address.
- `checkin_viewer` backend moved from 172.16.10.57:3000 to 172.16.10.198:3000 (check-in cutover of 2026-11-23).

## 2026-11-20: 504 bursts fixed, Nextcloud 34.0.6, Collabora instead of ONLYOFFICE
Details, commands and evidence: docs/previews-and-504.md. Short version:
- Cause of the 504 bursts: ~200 `/core/preview` requests in minutes, each waiting on ONLYOFFICE thumbnails
  (+ no Ghostscript) until Apache hit MaxRequestWorkers 150. Fixed: ghostscript in the image, ONLYOFFICE `preview`
  off, preview providers trimmed, concurrency limits (occ config, not in git).
- Nextcloud is 34.0.6 (the image rebuild pulled it; not rehearsed). calendar_resource_management went 0.12.2 to 1.0.0
  by the app store during the upgrade: room booking still to be re-tested.
- HaRP: image `:release` (0.4.8). Its FRP certificates had expired on 2026-11-07; regenerated (valid to 2040).
- Office editor: Nextcloud now uses Collabora (richdocuments) on 172.16.10.57, image `kdvn/collabora:26.04.6.1.1-jp`
  (project `~/collabora` there). HAProxy `collabora_backend` points to it. ONLYOFFICE app is DISABLED, container
  `onlyoffice` on 172.16.10.57 is STOPPED but kept for rollback (see memory/previews doc). ONLYOFFICE 10.0.0 was
  trialled and stopped: vanilla 10.0.0 is view-only on mobile; Thanh will not use it until the licence is clear.
- Public share links: no switch to force download; a read-only public xlsx opens in Collabora view mode (fast).
- Host cron (UTC; Vietnam = +7): backup.sh 18:00, preview jobs, ClamAV at 20:00/20:30. The old 02:00 job used the
  legacy backup-nextcloud.sh and wrote CRLF-corrupted dumps; replaced.

## Versions
- UPDATE 2026-11-20: production now runs Nextcloud 34.0.6 (see docs/previews-and-504.md).
- Production ran Nextcloud 34.0.5 (checked on the server 2026-10-26: not in maintenance
  mode, no DB upgrade pending). The image history shows 32.0.15, then 33, then 34, all
  built on 2026-10-23. Earlier note said "from 32.0.3"; the upgrade date is therefore
  about 2026-10-23 (Thanh: confirm).
- UPDATE 2026-11-24: production runs Nextcloud 35.0.3 (34.0.5 to 34.0.6 to 35.0.3). Upgrade plan of Thanh (2026-11-13) is
  done: one major at a time, rehearsed on a test server (172.16.10.57, now deleted), fresh backup before. The SMB problem
  was NOT fixed by the 35 image (same Samba 4.22.11); worked around with a host mount for /scan-ho, see above.
- Calendar Resource Management: 0.12.2 installed (checked 2026-10-26). 1.0.0-rc.2 is the
  release for Nextcloud 34 (its tables are unchanged from 0.12.2); upgrading it is TODO.
- Custom image in use: nextcloud-app runs kdvn/nextcloud-app:35, built from
  Dockerfile/nextcloud-app/Dockerfile via docker-compose.override.yml (NC_TAG 35.0.3-apache, an exact tag on purpose).
  Base docker-compose.yml still says nextcloud:32.0.15, which the override replaces (still to be cleaned up). A
  recreate of nextcloud-app still needs a fresh backup. The 34, 33 and 32.0.15 images are still on .247 (34 = rollback).
- Apps as found on the server on 2026-10-26 (since upgraded, see next item): kdvn_rooms 0.1.3 and kdvn_visitors
  0.4.6, both declaring Nextcloud 30 to 34. Whether the 0.4.1 to 0.4.6 features work
  (readable room names, auto check-out, newest-first lists, readable errors, reception-office
  setting, display layouts) is not tested yet: see Phase 1 tests.
- Apps as of 0.5.2 / 0.2.0 (superseded by 0.6.0 / 0.2.1 below, deployed 2026-10-31):
  - kdvn_rooms 0.2.0: admins and the kdvn_visitors reception group can manage rooms (top-bar
    page plus every API call, via Service/Access.php). Delegating the old admin settings page
    to a group no longer grants API access. Needs the reception_group setting of kdvn_visitors.
  - kdvn_visitors 0.5.2: walk-in form with required-field marks and a purpose drop-down; accent-
    insensitive host search (needs PHP `intl`, present in production); phone layout of the staff
    page; Talk message to the inviter; the Talk reception account is searchable as a host;
    "guest comes early" display message (0.4.x); External sites icons for the mobile app.
  - Deployed, but not yet tested by Claude on the live server (Thanh's Phase 1 tests pending). Open: whether Visitors opens
    inside the Nextcloud mobile app without a new login (test on one phone).
- Earlier checks by Claude were only lint, API checks against stable32/34 and replayed
  scenarios with Sabre VObject; nothing was verified on a live server by Claude.

## Open items
- SECURITY: the old n8n workflow Booking_Rooms.json exposed an API token and had SQL
  injection. Revoke the token, deactivate the webhook, remove or limit the "NC Readonly"
  database user. TODO: confirm done. Never copy that file's contents into git.
- Reverse proxy: check trusted_proxies and forwarded_for_headers in config.php behind
  internal-haproxy and Cloudflare (rate limits need real client IPs). TODO: confirm set.
- Maintenance window: TODO: confirm.
- The meeting room that could not be booked: see custom_apps/kdvn_rooms/CLAUDE.md pitfalls.
- After deploying 0.4.5: untick every office except head office under Visitor reception and
  save (until saved, all offices count).
- Retention and consent wording with HR/legal. IT contact in the reception plan.
- Test plan Phase 1 not run yet (Thanh is starting careful testing).
- Collabora (2026-11-20): test two users on one file and editing on a phone; check the CODE (Development Edition)
  licence terms for ~430 users (Thanh does this); Windows Japanese font names (MS Mincho, MS Gothic, Meiryo, Yu *)
  still fall back to Noto Sans SC (Chinese glyph shapes); fonts uploaded in the Office app work (remote_font_config).
- Re-test room booking after calendar_resource_management 1.0.0 (tables calresources_* must stay unchanged).
- Check the first runs of the new crons: ~/logs/nextcloud-backup.log (backup.sh, maintenance mode ~3 min at 01:00 VN)
  and ~/logs/preview-generate-all.log (one-time, 2026-11-20 18:30 UTC).
- Port 9980 on 172.16.10.57 is open to the LAN. A DOCKER-USER rule failed once and caused an outage: retry only with a
  self-undoing command (docs/previews-and-504.md).
- Setup checks still open: missing DB indices (`occ db:add-missing-indices`), Talk expensive repair steps. ExApp
  test-deploy fails to enable (old frpc cert; test app only). Unused: ONLYOFFICE container/image/volumes and the
  `/onlyoffice/` HAProxy route (503 while stopped): remove when the rollback is no longer needed.
- Not decided: large-file upload block (Flow rule with files_accesscontrol: untested); default quota is 5 GB.
- Direct login block (done 2026-11-20, commit 5ebfed0): haproxy.cfg denies `/login?direct=1` and POST `/login` unless the
  request comes from the front HAProxy (172.16.10.129) and the last X-Forwarded-For entry is 192.168.100.38 (admin
  desktop). Break-glass = local `admin` (password + TOTP; disabled in Authentik on purpose). From the desktop the name
  must resolve to 172.16.10.129 (hosts entry, browser restart, secure DNS off), because via the public name the office
  appears as 101.99.23.25 (hairpin NAT). Rollback: haproxy/haproxy.cfg.bak-2026-11-20 on the server, then recreate
  internal-haproxy. Open: remove the temporary hosts line when not needed; ask the colleague who manages the front
  HAProxy about passing the real client IP (X-Real-IP is overwritten here with the front's address, so rate limits
  and brute-force protection see one IP; see the reverse proxy item above); decide whether to allow more admin IPs;
  WebDAV/desktop clients with real passwords are not blocked.
- Group clean-up (started 2026-11-20): make Authentik the single source of truth for all `SAML_*` groups. SSO is not
  enforced yet (mixed SSO and local logins; 339 Database vs 88 user_saml users), and user_saml only syncs groups when a
  user logs in via SSO, so Nextcloud groups drift. Example: `SAML_Japanese` had 22 members (5 disabled, UI shows 17)
  vs 8 in Authentik; yusuke-tamada added in Authentik on 2026-11-20 (needs a fresh SSO login to sync). Needs, per
  group: Authentik member list (usernames) to diff against `occ group:list`; then decide per extra member (add in
  Authentik or remove with `occ group:removeuser`, backup first). Also decide: enforce SSO-only, and whether groups
  used for access (shares, group folders, Talk) should be plain Nextcloud groups instead of `SAML_*`. Check the
  `Tamada` uid (possible duplicate of yusuke-tamada) and `Japanese HAN` (12 members) vs `SAML_Japanese`.

## SMB external storage broken after the 34 image (found 2026-11-13)
Status 2026-11-13: workaround applied on the server (PHP smbclient extension off). /vtrm works again.
/scan-ho (mount 19) still fails and waits for a decision (see "Open decision" below).

- Symptom: after the upgrade every SMB/CIFS external storage failed (`occ files_external:verify <id>`
  printed `Bad SMB2 (sign_algo_id=N) signature for message`, with an all-zero signature in the first
  dump). The edit form in the web UI has no Save button; the blue "Edit" button at the bottom is it.
  Everything can be done with `occ files_external:*` (list, config, option, applicable, verify,
  create, delete, export, import).
- Cause found: the image is now Debian 13 with Samba 4.22.11 (libsmbclient, smbclient) and PECL
  `smbclient` 1.2.0dev. Nextcloud's SMB library (icewind/smb, `Native/NativeState.php` line 111) always
  sets `SMBCLIENT_OPT_ENCRYPT_LEVEL` to `SMBCLIENT_ENCRYPTLEVEL_REQUEST`. A small PHP test against
  192.168.100.4 gave: encrypt NONE works, REQUEST fails, REQUIRE fails (errno 13). The `smbclient`
  command line works on the same share with the same login. So it is not the servers, the
  credentials, the mount config, nor the signing algorithm.
- Dead ends (do not repeat): re-entering the password, deleting and recreating the mount,
  `client smb3 signing algorithms = AES-128-CMAC HMAC-SHA256` in a mounted /etc/samba/smb.conf (error
  then showed sign_algo_id=1 but still failed), Samba debug log level 3 (nothing useful), capping the
  protocol (PHP test with encryption REQUEST and max protocol SMB3_02, SMB3_00 and SMB2_10: all fail,
  so a `client max protocol` in smb.conf cannot fix it). An smb.conf setting cannot override the
  encryption level that the extension sets through its API.
- Workaround, applied 2026-11-13 (docker-compose.override.yml, commit 35155d2): an empty file is
  bind-mounted over `/usr/local/etc/php/conf.d/docker-php-ext-smbclient.ini`. Without the PHP extension
  Nextcloud uses the `smbclient` binary, which asks for no encryption (`Wrapped/Server.php` only passes
  min/max protocol). Check: `php -m | grep -i smbclient` prints nothing, `which smbclient` prints
  /usr/bin/smbclient. The Settings page now shows "The php-smbclient library provides improved
  compatibility and performance": expected. Slower, and it affects every SMB mount. Remove the line when
  the extension works again.
- Results after the workaround: /vtrm (mount 3) is green in the UI. /scan-ho (mount 19) fails:
  `verify` says `NT_STATUS_OBJECT_NAME_INVALID for /` and the Files app shows "External mount error".
  Reason: the command-line backend cannot stat the root of that Windows share (`stat('')` and `stat('/')`
  fail; listing the share works, 29 entries). Plain `smbclient -c 'allinfo ...'` on the root gives
  `NT_STATUS_OBJECT_NAME_NOT_FOUND getting alt name for \` or INVALID for `.`, so no other spelling
  helps. The share root is a flat scan folder (PDFs only, no subfolders), so a Remote subfolder on the
  same share is not possible.
- SMB mounts (ids from `occ files_external:list`): 19 /scan-ho (Thanh's own test mount, keep), 3 /vtrm
  (works now), 6 /hcmc-tamada and 17 /tamada-it (HCMC server, skipped by Thanh, not re-checked; they
  returned an empty message before the workaround). 15 was a duplicate of 19 and is deleted. Mount 4
  /kdvn_db is Local storage, not SMB, and did not work before the upgrade either: ignore.

### Open decision: /scan-ho (waiting for answers from the Windows PC at 192.168.100.4)
Questions to ask: (1) the local path of the "Admin" share on that PC (for example D:\Scan); (2) whether the
account used (tqhanh) is an administrator on that PC (needed for C$).
- A. Windows side: share the parent folder (or use C$ if admin), then `occ files_external:config 19 share
  <share>` and `... root <path to the scan folder>`. The mount root becomes a normal subfolder, so no share
  root is stat'd. No downtime, reversible. Not proven yet: first test that `stat` of a real subfolder works
  through the command-line backend (read-only PHP script with `Icewind\SMB\ServerFactory`, as used on
  2026-11-13). With C$ Nextcloud gets admin access to that drive (Remote subfolder limits what is shown).
- D. Host mount: mount the share on the Docker host with the kernel CIFS client (it handles encryption)
  and bind it into nextcloud-app as local storage, like /kdvn_db. Host-level change plus a volume line in the
  override: recreate of nextcloud-app (backup, approval, downtime).
- B. Fix the image on the 35 upgrade: try another PECL `smbclient` version (1.2.0dev may be at fault, not
  sure) or another Samba version, rehearsed on the test server (restore.sh) first, then remove the
  /dev/null line. Needs backup, approval and downtime for everyone.

### Re-test on the 35 image (and whenever the Samba version changes)
Password typed at the prompt, nothing stored. Needs `read -rs` in bash, not sh. The extension is off in the
container while the workaround is in the override, so load it for the test with `-d extension=smbclient.so`
(or remove the /dev/null line first):
```
read -rs -p "Password: " SMBP; echo; export SMBP
docker exec -i -u www-data -e SMBP nextcloud-app php -d extension=smbclient.so 2>&1 <<'EOF2'
<?php
foreach (['NONE'=>SMBCLIENT_ENCRYPTLEVEL_NONE,'REQUEST'=>SMBCLIENT_ENCRYPTLEVEL_REQUEST] as $n=>$l) {
    $s = smbclient_state_new();
    smbclient_option_set($s, SMBCLIENT_OPT_AUTO_ANONYMOUS_LOGIN, false);
    smbclient_option_set($s, SMBCLIENT_OPT_ENCRYPT_LEVEL, $l);
    smbclient_state_init($s, 'WORKGROUP', '<user>', getenv('SMBP'));
    $d = @smbclient_opendir($s, 'smb://<host>/<share>');
    echo "encrypt=$n => ".($d ? 'OK' : 'FAIL errno='.smbclient_state_errno($s)).PHP_EOL;
}
EOF2
unset SMBP
```
If REQUEST works on the new image, remove the /dev/null line from the override and verify every SMB mount.
If not, fix it in the Dockerfile (nextcloud-app), rehearsed on the test server first. Do not edit vendored
PHP inside the container. To read the real error behind an empty `verify` message, the log is
/mnt/ncdata/nextcloud.log (`occ log:file`); `/var/www/html/data/nextcloud.log` is an old file.

## Offices (kdvn_visitors 0.6.0, kdvn_rooms 0.2.1): deployed 2026-10-31
- Deployed to production on 2026-10-31 (backup.sh at 11:06, files copied with docker cp, `occ upgrade`
  successful, migration added building_id; 16 of 31 old visits got an office from their room).
  Thanh's first checks: "so far so good". Full Phase 1 and cross-office tests still to do.
- Each visit has an office (building). Per-office reception group, Talk "Reception desk" conversation
  and tablet link; reception sees only its own office's guests; anyone can invite to any office
  (new Office field). Reception desk also gets a Talk message when an invited guest arrives.
- The Talk sender is now a bot account (hidden from the host search); more service accounts can be
  hidden by group. The 0.5.2 "reception account is searchable" behaviour is gone.
- Settings to finish in Administration settings > Visitors (see docs/deploy-apps.md step 6): office
  reception groups, hidden groups, a tablet link per office (the old link works as "no office").
- Not tested by Claude on the live server; the 15 old visits without an office are open to every reception.

## Office sensors (kdvn_sensors 0.2.0, kdvn_visitors 0.6.2): deployed 2026-11-14 and 2026-11-15
- kdvn_sensors: public display page (secret link, `&building=ID`, `&lang`, `&theme`) with one block per sensor, and an
  admin page: data source (Off or Mock), a list of sensors, default thresholds, display link. A sensor has a name, an
  office (a Calendar Resource Management building, read-only from calresources_buildings), the metrics it measures,
  a point name at the source, "show on the room display" and optional own thresholds (e.g. server room). At HO the
  plan is "Office area" and "Server room"; other offices follow by adding rows. With no list saved, one implicit sensor
  "Office" is shown.
- kdvn_visitors: `&sensors=1` on a room display link (tick "Show the indoor sensor strip" in the Room displays link
  builder) shows the header strip with that screen's office sensors that have "show on the room display".
- Deployed in two steps (0.1.0/0.6.1 on 2026-11-14, 0.2.0/0.6.2 on 2026-11-15). Thanh confirmed "it worked" and "so far so
  good". Each version bump needed `occ upgrade`, which also updated store apps (forms, memories, nc_gantt, notes,
  passwords, spreed 24.0.8, then nc_gantt 2.5.0). The list before the second upgrade is in
  /tmp/apps-before-sensors-0.2.txt on the server (may be lost on reboot).
- The sensor values are still MOCK (fake). Do not show `&sensors=1` on a screen staff will see, and do not describe
  the Mock source as real, until the supplier provider exists.
- Supplier (Azbil) answered on 2026-11-19: see docs/plan-sensor-display.md section 7 (REST GET /api/points/values,
  X-API-Key header, one sensor per room, no rate limit, NA when offline or stale). Still open: IP, port, HTTP or
  HTTPS, the point list (comes with the budget). Not built: weather fallback.
- TODO: check that Talk Reception desk messages still arrive after Talk updated to 24.0.8.

- kdvn_sensors 0.3.0 (code written 2026-11-19, NOT deployed, not tested against the real service): supplier provider
  from the sample JSON, admin fields, point names per metric, optional fan mark, CO2 default bands 600/1000 from the
  Azbil diagram E-14. Parser unit-tested with the sample (17 checks). Deploy notes: docs/deploy-apps.md (needs
  `allow_local_remote_servers`, a firewall rule, `occ upgrade`). The fan is controlled by Azbil's DDC, not Nextcloud.
  Questions to send Azbil: end of docs/plan-sensor-display.md.
- (2026-11-25) Azbil proposes dropping SVC and savic-net G5 (DDC data via the BACnet Service only). Impact on kdvn_sensors,
  history and alerting, and the new questions for Azbil: end of docs/plan-sensor-display.md. Decision pending.

## Hardware plan
- Reception tablet: iPad 10th generation or newer (or Galaxy Tab A9+ with Fully Kiosk).
- Room display: MSI Pro MP165 E6 (16 inch, 1920x1080, chosen 2026-11-27) as plain monitor plus Raspberry Pi 5 2 GB running
  Chromium kiosk with `layout=condensed` (setup: docs/pi-display-kiosk.md).

## Next
- (2026-11-20) Finish the Collabora checks above, then decide what to do with the stopped ONLYOFFICE.
- SMB external storage: workaround applied; /scan-ho waits for the answers from the Windows PC (see "Open
  decision" in the SMB section), then re-test on the 35 image.
- Indoor sensors: waiting for the supplier answers (docs/plan-sensor-display.md section 6), then write the
  supplier provider. Meanwhile keep `&sensors=1` off staff-facing screens (mock values).
- Re-run the quick re-test of the apps on 34 (docs/deploy-apps.md step 6) and note the result.
- kdvn_visitors 0.6.0 and kdvn_rooms 0.2.1 are deployed: run Phase 1 tests, set reception offices, then a go-live dry run on site before the office
  move.

## Visitors 0.6.3: tablet name search ranking (deployed 2026-11-15)
- Thanh could not find his own name ("Truong Quang Thanh") by typing "Thanh": results were capped at 8, sorted
  alphabetically among equal scores, and nothing said more people matched.
- Fix (`StaffDirectory::score()`): the LAST word of the name ranks first (Vietnamese given name; Japanese staff are
  stored "Given Family" and searched by family name, also the last word), then a word start, then anywhere.
- The tablet shows "More people match, type another word of the name" when there are more than 8 matches
  (`more` flag from `KioskController::hosts`, text in kiosk.js in vi/en/ja).
- Deployed 2026-11-15: backup.sh (14:01) and stack-back snapshot first, then git pull, docker cp, chown, `occ upgrade`
  (no store apps updated this time). Still to do: hard-refresh the iPad and test "Thanh". The reception invite search
  (`VisitApiController`, limit 8) is unchanged.

## Before upgrading to Nextcloud 35: PHP `intl` and Visitors name search
- Visitors 0.5.0 (`custom_apps/kdvn_visitors/lib/Service/StaffDirectory.php`) ignores accents in
  the "Who are you visiting?" search. It uses PHP's `intl` Transliterator when present, and falls
  back to a Vietnamese letter table when not.
- The PHP on the dev machine has no `intl`, so only the fallback was tested here. Thanh checked
  production on 2026-10-27: `intl` IS installed in nextcloud-app, so the full transliterator is
  used. Re-check the same way after the image change for 35:
  `docker exec -u www-data nextcloud-app php -m | grep -i intl`
- If `intl` is ever missing: the fallback covers Vietnamese only (other accents, e.g. Japanese romaji
  or "ü", are not folded). Install `intl` through the Dockerfile (nextcloud-app image), which
  needs a rebuild and recreate of nextcloud-app, so take a fresh backup first (CLAUDE.md).
- On the 35 upgrade: check the Nextcloud 35 admin manual for its PHP version and required
  extensions, make sure `intl` is on the list for the new image, and re-test the walk-in host
  search with "nguyen van a" against a user named "Nguyễn Văn A".
- SMB external storage: on the 35 image check the Samba version (`docker exec nextcloud-app smbclient
  --version`) and run the encryption re-test from the SMB section above.
- Also re-check the OCP calls added in 0.5.0 against stable35:
  `IUserManager::searchDisplayName('')` (empty pattern = all users) and `ICacheFactory`.

## Cowork-only files to export into docs/
- "Visitors and Meeting Rooms: Test and Upgrade Plan" (test cases R1-R12, V, T, W, P, C, D,
  J, S, upgrade rehearsal, production runbook, reception contingency plan)
- claude_incident-2026-10-15-talk-hpb-invalid-token.md
- claude_authentik-migration-runbook.md
- claude_sitepin-app-design.md (SitePin app design, not built)
- KDVN_Logo_cropped.png (the Dockerfile is exported already)


## 2026-11-28: tablet second tab, rooms moved to Settings with Excel import (uncommitted, not deployed)
- kdvn_visitors: optional second tab on the reception tablet (default name "Book a room"), framed inside the kiosk page because
  the iPad is in kiosk mode. Admin: Visitors settings > Second tab. Tested in a browser harness (tab switch, frame not reloaded,
  return to Visitors). No route change: file copy only.
- kdvn_rooms 0.3.0: top-bar entry and page removed (only Administration settings > Meeting rooms; non-admin reception staff need
  Administration privileges delegation). New: Import from Excel (.xlsx/.csv) with template download and a preview. New routes:
  deploy needs `apachectl -k graceful` (see deploy-apps.md). Importer logic tested on a real openpyxl file; SheetWriter (template) not
  yet tested because the local PHP lacks zip: open the downloaded template in Excel after deploy.
