<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\AppInfo;

use OCA\KdvnVisitors\Listener\CalendarListener;
use OCA\KdvnVisitors\Notification\Notifier;
use OCP\Calendar\Events\CalendarObjectCreatedEvent;
use OCP\Calendar\Events\CalendarObjectDeletedEvent;
use OCP\Calendar\Events\CalendarObjectMovedToTrashEvent;
use OCP\Calendar\Events\CalendarObjectRestoredEvent;
use OCP\Calendar\Events\CalendarObjectUpdatedEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'kdvn_visitors';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
		// Calendar -> visitor passes (see Service/CalendarSync)
		foreach ([CalendarObjectCreatedEvent::class, CalendarObjectUpdatedEvent::class, CalendarObjectRestoredEvent::class,
			CalendarObjectMovedToTrashEvent::class, CalendarObjectDeletedEvent::class] as $eventClass) {
			$context->registerEventListener($eventClass, CalendarListener::class);
		}
	}

	public function boot(IBootContext $context): void {
	}
}
