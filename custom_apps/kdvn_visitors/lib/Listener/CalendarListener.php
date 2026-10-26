<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Listener;

use OCA\KdvnVisitors\BackgroundJob\CalendarSyncJob;
use OCP\BackgroundJob\IJobList;
use OCP\Calendar\Events\AbstractCalendarObjectEvent;
use OCP\Calendar\Events\CalendarObjectDeletedEvent;
use OCP\Calendar\Events\CalendarObjectMovedToTrashEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Nextcloud calls this whenever a calendar event is created, changed, restored, trashed or deleted.
 *
 * IMPORTANT: Nextcloud calls it *inside the database transaction that saves the event*. On
 * PostgreSQL one failed SQL statement there aborts the whole transaction, and the event is not
 * saved, even if we catch the error. So this listener reads nothing from our tables: it only
 * queues a background job (one small insert into oc_jobs) and all real work happens in that job,
 * after the event is safely saved.
 *
 * @template-implements IEventListener<Event>
 */
class CalendarListener implements IEventListener {
	public function __construct(
		private IJobList $jobList,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AbstractCalendarObjectEvent) {
			return;
		}
		try {
			$principalUri = (string)($event->getCalendarData()['principaluri'] ?? '');
			if (!str_starts_with($principalUri, 'principals/users/')) {
				return; // rooms' and resources' own calendars
			}
			$object = $event->getObjectData();
			$deleted = $event instanceof CalendarObjectDeletedEvent || $event instanceof CalendarObjectMovedToTrashEvent;
			// Same arguments = same job: several saves before cron runs collapse into one check.
			$this->jobList->add(CalendarSyncJob::class, [
				'principal' => $principalUri,
				'calendarId' => $event->getCalendarId(),
				'uri' => (string)($object['uri'] ?? ''),
				'uid' => (string)($object['uid'] ?? ''),
				'deleted' => $deleted,
			]);
		} catch (\Throwable $e) {
			$this->logger->warning('kdvn_visitors: could not queue calendar sync: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}
