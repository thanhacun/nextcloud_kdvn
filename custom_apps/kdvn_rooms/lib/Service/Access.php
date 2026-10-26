<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\Service;

use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * Who may manage rooms: Nextcloud admins and the Visitors "reception group"
 * (appconfig kdvn_visitors / reception_group, set on the Visitors admin page).
 */
class Access {
	public function __construct(
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private IAppConfig $appConfig,
	) {
	}

	public function isAllowed(): bool {
		$uid = $this->userSession->getUser()?->getUID();
		if ($uid === null) {
			return false;
		}
		if ($this->groupManager->isAdmin($uid)) {
			return true;
		}
		$group = $this->appConfig->getValueString('kdvn_visitors', 'reception_group', '');
		return $group !== '' && $this->groupManager->isInGroup($uid, $group);
	}

	/** @throws UserError 403 when the user may not manage rooms */
	public function require(): void {
		if (!$this->isAllowed()) {
			throw new UserError('Only administrators and the reception group can manage meeting rooms.', 403);
		}
	}
}
