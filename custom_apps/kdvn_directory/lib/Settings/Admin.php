<?php

declare(strict_types=1);

namespace OCA\KdvnDirectory\Settings;

use OCA\KdvnDirectory\AppInfo\Application;
use OCA\KdvnDirectory\Service\DirectorySettings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;
use OCP\Util;

/** Administration settings > Directory (admins only). */
class Admin implements ISettings {
	public function __construct(
		private DirectorySettings $settings,
		private IGroupManager $groupManager,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'admin');
		Util::addStyle(Application::APP_ID, 'directory');
		return new TemplateResponse(Application::APP_ID, 'admin', [
			'groups' => array_map(fn ($g) => ['id' => $g->getGID(), 'name' => $g->getDisplayName(), 'count' => $g->count()],
				$this->groupManager->search('')),
			'listed' => $this->settings->listedGroups(),
			'denied' => $this->settings->deniedGroups(),
			'saveUrl' => $this->urlGenerator->linkToRoute('kdvn_directory.settings_api.save'),
		], '');
	}

	public function getSection(): string {
		return 'kdvn_directory';
	}

	public function getPriority(): int {
		return 10;
	}
}
