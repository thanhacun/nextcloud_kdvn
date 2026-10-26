<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\INavigationManager;
use OCA\KdvnRooms\Service\Access;

class Application extends App implements IBootstrap {
	public const APP_ID = 'kdvn_rooms';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Nothing to register yet: settings and routes are declared in appinfo/.
	}

	public function boot(IBootContext $context): void {
		// Top-bar entry only for people who may manage rooms (admins and the reception group).
		$context->injectFn(function (INavigationManager $navigation, Access $access, IURLGenerator $urls, IFactory $l10n): void {
			if (!$access->isAllowed()) {
				return;
			}
			$navigation->add(fn () => [
				'id' => self::APP_ID,
				'order' => 76,
				'href' => $urls->linkToRoute('kdvn_rooms.page.index'),
				'icon' => $urls->imagePath(self::APP_ID, 'app-dark.svg'),
				'name' => $l10n->get(self::APP_ID)->t('Meeting rooms'),
			]);
		});
	}
}
