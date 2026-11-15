# Nextcloud stack and apps: status (dated 2026-11-13)

## Versions
- Production runs Nextcloud 34.0.5 (checked on the server 2026-10-26: not in maintenance
  mode, no DB upgrade pending). The image history shows 32.0.15, then 33, then 34, all
  built on 2026-10-23. Earlier note said "from 32.0.3"; the upgrade date is therefore
  about 2026-10-23 (Thanh: confirm).
- Upgrade plan (Thanh, 2026-11-13): 34.0.5 to 34.0.6, then to 35. One major at a time, rehearsed
  first on a test server built with restore.sh, fresh backup (backup.sh plus stack-back) before
  each step. The image change for 35 is the chance to fix the SMB problem below properly.
- Calendar Resource Management: 0.12.2 installed (checked 2026-10-26). 1.0.0-rc.2 is the
  release for Nextcloud 34 (its tables are unchanged from 0.12.2); upgrading it is TODO.
- Custom image in use: nextcloud-app runs kdvn/nextcloud-app:34, built from
  Dockerfile/nextcloud-app/Dockerfile via docker-compose.override.yml (NC_TAG 34-apache).
  Base docker-compose.yml still says nextcloud:32.0.15, which the override replaces. A
  recreate of nextcloud-app still needs a fresh backup.
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

## Office sensors (kdvn_sensors 0.1.0, kdvn_visitors 0.6.1): deployed 2026-11-14
- kdvn_sensors: public display page with temperature, humidity and CO2 tiles (secret link, 60 s cache),
  admin page (source Off or Mock, location label, thresholds, display link). Only the mock provider exists:
  the supplier BACnet provider waits for the API details (see docs/plan-sensor-display.md). Weather
  fallback not built.
- kdvn_visitors 0.6.1: `&sensors=1` on a room display link shows the sensor strip in the header (tick in the
  Room displays link builder). Thanh confirmed on 2026-11-14 that it works.
- The deploy needed `occ upgrade` (version bump); it also updated forms, memories, nc_gantt, notes, passwords
  and spreed 24.0.8 from the App Store. Check that Talk Reception desk messages still arrive (TODO).
- Multiple sensors per office (kdvn_sensors 0.2.0, kdvn_visitors 0.6.2, built 2026-11-15, not deployed): sensors are
  a list in the admin page (name, office from Calendar Resource Management, metrics, point name, own thresholds,
  show on room display), e.g. "Office area" and "Server room" at HO. A room display with `&sensors=1` shows the
  sensors of its own office. Deploy both apps together (needs `occ upgrade`: version bump).
- Switch the source from Mock to the real provider before showing it to staff: mock values are fake.

## Hardware plan
- Reception tablet: iPad 10th generation or newer (or Galaxy Tab A9+ with Fully Kiosk).
- 15-inch screen as plain monitor plus Raspberry Pi 5 2 GB running Chromium kiosk with
  `layout=condensed`.

## Next
- SMB external storage: workaround applied; /scan-ho waits for the answers from the Windows PC (see "Open
  decision" in the SMB section), then re-test on the 35 image.
- Re-run the quick re-test of the apps on 34 (docs/deploy-apps.md step 6) and note the result.
- kdvn_visitors 0.6.0 and kdvn_rooms 0.2.1 are deployed: run Phase 1 tests, set reception offices, then a go-live dry run on site before the office
  move.

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
