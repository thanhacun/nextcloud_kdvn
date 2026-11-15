<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Settings;

use OCA\KdvnSensors\AppInfo\Application;
use OCA\KdvnSensors\Service\AppSettings;
use OCA\KdvnSensors\Service\BuildingCatalog;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;
use OCP\Util;

/** Administration settings > Office sensors (admins only). */
class Admin implements ISettings {
	public function __construct(
		private AppSettings $settings,
		private BuildingCatalog $buildings,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'admin');
		return new TemplateResponse(Application::APP_ID, 'admin', [
			'provider' => $this->settings->provider(),
			'sensors' => $this->settings->hasSensors() ? $this->settings->sensors() : [],
			'buildings' => array_map(fn ($id, $name) => ['id' => $id, 'name' => $name], array_keys($this->buildings->names()), array_values($this->buildings->names())),
			'defaultThresholds' => AppSettings::DEFAULT_THRESHOLDS,
			'thresholds' => $this->settings->thresholds(),
			'displayCreatedAt' => $this->settings->displayKeyCreatedAt(),
			'saveUrl' => $this->urlGenerator->linkToRoute('kdvn_sensors.settings_api.save'),
			'displayLinkUrl' => $this->urlGenerator->linkToRoute('kdvn_sensors.settings_api.new_display_link'),
		], '');
	}

	public function getSection(): string {
		return 'kdvn_sensors';
	}

	public function getPriority(): int {
		return 10;
	}
}
