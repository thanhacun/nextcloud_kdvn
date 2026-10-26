<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\BackgroundJob;

use OCA\KdvnVisitors\Service\AppSettings;
use OCA\KdvnVisitors\Service\VisitStore;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Runs about every 15 minutes with Nextcloud's cron (your host crontab runs cron.php every 5 minutes).
 *  - Guests still "in the building" are checked out a set time after their visit ends
 *    (Administration settings > Visitors), and at the latest after midnight.
 *  - Walk-ins nobody answered from a previous day are marked declined.
 *  - Visits older than the retention period (default 90 days) are deleted.
 */
class Housekeeping extends TimedJob {
	/** Walk-ins and visits without an end time are assumed to last this long. */
	private const ASSUMED_LENGTH = 2 * 3600;

	public function __construct(
		ITimeFactory $time,
		private VisitStore $store,
		private AppSettings $settings,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(900); // every 15 minutes, so automatic check-out happens close to its time
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$now = $this->time->getTime();
		$startOfToday = (new \DateTimeImmutable('today', $this->settings->officeTimezone()))->getTimestamp();

		// Guests still "in the building": checked out a while after their visit ended (setting),
		// and in any case once the day is over.
		$grace = $this->settings->autoCheckoutMinutes() * 60;
		foreach ($this->store->listStale('checked_in', 'checked_in_at', $now) as $visit) {
			$end = $visit['end_at'] ?? ($visit['start_at'] + self::ASSUMED_LENGTH);
			$checkedOutAt = null;
			if ($grace > 0 && $now >= $end + $grace) {
				$checkedOutAt = $end + $grace; // record the time it became due, not when cron noticed
			} elseif ((int)$visit['checked_in_at'] < $startOfToday) {
				$checkedOutAt = $now;
			}
			if ($checkedOutAt !== null) {
				$this->store->update($visit['id'], ['status' => 'checked_out', 'checked_out_at' => min($checkedOutAt, $now)]);
			}
		}
		foreach ($this->store->listStale('waiting', 'start_at', $startOfToday) as $visit) {
			$this->store->update($visit['id'], ['status' => 'declined', 'decided_by' => 'auto']);
		}

		$deleted = $this->store->deleteStartedBefore($now - $this->settings->retentionDays() * 86400);
		if ($deleted > 0) {
			$this->logger->info("kdvn_visitors: deleted $deleted visit(s) older than {$this->settings->retentionDays()} days");
		}
	}
}
