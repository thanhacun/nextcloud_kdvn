<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Settings;

use OCA\KdvnVisitors\AppInfo\Application;
use OCA\KdvnVisitors\Service\AppSettings;
use OCA\KdvnVisitors\Service\Branding;
use OCA\KdvnVisitors\Service\RoomCatalog;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;
use OCP\Util;

/** Administration settings > Visitors (admins only). */
class Admin implements ISettings {
	public function __construct(
		private AppSettings $settings,
		private Branding $branding,
		private RoomCatalog $rooms,
		private IAppManager $appManager,
		private IURLGenerator $urlGenerator,
		private IGroupManager $groupManager,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'admin');
		Util::addStyle(Application::APP_ID, 'admin');

		return new TemplateResponse(Application::APP_ID, 'admin', [
			'companyName' => $this->settings->companyName(),
			'officeAddress' => $this->settings->officeAddress(),
			'talkUser' => $this->settings->talkUser(),
			'hasTalkPassword' => $this->settings->hasTalkPassword(),
			'talkInstalled' => $this->appManager->isEnabledForAnyone('spreed'),
			'kioskCreatedAt' => $this->settings->kioskKeyCreatedAt(),
			'receptionGroup' => $this->settings->receptionGroup(),
			'groups' => array_map(fn ($g) => ['id' => $g->getGID(), 'name' => $g->getDisplayName()], $this->groupManager->search('')),
			'retentionDays' => $this->settings->retentionDays(),
			'officeTimezone' => $this->settings->officeTimezoneName(),
			'appearance' => $this->settings->appearance(),
			'autoPassMode' => $this->settings->autoPassMode(),
			'autoCheckoutMinutes' => $this->settings->autoCheckoutMinutes(),
			'buildings' => array_map(fn ($b) => $b + [
				'workdays' => $this->settings->workdaysFor($b['id']),
				'reception' => $this->settings->hasReception($b['id']),
			], $this->rooms->buildings()),
			'displayGuestMode' => $this->settings->displayGuestMode(),
			'displayTitleMode' => $this->settings->displayTitleMode(),
			'displayCreatedAt' => $this->settings->displayKeyCreatedAt(),
			'displayLinkUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.settings_api.new_display_link'),
			'logoUrl' => $this->branding->logoUrl(true),
			'hasOwnLogo' => $this->branding->hasOwnLogo(),
			'logoApiUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.settings_api.upload_logo'),
			'testReceptionUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.settings_api.test_reception'),
			'saveUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.settings_api.save'),
			'kioskUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.settings_api.new_kiosk_link'),
			'testTalkUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.settings_api.test_talk'),
		], '');
	}

	public function getSection(): string {
		return 'kdvn_visitors';
	}

	public function getPriority(): int {
		return 10;
	}
}
