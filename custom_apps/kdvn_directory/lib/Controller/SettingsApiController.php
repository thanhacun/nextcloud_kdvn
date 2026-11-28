<?php

declare(strict_types=1);

namespace OCA\KdvnDirectory\Controller;

use OCA\KdvnDirectory\Service\DirectorySettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;

/** Admin settings. No attributes = Nextcloud's default: admins only, CSRF checked. */
class SettingsApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private DirectorySettings $settings,
		private IGroupManager $groupManager,
	) {
		parent::__construct($appName, $request);
	}

	public function save(array $listedGroups = [], array $deniedGroups = []): DataResponse {
		$gids = array_merge(array_column($listedGroups, 'id'), $deniedGroups);
		foreach ($gids as $gid) {
			if (!is_string($gid) || !$this->groupManager->groupExists($gid)) {
				return new DataResponse(['message' => 'There is no group "' . (is_string($gid) ? $gid : '?') . '".'], Http::STATUS_BAD_REQUEST);
			}
		}
		$this->settings->setListedGroups($listedGroups);
		$this->settings->setDeniedGroups($deniedGroups);
		return new DataResponse(['ok' => true]);
	}
}
