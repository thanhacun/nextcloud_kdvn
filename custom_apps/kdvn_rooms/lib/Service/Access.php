<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\Service;

use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * Who may manage rooms: Nextcloud admins and members of any Visitors reception group: the default
 * one (appconfig kdvn_visitors / reception_group) and each office's (office_groups, a JSON map
 * building id => group id), all set on the Visitors admin page.
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
		foreach ($this->receptionGroups() as $group) {
			if ($this->groupManager->isInGroup($uid, $group)) {
				return true;
			}
		}
		return false;
	}

	/** @return string[] */
	private function receptionGroups(): array {
		$groups = [$this->appConfig->getValueString('kdvn_visitors', 'reception_group', '')];
		$byOffice = json_decode($this->appConfig->getValueString('kdvn_visitors', 'office_groups', '{}'), true);
		foreach (is_array($byOffice) ? $byOffice : [] as $gid) {
			$groups[] = $gid;
		}
		return array_values(array_unique(array_filter($groups, fn ($g) => is_string($g) && $g !== '')));
	}

	/** @throws UserError 403 when the user may not manage rooms */
	public function require(): void {
		if (!$this->isAllowed()) {
			throw new UserError('Only administrators and the reception group can manage meeting rooms.', 403);
		}
	}
}
