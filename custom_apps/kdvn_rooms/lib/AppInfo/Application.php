<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'kdvn_rooms';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Nothing to register yet: settings and routes are declared in appinfo/.
	}

	public function boot(IBootContext $context): void {
	}
}
