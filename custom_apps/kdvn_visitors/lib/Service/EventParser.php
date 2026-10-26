<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;

/**
 * Reads iCalendar data with Sabre VObject (the library Nextcloud itself uses for CalDAV),
 * so time zones, all-day events and recurring events are handled correctly.
 */
class EventParser {
	/**
	 * The main event (not a changed single occurrence of a series).
	 * @return array|null see eventInfo()
	 */
	public function master(string $ics): ?array {
		$calendar = $this->read($ics);
		if ($calendar === null) {
			return null;
		}
		foreach ($calendar->select('VEVENT') as $event) {
			if (!isset($event->{'RECURRENCE-ID'})) {
				return $this->eventInfo($event);
			}
		}
		$first = $calendar->select('VEVENT')[0] ?? null;
		return $first ? $this->eventInfo($first) : null;
	}

	/**
	 * Every occurrence that overlaps [from, to), with recurring events expanded.
	 * @return array[] see eventInfo()
	 */
	public function occurrences(string $ics, \DateTimeImmutable $from, \DateTimeImmutable $to): array {
		$calendar = $this->read($ics);
		if ($calendar === null) {
			return [];
		}
		try {
			$expanded = $calendar->expand($from, $to);
		} catch (\Throwable) {
			return [];
		}
		$out = [];
		foreach ($expanded->select('VEVENT') as $event) {
			$info = $this->eventInfo($event);
			if ($info['end'] > $from->getTimestamp() && $info['start'] < $to->getTimestamp()) {
				$out[] = $info;
			}
		}
		return $out;
	}

	/**
	 * @return array{uid: string, summary: string, status: string, private: bool, sequence: int, recurring: bool,
	 *               start: int, end: int, allDay: bool, organizer: array{email: string, name: string},
	 *               attendees: array<int, array{email: string, name: string, type: string, partstat: string}>}
	 */
	private function eventInfo(VEvent $event): array {
		$start = $event->DTSTART;
		$allDay = $start !== null && !$start->hasTime();
		$startTs = $start ? $start->getDateTime()->getTimestamp() : 0;
		if (isset($event->DTEND)) {
			$endTs = $event->DTEND->getDateTime()->getTimestamp();
		} elseif (isset($event->DURATION)) {
			$endTs = $start->getDateTime()->add($event->DURATION->getDateInterval())->getTimestamp();
		} else {
			$endTs = $startTs + ($allDay ? 86400 : 0);
		}

		$attendees = [];
		foreach ($event->select('ATTENDEE') as $attendee) {
			$email = $this->email((string)$attendee->getValue());
			if ($email === '') {
				continue;
			}
			$attendees[] = [
				'email' => $email,
				'name' => isset($attendee['CN']) ? trim((string)$attendee['CN']) : '',
				'type' => isset($attendee['CUTYPE']) ? strtoupper((string)$attendee['CUTYPE']) : 'INDIVIDUAL',
				'partstat' => isset($attendee['PARTSTAT']) ? strtoupper((string)$attendee['PARTSTAT']) : 'NEEDS-ACTION',
			];
		}
		$organizer = isset($event->ORGANIZER)
			? ['email' => $this->email((string)$event->ORGANIZER->getValue()), 'name' => isset($event->ORGANIZER['CN']) ? trim((string)$event->ORGANIZER['CN']) : '']
			: ['email' => '', 'name' => ''];
		$class = isset($event->CLASS) ? strtoupper((string)$event->CLASS) : 'PUBLIC';

		return [
			'uid' => isset($event->UID) ? (string)$event->UID : '',
			'summary' => isset($event->SUMMARY) ? trim((string)$event->SUMMARY) : '',
			'status' => isset($event->STATUS) ? strtoupper((string)$event->STATUS) : 'CONFIRMED',
			'private' => $class === 'PRIVATE' || $class === 'CONFIDENTIAL',
			'sequence' => isset($event->SEQUENCE) ? (int)(string)$event->SEQUENCE : 0,
			'recurring' => isset($event->RRULE) || isset($event->RDATE),
			'start' => $startTs,
			'end' => max($endTs, $startTs),
			'allDay' => $allDay,
			'organizer' => $organizer,
			'attendees' => $attendees,
		];
	}

	private function read(string $ics): ?VCalendar {
		try {
			$doc = Reader::read($ics, Reader::OPTION_FORGIVING);
			return $doc instanceof VCalendar ? $doc : null;
		} catch (\Throwable) {
			return null;
		}
	}

	/** "mailto:Room.A@Kinden.com.vn" -> "room.a@kinden.com.vn" */
	private function email(string $value): string {
		$value = trim($value);
		if (stripos($value, 'mailto:') === 0) {
			$value = substr($value, 7);
		}
		return strtolower($value);
	}
}
