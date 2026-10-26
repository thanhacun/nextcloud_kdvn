# Nextcloud stack and apps: status (dated 2026-10-26)

## Versions
- Production runs Nextcloud 34.0.5, upgraded from 32.0.3 (Thanh: everything worked well).
  Upgrade date: TODO: confirm.
- Calendar Resource Management: installed version TODO: confirm (1.0.0-rc.2 is the release
  for Nextcloud 34; its tables are unchanged from 0.12.2).
- Is the custom image (Dockerfile/nextcloud-app) built and in use? TODO: confirm. It matters
  before any rebuild or recreate of nextcloud-app.
- Apps built: kdvn_rooms 0.1.3 and kdvn_visitors 0.4.6, both declaring Nextcloud 30 to 34.
  Last version confirmed working on the server: kdvn_visitors 0.4.0. Deployment of 0.4.1 to
  0.4.6 (readable room names, auto check-out, newest-first lists, Nextcloud 34 support,
  readable errors, reception-office setting, display layouts): TODO: confirm.
- Earlier checks by Claude were only lint, API checks against stable32/34 and replayed
  scenarios with Sabre VObject; nothing was verified on a live server by Claude.

## Open items
- SECURITY: the old n8n workflow Booking_Rooms.json exposed an API token and had SQL
  injection. Revoke the token, deactivate the webhook, remove or limit the "NC Readonly"
  database user. TODO: confirm done. Never copy that file's contents into git.
- Reverse proxy: check trusted_proxies and forwarded_for_headers in config.php behind
  internal-haproxy and Cloudflare (rate limits need real client IPs). TODO: confirm set.
- Maintenance window: TODO: confirm.
- The meeting room that could not be booked: see apps/kdvn_rooms/CLAUDE.md pitfalls.
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
- Dockerfile/nextcloud-app/Dockerfile and KDVN_Logo_cropped.png
