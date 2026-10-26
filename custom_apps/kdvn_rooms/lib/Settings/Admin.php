<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\Settings;

use OCA\KdvnRooms\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\Settings\IDelegatedSettings;
use OCP\Util;

/**
 * The settings page itself. Implementing IDelegatedSettings (instead of ISettings)
 * lets an admin hand this page to a non-admin group under
 * Administration settings > Administration privileges.
 */
class Admin implements IDelegatedSettings {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'admin');
		Util::addStyle(Application::APP_ID, 'admin');

		return new TemplateResponse(Application::APP_ID, 'admin', [
			// Domain used for auto-suggested room emails. Change with:
			// occ config:app:set kdvn_rooms email_domain --value=rooms.example.com
			'emailDomain' => $this->appConfig->getValueString(Application::APP_ID, 'email_domain', 'resources.invalid'),
		], '');
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 10;
	}

	public function getName(): ?string {
		return null; // only one settings block in this section
	}

	public function getAuthorizedAppConfig(): array {
		return [];
	}
}
