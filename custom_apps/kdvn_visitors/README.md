# Visitors (kdvn_visitors) – version 0.6

Staff invite guests → guests get a QR visitor pass by email → guests check in at the reception
tablet → the host gets a Talk message. Walk-in guests register on the tablet. Meetings booked in
Calendar with outside guests create visitor passes automatically, and public room displays show
the bookings per building.

## What's new in 0.6
- One app, several offices. An office is a building of Calendar Resource Management. Every visit now
  has an office: from the booked room (Calendar passes and invitations with a room), from the tablet
  link (walk-ins), or from the new Office field of the invite form (default: the office of your
  area group, or the only office with a reception). Anyone can invite to any office.
- Each office has its own reception group (Administration settings > Visitors > Room displays >
  Offices table). Its members see only that office's guests on the Reception tab, can check them
  in/out, and get that office's "Reception desk" Talk conversation (one per group, named
  "Reception desk – <group>"). Offices without a group use the default reception group. Visits of
  unknown office (made before 0.6 without a room) are open to every reception. Admins see all.
  kdvn_rooms 0.2.1: members of any office's reception group may manage meeting rooms.
- One tablet link per office (button in the Offices table). The link from before 0.6 keeps working
  as "no office" (walk-ins go to the default reception group); replace it with an office link.
- The reception desk now also gets a Talk message when an invited guest arrives (before: only when
  the Talk message to the host failed, in which case the message still says to call the host).
- The Talk sender is a bot (the account that sends our other notifications): it never appears in
  the "Who are you visiting?" search. Other service accounts: tick their group under "Hide these
  groups from the staff search".
- Database: new column building_id on oc_kdvnv_visits (migration runs on enabling the update; old
  visits with a room get their office from the room name).

## What's new in 0.5
- Walk-in form on the tablet: required fields carry a red star and a "* Required" legend from the
  start; a missed field shows its own message under it (cleared as the guest types).
- Purpose of visit is a drop-down (Administration settings > Visitors > Walk-in form on the tablet,
  one per line, optional Vietnamese and Japanese wording: `Meeting | Họp | 会議`) with an "Other" box.
  The staff invite form uses the same list. Reception always sees the English wording.
- "Who are you visiting?" ignores accents and capitals ("nguyen van a" finds "Nguyễn Văn A", words in
  any order) and shows up to 8 matches instead of 5. Option to hide most letters (N*** V*** A***);
  the search then needs 3 letters.
- Phone layout of the staff page: full-width Invite button, full-screen invite form with the buttons
  pinned at the bottom, bigger touch targets.
- Talk message to the inviter: right after a manual invitation (not Calendar-created passes) the
  person who invited gets a Talk message with guest, time, room, phone, email, purpose, the 6-letter
  code and the pass link, in their own Nextcloud language. If Talk fails the invitation still
  goes out. If the inviter is the Talk sender account itself, no message is sent.
