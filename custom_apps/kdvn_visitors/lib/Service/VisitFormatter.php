<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\IDateTimeFormatter;
use OCP\IDateTimeZone;
use OCP\L10N\IFactory;

/** Formats visit times in a given language, using the host's time zone. */
class VisitFormatter {
	public function __construct(
		private IDateTimeFormatter $dateFormatter,
		private IDateTimeZone $dateTimeZone,
		private IFactory $l10nFactory,
	) {
	}

	/** "15 October 2026 at 14:00 – 16:00" in the given language */
	public function when(array $visit, string $lang): string {
		$tz = $this->dateTimeZone->getTimeZone(false, $visit['host_uid'] ?: null);
		$l = $this->l10nFactory->get('kdvn_visitors', $lang);
		$text = $this->dateFormatter->formatDateTime($visit['start_at'], 'long', 'short', $tz, $l);
		if ($visit['end_at'] !== null) {
			$text .= ' – ' . $this->dateFormatter->formatTime($visit['end_at'], 'short', $tz, $l);
		}
		return $text;
	}

	public function date(array $visit, string $lang): string {
		$tz = $this->dateTimeZone->getTimeZone(false, $visit['host_uid'] ?: null);
		return $this->dateFormatter->formatDate($visit['start_at'], 'long', $tz, $this->l10nFactory->get('kdvn_visitors', $lang));
	}

	public function time(int $timestamp, string $hostUid, string $lang): string {
		$tz = $this->dateTimeZone->getTimeZone(false, $hostUid !== '' ? $hostUid : null);
		return $this->dateFormatter->formatTime($timestamp, 'short', $tz, $this->l10nFactory->get('kdvn_visitors', $lang));
	}
}
