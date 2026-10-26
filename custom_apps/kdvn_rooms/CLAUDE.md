# kdvn_rooms

Web page to manage Calendar Resource Management buildings, floors and meeting rooms, so
non-IT staff do not need occ.

## Rules
- Admin page is delegatable to a group (IDelegatedSettings + #[AuthorizedAdminSetting]).
- Writes calresources_buildings/_stories/_rooms/_restricts directly, then must call
  OCP\Calendar\Room\IManager::update() so Calendar sees the change. Never change schema.
- Re-run the quick re-test (docs/deploy-apps.md, step 6) after any update of
  calendar_resource_management or of Nextcloud.

## How it works
- Flags rooms Calendar cannot book: missing, invalid, duplicate or user-shared email.
  A room with such an email cannot be booked.

## Pitfalls
- The first release (0.1.1) wrongly locked the email field.
- If a room cannot be booked, look for the red warning in 0.1.1 or later; if none, check
  "Who can book this room".
