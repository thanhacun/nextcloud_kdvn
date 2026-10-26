<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\BackgroundJob;

use OCA\KdvnVisitors\Service\VisitService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

/** Sends the visitor pass email for a visit created from Calendar (runs once, with the next cron). */
class SendInvitationJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private VisitService $visits,
	) {
		parent::__construct($time);
	}

	protected function run($argument): void {
		$this->visits->sendInvitationFor((int)($argument['visitId'] ?? 0));
	}
}
