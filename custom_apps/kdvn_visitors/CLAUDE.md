# kdvn_visitors

Guest invitations with QR pass, reception tablet check-in, walk-ins, reception desk, Talk
alerts, passes created from Calendar, and public meeting-room displays.

## Rules
- Public pages need no login: /apps/kdvn_visitors/pass/{token}, /kiosk/{key} (reception
  tablet), /display/{key} (room screens), /logo. Kiosk and display keys are separate
  secrets (appconfig kiosk_key_hash, display_key_hash). Never log or print them.
- Rate limits need real client IPs: trusted_proxies and forwarded_for_headers must be set
  behind internal-haproxy and Cloudflare. TODO: confirm set.
- Talk is used only through its OCS REST API (stable across Talk versions), never Talk's
  internal PHP.
- No SVG logo upload (scripts would be served from our domain). PNG, JPEG or WebP up to 2 MB.

## How it works
- Table oc_kdvnv_visits (migrations in lib/Migration). Pass token stored as sha256 plus an
  ICrypto-encrypted copy; a 6-character code can be typed at the tablet. Settings in
  oc_appconfig.
- Talk: TalkService calls the REST API at the public URL as a reception user (appconfig
  talk_user, talk_password encrypted, optional talk_base_url such as http://localhost).
  Hosts get 1:1 messages; walk-ins and problems go to the group conversation
  "Reception desk". Fallback: Nextcloud notification.
- Email uses Nextcloud's SMTP (IMailer). Calendar's invitation email and the visitor pass
  email are separate messages. The pass email is sent by queued job SendInvitationJob via
  the host crontab (cron.php every 5 minutes).
- Calendar to passes: CalendarListener on calendar object events calls CalendarSync
  (organizer's own copy only; rooms in buildings listed in reception_buildings; outside
  guest = not a room, not a Nextcloud user, not on the organizer's email domain).
  Recurring meetings create no passes.
- Room display: DisplayService reads oc_calendarobjects and parses with Sabre VObject,
  30 s cache. "Book the room in Calendar" creates an event with the room as ROOM attendee
  in the host's calendar. It replaced the n8n "Booking_Rooms" webhook.

## Decisions
- Display is built inside Visitors, not n8n: the n8n flow had SQL injection via
  `?building=`, missed recurring and UTC events, and showed private titles.
- The tablet is a secret URL key, not a logged-in account: a logged-in public tablet can
  be tapped out of the kiosk page.
- Passes only for rooms in offices ticked "Visitor reception" (the calendar is company-wide
  and other offices have no tablet). Auto-pass mode defaults to "room" so online meetings
  create no passes.
- Display defaults: English, company names only, real titles (private events show
  "Booked"), 5 working days. Layout per screen: `condensed` for a 15-inch monitor,
  `timeline` for big screens.
- Retention 90 days with a consent checkbox for walk-ins; auto check-out 60 minutes after
  the visit ends. TODO: confirm wording and period with HR/legal.
