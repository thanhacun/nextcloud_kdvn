# Meeting Rooms (kdvn_rooms)

A web page for managing Calendar Resource Management buildings, floors and rooms
without `occ`. Tested against the Nextcloud 32 APIs and calendar_resource_management 0.10–0.12.

Upstream added its own admin UI in calendar_resource_management 0.13 / 1.0, which needs
Nextcloud 34+. After upgrading to 34, compare the two and remove this app if upstream's is enough.

## What it does
- Add, edit and delete buildings, floors and rooms (name, number, seats, type, equipment, contact person).
- Limit a room to one or more groups ("Who can book this room").
- Refreshes Nextcloud's room cache after every change, so rooms show in Calendar immediately.
- Refuses to delete a building that still has floors, or a floor that still has rooms
  (the backend has no foreign keys, so this prevents orphaned rows).
- Room ID and booking email can't be changed after creation, because existing bookings use them.
- Vehicles and general resources are not managed here yet (still `occ`).

## Install
Run from /home/kdvn_admin/nextcloud-stack. No restart needed.

    docker cp kdvn_rooms nextcloud-app:/var/www/html/custom_apps/
    docker exec nextcloud-app chown -R www-data:www-data /var/www/html/custom_apps/kdvn_rooms
    docker exec -u www-data nextcloud-app php occ app:enable kdvn_rooms

Open Administration settings > Meeting rooms.

## Let non-admins use it
1. Create a group, e.g. "Room managers", and add the people.
2. Administration settings > Administration privileges > find "Meeting rooms" > add the group.
3. Those users now see Administration settings > Meeting rooms, and nothing else from the admin area.

## Settings
Suggested booking emails use `room-<id>@resources.invalid` by default. To change the domain:

    docker exec -u www-data nextcloud-app php occ config:app:set kdvn_rooms email_domain --value=rooms.kinden.com.vn

## Update after editing the code
Copy the folder again (same docker cp + chown). Bump <version> in appinfo/info.xml if you
change info.xml or routes, then run `occ upgrade`. JS/CSS changes need a hard refresh (Ctrl+F5).

## Files
- appinfo/info.xml       app id, version, settings page registration
- appinfo/routes.php     URL -> controller method
- lib/Settings/          the settings page and its menu entry (delegatable)
- lib/Controller/        JSON API, permission checks
- lib/Service/           all database work
- templates/admin.php    page shell
- js/admin.js, css/admin.css  the UI (no build step)
