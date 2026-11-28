# kdvn_directory

Read-only staff directory: admins tick which groups are listed (Administration settings > Directory); every signed-in
user can browse their members and search by name or email. Users in a "denied" group (temporary/guest accounts) get 403
and are not listed. Disabled accounts are never listed. Email is hidden when the person set its scope to private.

## Rules
- No database table, no migration: settings live in oc_appconfig (listed_groups, denied_groups). Deploying 0.1.0 is
  docker cp + app:enable; a later version bump needs `occ upgrade` (docs/deploy-apps.md).
- Member lists leave the server only through ApiController::data(); keep the DirectoryService::mayUse() check there and in PageController.
- Group IDs come from the identity provider (SAML_*): a deleted group is skipped silently.
- OCP calls checked against stable35 on 2026-11-28: IGroup::searchUsers, IGroupManager::get/isAdmin/getUserGroupIds/groupExists,
  IAccountManager::getAccount, IAppConfig::getValueString/setValueString. UNVERIFIED (core routes, not OCP): /avatar/{uid}/64
  and /u/{uid} URLs built in DirectoryService.
- Never name a route URL parameter `action`, `caller` or `_route`.
