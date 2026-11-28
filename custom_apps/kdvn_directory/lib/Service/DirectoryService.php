<?php

declare(strict_types=1);

namespace OCA\KdvnDirectory\Service;

use OCP\Accounts\IAccountManager;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;

/** Builds the directory: listed groups with their enabled, non-denied members. */
class DirectoryService {
	public function __construct(
		private DirectorySettings $settings,
		private IGroupManager $groupManager,
		private IAccountManager $accountManager,
		private IURLGenerator $urlGenerator,
	) {
	}

	/** Admins always get in. Users in a denied group (temporary or guest accounts) do not. */
	public function mayUse(?IUser $user): bool {
		if ($user === null) {
			return false;
		}
		if ($this->groupManager->isAdmin($user->getUID())) {
			return true;
		}
		$mine = $this->groupManager->getUserGroupIds($user);
		return array_intersect($mine, $this->settings->deniedGroups()) === [];
	}

	/** @return array{groups: list<array<string, mixed>>, avatarBase: string, profileBase: string} */
	public function build(IUser $viewer): array {
		$denied = $this->settings->deniedGroups();
		$mine = $this->groupManager->getUserGroupIds($viewer);
		$groups = [];
		foreach ($this->settings->listedGroups() as $listed) {
			$group = $this->groupManager->get($listed['id']);
			if ($group === null) {
				continue; // group was deleted or renamed in the identity provider
			}
			$members = [];
			foreach ($group->searchUsers('') as $user) {
				if (!$user->isEnabled() || $this->inAny($user, $denied)) {
					continue;
				}
				$members[] = [
					'id' => $user->getUID(),
					'name' => $user->getDisplayName() !== '' ? $user->getDisplayName() : $user->getUID(),
					'email' => $this->visibleEmail($user),
				];
			}
			usort($members, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
			$groups[] = [
				'id' => $group->getGID(),
				'name' => $listed['label'] !== '' ? $listed['label'] : $group->getDisplayName(),
				'mine' => in_array($group->getGID(), $mine, true),
				'members' => $members,
			];
		}
		return [
			'groups' => $groups,
			'me' => $viewer->getUID(),
			'avatarBase' => $this->urlGenerator->getAbsoluteURL('/avatar/'),
			'profileBase' => $this->urlGenerator->getAbsoluteURL('/u/'),
		];
	}

	private function inAny(IUser $user, array $gids): bool {
		return $gids !== [] && array_intersect($this->groupManager->getUserGroupIds($user), $gids) !== [];
	}

	/** Respects the person's own privacy choice: a private email is not shown. */
	private function visibleEmail(IUser $user): string {
		$email = $user->getEMailAddress();
		if ($email === null || $email === '') {
			return '';
		}
		try {
			$scope = $this->accountManager->getAccount($user)->getProperty(IAccountManager::PROPERTY_EMAIL)->getScope();
		} catch (\Throwable $e) {
			return '';
		}
		return $scope === IAccountManager::SCOPE_PRIVATE ? '' : $email;
	}
}
