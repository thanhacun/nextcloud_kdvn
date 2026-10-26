<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\Calendar\ICalendarIsShared;
use OCP\Calendar\ICalendarIsWritable;
use OCP\Calendar\ICreateFromString;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use Sabre\VObject\Component\VCalendar;

/**
 * Books a meeting room for a guest visit: creates an event in the host's own calendar with the
 * room invited, exactly as if the host had done it in Calendar. Nextcloud's scheduling then
 * reserves the room, and the event shows on the host's calendar and the room displays.
 *
 * Limitation: Nextcloud has no public API for changing or deleting events afterwards, so when a
 * visit is cancelled the host is asked to delete the event in Calendar.
 */
class CalendarBooking {
	public function __construct(
		private ICalendarManager $calendarManager,
		private IConfig $config,
		private IUserManager $userManager,
		private DisplayService $display,
		private ISecureRandom $random,
		private AppSettings $settings,
	) {
	}

	/** @return string the new event's UID */
	public function book(string $hostUid, array $room, int $start, int $end, string $summary, string $description): string {
		$host = $this->userManager->get($hostUid);
		$email = $host ? trim((string)$host->getEMailAddress()) : '';
		if ($email === '') {
			throw new UserError('The host\'s Nextcloud account has no email address, so the room can\'t be booked automatically. '
				. 'Add one under Personal settings, or book the room in Calendar.');
		}
		if ($room['email'] === '' || !filter_var($room['email'], FILTER_VALIDATE_EMAIL)) {
			throw new UserError($room['name'] . ' has no valid booking email, so it can\'t be booked. Fix it in Administration settings > Meeting rooms.');
		}
		$conflict = $this->display->roomConflict($room['email'], $start, $end);
		if ($conflict !== null) {
			$tz = $this->settings->officeTimezone();
			$when = (new \DateTimeImmutable('@' . $conflict['start']))->setTimezone($tz)->format('H:i')
				. '–' . (new \DateTimeImmutable('@' . $conflict['end']))->setTimezone($tz)->format('H:i');
			throw new UserError($room['name'] . ' is already booked ' . $when . '. Pick another time or room, or untick "Book the room".', 409);
		}

		$calendar = $this->writableCalendar($hostUid);
		if ($calendar === null) {
			throw new UserError('The host has no personal calendar to put the booking in.');
		}

		$uid = $this->random->generate(32, ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS) . '@kdvn-visitors';
		$vcalendar = new VCalendar([
			'PRODID' => '-//KDVN//Visitors//EN',
			'VEVENT' => [
				'UID' => $uid,
				'DTSTAMP' => new \DateTime('now', new \DateTimeZone('UTC')),
				'DTSTART' => (new \DateTime('@' . $start))->setTimezone(new \DateTimeZone('UTC')),
				'DTEND' => (new \DateTime('@' . $end))->setTimezone(new \DateTimeZone('UTC')),
				'SUMMARY' => $summary,
				'LOCATION' => $room['label'],
				'DESCRIPTION' => $description,
				'STATUS' => 'CONFIRMED',
			],
		]);
		$vcalendar->VEVENT->add('ORGANIZER', 'mailto:' . $email, ['CN' => $host->getDisplayName()]);
		$vcalendar->VEVENT->add('ATTENDEE', 'mailto:' . $room['email'], [
			'CN' => $room['name'],
			'CUTYPE' => 'ROOM',
			'ROLE' => 'REQ-PARTICIPANT',
			'PARTSTAT' => 'NEEDS-ACTION',
			'RSVP' => 'TRUE',
		]);

		$calendar->createFromString($uid . '.ics', $vcalendar->serialize());
		return $uid;
	}

	/** The host's default calendar ("personal" unless they chose another), or another of their own writable ones. */
	private function writableCalendar(string $uid): ?ICreateFromString {
		$default = $this->config->getUserValue($uid, 'dav', 'defaultCalendar', 'personal');
		$fallback = null;
		foreach ($this->calendarManager->getCalendarsForPrincipal('principals/users/' . $uid) as $calendar) {
			if (!$calendar instanceof ICreateFromString || $calendar->isDeleted()
				|| ($calendar instanceof ICalendarIsWritable && !$calendar->isWritable())
				|| ($calendar instanceof ICalendarIsShared && $calendar->isShared())) {
				continue;
			}
			if ($calendar->getUri() === $default) {
				return $calendar;
			}
			$fallback ??= $calendar;
		}
		return $fallback;
	}
}
