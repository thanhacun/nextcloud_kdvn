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
  talk_user is a bot account (never a real person; hidden from the staff search).
  Hosts get 1:1 messages; arrivals, walk-ins and problems also go to the "Reception desk" group
  conversation of the visit's office (one per reception group, token by group in
  reception_room_tokens). Fallback: Nextcloud notification.
- Offices: an office = a calresources building. Every visit has building_id (null = unknown, open
  to every reception). Reception group per office: appconfig office_groups (building id => group),
  fallback reception_group. Access check is VisitService::canHandle(); never list or act on visits
  without it. Office comes from the room (CalendarSync, invite with room), the tablet key
  (kiosk_keys, per building; legacy kiosk_key_hash = no office) or the invite form's officeId.
  office_staff_groups only preselects the office in the invite form. kdvn_rooms Access.php reads
  reception_group and office_groups.
- Email uses Nextcloud's SMTP (IMailer). Calendar's invitation email and the visitor pass
  email are separate messages. The pass email is sent by queued job SendInvitationJob via
  the host crontab (cron.php every 5 minutes).
- Calendar to passes: CalendarListener on calendar object events calls CalendarSync
  (organizer's own copy only; rooms in buildings listed in reception_buildings; outside
  guest = not a room, not a Nextcloud user, not on the organizer's email domain).
  Recurring meetings create no passes.
- Room display: DisplayService reads oc_calendarobjects and parses with Sabre VObject,
  30 s cache. Link option `&sensors=1` (tick in the link builder) adds a `sensors`
  list (this office's room-display sensors) from kdvn_sensors (DisplayController::sensorReadings(), after the room cache, null on any failure) and
  the header strip in display.js. "Book the room in Calendar" creates an event with the room as ROOM attendee
  in the host's calendar. It replaced the n8n "Booking_Rooms" webhook.
- Touch display: /touch/{key} (TouchController, own key touch_key_hash, own "Create touch links" button) reuses display.php and
  display.js with data-touch="1"; the read-only /display/{key} never gets the buttons. Who books is proven, not typed:
  claim (QR) -> logged-in phone page /confirm/{nonce} (ConfirmController, user from the session) -> screen collects a TouchTicket
  (HMAC, 60 s, once) -> /book redeems it -> CalendarBooking::book for that user, 15/30/60 min from now, room re-checked on the
  server, DisplayService::forgetCache() afterwards. Claims and used tickets live in the distributed cache. A face check is the
  same thing without the phone: its endpoint must issue a ticket (contract in docs/progress.md; the uid is the Odoo login without the
  domain part); the UI for it is in display.js
  and shows only when the page has data-face-url (set when appconfig face_enabled, face_url and the encrypted face_key
  are set: TouchController::face relays the JPEG through FaceRelay to Odoo and never stores it or logs it; the Odoo side is a
  separate task in ~/odoo19_thcn). Never put the face key in git, chat or logs.

## Decisions
- Touch booking has no typed names and no shared login: only a ticket from a verified identity books a room. Do not add a
  "type your name" path back: anyone could then book in any colleague's name (the old first version did, and was dropped).
  The audit line in nextcloud.log ("touch booking: room N by uid") shows who. Someone photographing the QR can book a room for
  themselves, no more (2 minute code).
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
