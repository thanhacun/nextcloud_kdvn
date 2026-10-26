<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\BackgroundJob;

use OCA\KdvnVisitors\Service\CalendarSync;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Applies a calendar change to visitor passes, with the next cron run (within ~5 minutes).
 * Reads the event's *current* state, so several quick edits are handled once, correctly.
 */
class CalendarSyncJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private CalendarSync $sync,
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	protected function run($argument): void {
		$principal = (string)($argument['principal'] ?? '');
		$uid = (string)($argument['uid'] ?? '');
		try {
			$ics = empty($argument['deleted'])
				? $this->currentData((int)($argument['calendarId'] ?? 0), (string)($argument['uri'] ?? ''))
				: null;
			if ($ics === null) {
				$this->sync->onDeletedUid($principal, $uid); // deleted, trashed, or gone since
			} else {
				$this->sync->onSaved($principal, $ics);
			}
		} catch (\Throwable $e) {
			$this->logger->warning('kdvn_visitors: calendar sync failed for event ' . $uid . ': ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	private function currentData(int $calendarId, string $uri): ?string {
		$qb = $this->db->getQueryBuilder();
		$qb->select('calendardata')
			->from('calendarobjects')
			->where($qb->expr()->eq('calendarid', $qb->createNamedParameter($calendarId)))
			->andWhere($qb->expr()->eq('uri', $qb->createNamedParameter($uri)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$data = $result->fetchOne();
		$result->closeCursor();
		if ($data === false || $data === null) {
			return null;
		}
		return is_resource($data) ? (string)stream_get_contents($data) : (string)$data;
	}
}