- Nextcloud mobile app: the Android app's menu lists the "External sites" links (ATT Hanoi, GLPI...),
  not custom apps. Add Visitors there: Administration settings > External sites > add a site named
  Visitors, URL `https://<our domain>/apps/kdvn_visitors/`, for all users. Opening it inside the
  app's browser without a new login is not verified yet: test on one phone.
  How External sites works (read from github.com/nextcloud/external, 2026-10): the mobile apps ask
  `/ocs/v2.php/apps/external/api/v1`; the server filters by language, group and device (decided
  from the app's user agent: Android, iOS, desktop client, else browser) and applies no limit on the
  number of links, so a short list on one Android phone is the client's doing. One entry = one
  device type, so make one entry for iOS and one for Android.
  Icons: upload square PNGs of 16, 24 or 32 px in External sites (SVG also works). The server serves
  `name-dark.png` instead of `name.png` to the Android, iOS and desktop apps, so upload both:
  `img/visitors.png` (white glyph) and `img/visitors-dark.png` (black glyph). If the icon is
  invisible in the app, swap the two files.
- (Changed in 0.6: the Talk sender account is now a bot and is hidden from the host search.)

## What's new in 0.4
- "Book the room in Calendar" on the invite form (ticked by default when a room is chosen): creates
  the meeting in the host's own calendar with the room invited, exactly like booking in Calendar.
  A room that is already taken stops the invitation with a clear message. Moving the event in
  Calendar moves the visit. Cancelling the visit asks the host to delete the event (Nextcloud has no
  public API for apps to delete events).
- Working days per building (Administration settings > Visitors > Room displays). Displays show
  today plus the next 5 working days (&days=N for another number). Default Monday–Friday.
- Room displays are in English unless the link says &lang=vi or &lang=ja.
- Rooms that can't be booked (missing, invalid or shared email) show on the display and in the
  invite form; fix them in Administration settings > Meeting rooms (kdvn_rooms 0.1.1).

## What's new in 0.3
- Calendar → visitor passes. When an organizer books a meeting room in Calendar and invites outside
  guests, each guest gets a visitor pass (separate email, sent by the next cron run, ~5 minutes).
  Moving the meeting updates the pass; removing a guest, a guest declining, or cancelling/deleting
  the meeting cancels it. Manual invitations for the same guest and time are linked, not duplicated.
  Outside guest = not a room, not a Nextcloud user, not on the organizer's email domain.
  Recurring meetings don't create passes yet. Setting: Administration settings > Visitors > Calendar.
- Room displays (replaces the n8n "Booking Rooms" workflow): public screen per building with
  free/busy now and bookings for the next 14 days. Recurring meetings, moved or cancelled occurrences,
  UTC times and all-day events are handled by Nextcloud's own calendar library. Private events show
  "Booked". Guests appear per booking (company names by default). Updates every minute, long lists
  scroll by themselves, dark by default. Link options: &lang=en|ja, &theme=light, &days=7.

## Upgrade from 0.2 (a few seconds of maintenance mode)

    docker cp kdvn_visitors nextcloud-app:/var/www/html/custom_apps/
    docker exec nextcloud-app chown -R www-data:www-data /var/www/html/custom_apps/kdvn_visitors
    docker exec -u www-data nextcloud-app php occ upgrade

Then: Administration settings > Visitors > choose the Calendar and Room display options, Save,
and "Create display links". Open each building's link on its screen.

## What's new in 0.2
- Reception role: a group chosen in settings. Its members get a Reception tab (all guests for a day,
  walk-ins waiting, manual check-in/out, invite on behalf of others) and a shared "Reception desk"
  Talk conversation for walk-ins and check-in problems.
- Tablet welcome screen with greeting, date and time, and three choices: visitor pass, walk-in, check-out.
  Camera only runs while scanning. Back to the welcome screen after 1 minute without a touch.
- Walk-in form: name, company, phone, who they visit (search by name), purpose, consent.
  Reception or the host lets them in from the Visitors page (the Talk message has the link).
- Check-out at the tablet, at reception, or automatically after midnight (office time zone).
- Visit records are deleted after 90 days (configurable).

## Upgrade from 0.1 (a few seconds of maintenance mode)
Run from /home/kdvn_admin/nextcloud-stack:

    docker cp kdvn_visitors nextcloud-app:/var/www/html/custom_apps/
    docker exec nextcloud-app chown -R www-data:www-data /var/www/html/custom_apps/kdvn_visitors
    docker exec -u www-data nextcloud-app php occ upgrade

`occ upgrade` adds the new database columns and registers the hourly housekeeping job.
Then hard-refresh browsers (Ctrl+F5) and reload the tablet page.

## Install (no restart, no downtime)
Run from /home/kdvn_admin/nextcloud-stack:

    docker cp kdvn_visitors nextcloud-app:/var/www/html/custom_apps/
    docker exec nextcloud-app chown -R www-data:www-data /var/www/html/custom_apps/kdvn_visitors
    docker exec -u www-data nextcloud-app php occ app:enable kdvn_visitors

Enabling creates the table `oc_kdvnv_visits` (migration in lib/Migration/).

## Set up (Administration settings > Visitors)
1. Company name and office address (shown in the email and on the pass).
2. Talk sender:
   - Create a user, e.g. `reception`, display name "Lễ tân / Reception". If Talk is limited to
     certain groups, put this user in one of them.
   - Log in as that user, Personal settings > Security > create an app password "Visitors".
   - Enter user ID + app password, Save, then "Send me a test message".
3. Reception: create a group (e.g. "Reception"), add the receptionists, choose it in settings,
   Save, then "Test the Reception desk conversation". The conversation is created by the reception
   user; if Talk limits who can create conversations, allow that user.
   Reception desk messages use the language set for the reception user (Personal settings > Language).
4. Office time zone (default Asia/Ho_Chi_Minh) and retention days (default 90).
5. Reception tablet: "Create new tablet link", open it on the tablet, bookmark it, allow the camera.
   The link is shown only once. Lost it? Create a new one (the old one stops working).

## Test
Calendar and display (0.3):
A. In Calendar, create a meeting tomorrow, add a room from kdvn_rooms and your private email as attendee.
   Within ~5 minutes you get the Calendar invitation and a separate visitor pass. The visit shows
   "From Calendar" on the Visitors page.
B. Move the meeting: the pass page shows the new time. Remove yourself: the pass is cancelled.
C. Open a display link: the meeting appears under its room; check a weekly meeting shows every week.

Visitors (0.2):
1. Invited guest: invite yourself (private email), time = now. On the tablet choose
   "I have a visitor pass", scan. You get a Talk message; the Reception tab shows "Arrived".
2. Check-out: on the tablet choose "Leaving? Check out", scan again.
3. Walk-in: on the tablet choose "Walk-in guest", pick a colleague as host. Both the colleague and the
   Reception desk conversation get a message. Let them in from the Reception tab or the host's page.
4. Walk-in with "I'm not sure": only the Reception desk gets a message.
5. Problem: scan a cancelled visit or one for tomorrow. The tablet explains; the Reception desk gets one
   warning (repeats within 10 minutes are suppressed).
6. Invite on behalf: as a reception member, invite a guest with someone else as host.

## Check-in times
Check-in opens 3 hours before the start time and closes 2 hours after the end time
(8 hours after the start if no end time). Change in lib/Service/VisitService.php.

## Troubleshooting
- Nothing happens / errors: `docker exec -u www-data nextcloud-app php occ log:tail`, look for `kdvn_visitors`.
- Talk test fails with a connection or certificate error: the app calls Talk at the public URL
  from inside the container. Point it at the internal web server instead, e.g.:
      docker exec -u www-data nextcloud-app php occ config:app:set kdvn_visitors talk_base_url --value=http://localhost
  (`localhost` must then be in trusted_domains.)
- Camera does not start: the tablet must use https://, and camera permission must be allowed
  for cloud.kinden.com.vn in the browser settings.
- Rate limits and brute-force protection work per IP address. Make sure Nextcloud sees real client
  IPs behind HAProxy/Cloudflare (`trusted_proxies` and `forwarded_for_headers` in config.php).
  Otherwise all visitors share one IP and limits hit everyone at once.

## Privacy
Guest name, company, email and phone are stored in Nextcloud's database and deleted after the
retention period (default 90 days). Walk-ins tick a consent box; the time is stored. The tablet's
host search shows display names only (2+ letters, max 5 results). Confirm the retention period and
consent wording with HR/legal.

## Files
- appinfo/info.xml, routes.php           app definition and URLs
- lib/Migration/                          creates the database table
- lib/Controller/PageController           the Visitors page
- lib/Controller/VisitApiController       JSON API for the Visitors page (own visits only)
- lib/Controller/PassController           guest pass page (public, rate limited)
- lib/Controller/KioskController          tablet page + check-in (public, secret key, camera allowed)
- lib/Controller/SettingsApiController    admin settings
- lib/Service/VisitService                the rules: invite, link, cancel, check in
- lib/Service/VisitStore                  all SQL
- lib/Service/TalkService                 sends Talk messages through Talk's REST API
- lib/Service/Messenger                   who gets which Talk message (host, Reception desk, backups)
- lib/BackgroundJob/Housekeeping          hourly: auto check-out, expire walk-ins, delete old visits
- lib/BackgroundJob/SendInvitationJob     sends pass emails for visits created from Calendar
- lib/Listener/CalendarListener           receives Calendar changes (never blocks saving an event)
- lib/Service/CalendarSync                rules: who gets a pass, and keeping passes in step
- lib/Service/EventParser                 reads iCalendar data with Sabre VObject (time zones, recurrence)
- lib/Service/DisplayService              data for the room displays
- lib/Controller/DisplayController        room display page (public, secret display key)
- lib/Service/Branding, LogoController    uploaded logo
- lib/Service/GuestMailer                 invitation email
- lib/Service/Texts                       vi/en/ja texts for guests and hosts
- js/visitors.js, kiosk.js, pass.js, admin.js   page scripts (no build step)
- js/qrcode-generator.js (MIT, Kazuhiko Arase), js/jsQR.js (Apache-2.0, see jsQR.LICENSE)
