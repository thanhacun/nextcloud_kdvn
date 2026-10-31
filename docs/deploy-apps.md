# Deploying kdvn_rooms / kdvn_visitors

Run on the server from /home/kdvn_admin/nextcloud-stack (the apps are in custom_apps/), one app at a time. Show each
command and wait for approval. Steps 2 to 4 change the server.

1. Get the new source onto the server: `git pull` in this stack repo on the server (TODO:
   confirm the server folder is a git clone), or copy/unzip the app folder. Then
   `grep '<version>' custom_apps/kdvn_visitors/appinfo/info.xml` must show the new version.
   (`occ upgrade` once answered "No upgrade required" because the zip was never unpacked.)
2. `docker cp custom_apps/kdvn_visitors nextcloud-app:/var/www/html/custom_apps/`
   A single changed file can be copied alone; no restart needed. `docker cp` never deletes
   files removed in a newer version.
3. `docker exec nextcloud-app chown -R www-data:www-data /var/www/html/custom_apps/kdvn_visitors`
4. `docker exec -u www-data nextcloud-app php occ upgrade`
   Needed whenever the version changed or a migration was added (a few seconds of
   maintenance mode). First install: `docker exec -u www-data nextcloud-app php occ app:enable kdvn_visitors`.
5. Health check: `docker exec -u www-data nextcloud-app php occ app:list | grep -A1 kdvn_`
   shows the new version, and `app:list --disabled` does not list the apps. Hard refresh
   browsers (Ctrl+F5) and reload the tablet and display pages.
6. Watch `docker exec -u www-data nextcloud-app php occ log:tail` for lines with `kdvn_`
   while doing the quick re-test:
   - edit and save a room (still found in Calendar)
   - book a test meeting with a room and an outside email (pass arrives in about 5 minutes)
   - the meeting shows on the display
   - tablet check-in notifies the host in Talk
   - from 0.6 (offices): the migration adds oc_kdvnv_visits.building_id, so take a fresh backup first
     (backup.sh). Deploy kdvn_visitors and kdvn_rooms together (kdvn_rooms 0.2.1 reads office_groups).
     Then in Administration settings > Visitors: set each office's reception group, create each
     office's tablet link, tick the groups to hide from the staff search, and click "Test the
     Reception desk conversation". An invited guest arriving must reach the host and only that
     office's reception group; a Hanoi reception user must not see another office's guests.
