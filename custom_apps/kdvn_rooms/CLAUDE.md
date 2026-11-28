# kdvn_rooms

Web page to manage Calendar Resource Management buildings, floors and meeting rooms, so
non-IT staff do not need occ.

## Rules
- Who may manage rooms: admins and the kdvn_visitors reception groups (appconfig
  kdvn_visitors/reception_group and office_groups, any of them), checked in Service/Access.php on every ApiController call.
  The API methods are #[NoAdminRequired]; never drop the Access check. The only page is
  Administration settings > Meeting rooms (0.3.0 removed the top-bar entry and PageController: Nextcloud 35's own
  resource management made a second entry redundant). Reception staff who are not admins need that page
  delegated: Administration settings > Administration privileges > Meeting rooms > add the group.
- Writes calresources_buildings/_stories/_rooms/_restricts directly, then must call
  OCP\Calendar\Room\IManager::update() so Calendar sees the change. Never change schema.
- Re-run the quick re-test (docs/deploy-apps.md, step 6) after any update of
  calendar_resource_management or of Nextcloud.

## How it works
- Flags rooms Calendar cannot book: missing, invalid, duplicate or user-shared email.
  A room with such an email cannot be booked.

- Excel import (0.3.0): SheetReader (xlsx via ZipArchive + SimpleXML, or csv) -> RoomImporter (preview, then apply;
  existing rooms are skipped never changed; ResourceStore::batch refreshes Calendar's room cache once). Template is
  built by SheetWriter. Routes api#import and api#import_template: a new route needs `apachectl -k graceful`
  (docs/deploy-apps.md).

## Pitfalls
- The first release (0.1.1) wrongly locked the email field.
- If a room cannot be booked, look for the red warning in 0.1.1 or later; if none, check
  "Who can book this room".
