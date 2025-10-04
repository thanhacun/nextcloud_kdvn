# NoCollabora Filter

This Nextcloud app prevents Collabora from previewing large `.xlsx` files (default >5 MB).

## Installation
1. Extract to `nextcloud/apps/nocollabora_filter`
2. Run `sudo -u www-data php occ app:enable nocollabora_filter`
3. Check `data/nextcloud.log` for blocking messages.
