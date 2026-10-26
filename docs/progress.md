# Nextcloud stack and apps: status (dated 2026-10-26)

## Versions
- Production runs Nextcloud 34.0.5 (checked on the server 2026-10-26: not in maintenance
  mode, no DB upgrade pending). The image history shows 32.0.15, then 33, then 34, all
  built on 2026-10-23. Earlier note said "from 32.0.3"; the upgrade date is therefore
  about 2026-10-23 (Thanh: confirm).
- Calendar Resource Management: 0.12.2 installed (checked 2026-10-26). 1.0.0-rc.2 is the
  release for Nextcloud 34 (its tables are unchanged from 0.12.2); upgrading it is TODO.
- Custom image in use: nextcloud-app runs kdvn/nextcloud-app:34, built from
  Dockerfile/nextcloud-app/Dockerfile via docker-compose.override.yml (NC_TAG 34-apache).
  Base docker-compose.yml still says nextcloud:32.0.15, which the override replaces. A
  recreate of nextcloud-app still needs a fresh backup.
- Apps deployed on the server (checked 2026-10-26): kdvn_rooms 0.1.3 and kdvn_visitors
  0.4.6, both declaring Nextcloud 30 to 34. Whether the 0.4.1 to 0.4.6 features work
  (readable room names, auto check-out, newest-first lists, readable errors, reception-office
  setting, display layouts) is not tested yet: see Phase 1 tests.
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

## Hardware plan
- Reception tablet: iPad 10th generation or newer (or Galaxy Tab A9+ with Fully Kiosk).
- 15-inch screen as plain monitor plus Raspberry Pi 5 2 GB running Chromium kiosk with
  `layout=condensed`.

## Next
- Re-run the quick re-test of the apps on 34 (docs/deploy-apps.md step 6) and note the result.
- Run Phase 1 tests, deploy 0.4.6 and set reception offices, then a go-live dry run on site
  before the office move.

## Cowork-only files to export into docs/
- "Visitors and Meeting Rooms: Test and Upgrade Plan" (test cases R1-R12, V, T, W, P, C, D,
  J, S, upgrade rehearsal, production runbook, reception contingency plan)
- claude_incident-2026-10-15-talk-hpb-invalid-token.md
- claude_authentik-migration-runbook.md
- claude_sitepin-app-design.md (SitePin app design, not built)
- KDVN_Logo_cropped.png (the Dockerfile is exported already)
