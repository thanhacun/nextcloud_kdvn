<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IUserManager;

/**
 * Data for the public room display: bookings per room for the next days, with guests.
 *
 * Bookings are read from Nextcloud's calendar tables and parsed with Sabre VObject, so
 * recurring meetings, moved/cancelled occurrences, UTC times and all-day events are right.
 * Each meeting exists once per participant's calendar; only the newest copy is used.
 * Private and confidential events are shown as "Booked" without a title.
 */
class DisplayService {
	private const CACHE_SECONDS = 30;

	public function __construct(
		private IDBConnection $db,
		private EventParser $parser,
		private RoomCatalog $rooms,
		private VisitStore $visits,
		private AppSettings $settings,
		private IUserManager $userManager,
		private ICacheFactory $cacheFactory,
	) {
	}

	/**
	 * @param int $days number of working days to show (today always counts as shown)
	 */
	public function data(?int $buildingId, int $days): array {
		$days = max(1, min(10, $days));
		$cache = $this->cacheFactory->createDistributed('kdvn_visitors');
		$cacheKey = 'display-' . ($buildingId ?? 'all') . '-' . $days;
		$cached = $cache->get($cacheKey);
		if (is_array($cached)) {
			return $cached;
		}

		$tz = $this->settings->officeTimezone();
		$from = new \DateTimeImmutable('today', $tz);
		[$to, $shownDays] = $this->workingWindow($from, $days, $this->settings->workdaysFor($buildingId));

		// Rooms without a (valid) email still appear: they can't be booked in Calendar, but guests
		// invited in Visitors can still be sent there.
		$rooms = array_values(array_filter($this->rooms->rooms(),
			fn ($r) => $buildingId === null || $r['buildingId'] === $buildingId));
		$roomsByEmail = [];
		foreach ($rooms as $room) {
			if ($room['email'] !== '' && filter_var($room['email'], FILTER_VALIDATE_EMAIL)) {
				$roomsByEmail[$room['email']] = $room;
			}
		}

		$bookings = [];
		foreach ($this->meetings(array_keys($roomsByEmail), $from, $to) as $occurrence) {
			if ($occurrence['status'] === 'CANCELLED' || !$this->onShownDay($occurrence, $shownDays, $tz)) {
				continue;
			}
			foreach ($occurrence['attendees'] as $attendee) {
				$room = $roomsByEmail[$attendee['email']] ?? null;
				if ($room !== null && $attendee['partstat'] !== 'DECLINED') {
					$bookings[$room['id']][] = $occurrence;
				}
			}
		}

		$guestVisits = $this->visits->listForDisplay($from->getTimestamp(), $to->getTimestamp());
		$titleMode = $this->settings->displayTitleMode();
		$guestMode = $this->settings->displayGuestMode();

		$out = [];
		$usedVisits = [];
		foreach ($rooms as $room) {
			$list = $bookings[$room['id']] ?? [];
			usort($list, fn ($a, $b) => $a['start'] <=> $b['start']);
			// A plain loop (not array_map with fn): $usedVisits must be shared, and arrow functions copy it.
			$entries = [];
			foreach ($list as $occurrence) {
				$entries[] = $this->booking($occurrence, $room, $guestVisits, $titleMode, $guestMode, $usedVisits);
			}
			$entries = array_merge($entries, array_values(array_filter(
				$this->guestVisitsWithoutBooking($room, $guestVisits, $usedVisits, $guestMode, $from, $to),
				fn ($e) => $this->onShownDay($e, $shownDays, $tz))));
			usort($entries, fn ($a, $b) => $a['start'] <=> $b['start']);
			$out[] = [
				'id' => $room['id'],
				'name' => $room['name'],
				'floor' => $room['floor'],
				'number' => $room['number'],
				'capacity' => $room['capacity'],
				'bookable' => isset($roomsByEmail[$room['email']]),
				'bookings' => $entries,
			];
		}

		$building = null;
		foreach ($this->rooms->buildings() as $b) {
			if ($b['id'] === $buildingId) {
				$building = $b;
			}
		}
		$data = [
			'generatedAt' => time(),
			'timezone' => $tz->getName(),
			'from' => $from->getTimestamp(),
			'days' => $days,
			'shownDays' => $shownDays,
			'building' => $building,
			'guestMode' => $guestMode,
			'rooms' => $out,
		];
		$cache->set($cacheKey, $data, self::CACHE_SECONDS);
		return $data;
	}

	/**
	 * Is the room free? Used before booking it from the Visitors page.
	 * @return array|null the first meeting that overlaps, or null when free
	 */
	public function roomConflict(string $roomEmail, int $start, int $end): ?array {
		$email = strtolower($roomEmail);
		$from = (new \DateTimeImmutable('@' . $start))->modify('-1 day');
		$to = (new \DateTimeImmutable('@' . $end))->modify('+1 day');
		foreach ($this->meetings([$email], $from, $to) as $o) {
			if ($o['status'] === 'CANCELLED' || $o['end'] <= $start || $o['start'] >= $end) {
				continue;
			}
			foreach ($o['attendees'] as $a) {
				if ($a['email'] === $email && $a['partstat'] !== 'DECLINED') {
					return $o;
				}
			}
		}
		return null;
	}

	/**
	 * Today plus the next working days until $count working days are shown.
	 * @return array{0: \DateTimeImmutable, 1: string[]} end of the window, and the shown dates (Y-m-d)
	 */
	private function workingWindow(\DateTimeImmutable $today, int $count, array $workdays): array {
		$shown = [$today->format('Y-m-d')]; // today is always shown (now/next status)
		$found = in_array((int)$today->format('N'), $workdays, true) ? 1 : 0;
		$day = $today;
		for ($i = 0; $i < 31 && $found < $count; $i++) {
			$day = $day->modify('+1 day');
			if (in_array((int)$day->format('N'), $workdays, true)) {
				$shown[] = $day->format('Y-m-d');
				$found++;
			}
		}
		return [$day->modify('+1 day'), $shown];
	}

	private function onShownDay(array $item, array $shownDays, \DateTimeZone $tz): bool {
		$start = (new \DateTimeImmutable('@' . $item['start']))->setTimezone($tz);
		$end = (new \DateTimeImmutable('@' . max($item['start'], $item['end'] - 1)))->setTimezone($tz);
		// A meeting spanning several days counts if any of its days is shown.
		for ($d = $start->setTime(0, 0); $d <= $end; $d = $d->modify('+1 day')) {
			if (in_array($d->format('Y-m-d'), $shownDays, true)) {
				return true;
			}
		}
		return false;
	}

	/** All occurrences in [from, to) of meetings that invite any of these room emails. */
	private function meetings(array $roomEmails, \DateTimeImmutable $from, \DateTimeImmutable $to): array {
		if ($roomEmails === []) {
			return [];
		}
		$values = [];
		foreach ($roomEmails as $email) {
			$values[] = 'mailto:' . $email;
			$values[] = $email;
		}

		// 1. Which calendar objects invite one of these rooms in this period?
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('o.id')
			->from('calendarobjects', 'o')
			->innerJoin('o', 'calendarobjects_props', 'p', $qb->expr()->eq('p.objectid', 'o.id'))
			->innerJoin('o', 'calendars', 'c', $qb->expr()->eq('c.id', 'o.calendarid'))
			->where($qb->expr()->eq('p.name', $qb->createNamedParameter('ATTENDEE')))
			->andWhere($qb->expr()->in($qb->func()->lower('p.value'), $qb->createNamedParameter($values, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($qb->expr()->eq('o.componenttype', $qb->createNamedParameter('VEVENT')))
			->andWhere($qb->expr()->eq('o.calendartype', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('o.deleted_at'))
			->andWhere($qb->expr()->isNull('c.deleted_at'))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('o.lastoccurence'),
				$qb->expr()->gte('o.lastoccurence', $qb->createNamedParameter($from->getTimestamp(), IQueryBuilder::PARAM_INT)),
			))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('o.firstoccurence'),
				$qb->expr()->lt('o.firstoccurence', $qb->createNamedParameter($to->getTimestamp(), IQueryBuilder::PARAM_INT)),
			))
			->setMaxResults(5000);
		$result = $qb->executeQuery();
		$ids = array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
		$result->closeCursor();

		// 2. Load them and keep the newest copy of each meeting (highest SEQUENCE, then last modified).
		$newest = [];
		foreach (array_chunk($ids, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('uid', 'calendardata', 'lastmodified')
				->from('calendarobjects')
				->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$result = $qb->executeQuery();
			while ($row = $result->fetch()) {
				$ics = is_resource($row['calendardata']) ? (string)stream_get_contents($row['calendardata']) : (string)$row['calendardata'];
				$master = $this->parser->master($ics);
				if ($master === null) {
					continue;
				}
				$uid = $master['uid'] !== '' ? $master['uid'] : (string)$row['uid'];
				$rank = [$master['sequence'], (int)$row['lastmodified']];
				if (!isset($newest[$uid]) || $rank > $newest[$uid]['rank']) {
					$newest[$uid] = ['rank' => $rank, 'ics' => $ics];
				}
			}
			$result->closeCursor();
		}

		// 3. Expand recurring meetings into the single occurrences in this period.
		$occurrences = [];
		foreach ($newest as $item) {
			foreach ($this->parser->occurrences($item['ics'], $from, $to) as $occurrence) {
				$occurrences[] = $occurrence;
			}
		}
		return $occurrences;
	}

	private function booking(array $o, array $room, array $visits, string $titleMode, string $guestMode, array &$usedVisits): array {
		$guests = [];
		foreach ($visits as $v) {
			$linked = $v['event_uid'] !== null && $v['event_uid'] === $o['uid'];
			$sameRoom = $v['event_uid'] === null && $v['room_name'] === $room['label']
				&& $v['start_at'] < $o['end'] && ($v['end_at'] ?? $v['start_at'] + 3600) > $o['start'];
			if ($linked || $sameRoom) {
				$guests[] = $v;
				$usedVisits[$v['id']] = true;
			}
		}
		$shown = $this->guestLabels($guests, $guestMode);

		return [
			'kind' => 'booking',
			'start' => $o['start'],
			'end' => $o['end'],
			'allDay' => $o['allDay'],
			'private' => $o['private'] || $titleMode === 'booked',
			'title' => ($o['private'] || $titleMode === 'booked') ? '' : $o['summary'],
			'organizer' => $this->organizerName($o['organizer']),
			'guests' => $shown,
			'guestCount' => count($guests),
			'arrived' => count(array_filter($guests, fn ($v) => $v['status'] === 'checked_in')),
			'arrivedAt' => $this->firstArrival($guests),
		];
	}

	/**
	 * Guests invited in Visitors with this room but no matching Calendar booking: shown as their own
	 * "Guest visit" entries, so reception and passers-by still see them. (Booking the room in Calendar
	 * is still needed to reserve it.)
	 */
	private function guestVisitsWithoutBooking(array $room, array $visits, array $usedVisits, string $guestMode, \DateTimeImmutable $from, \DateTimeImmutable $to): array {
		$groups = [];
		foreach ($visits as $v) {
			if (isset($usedVisits[$v['id']]) || $v['room_name'] !== $room['label']) {
				continue;
			}
			$start = $v['start_at'];
			$end = $v['end_at'] ?? $start + 3600;
			if ($end <= $from->getTimestamp() || $start >= $to->getTimestamp()) {
				continue;
			}
			// One entry per host and time, even with several guests.
			$groups[$v['host_uid'] . '|' . $start . '|' . $end][] = $v;
		}
		$entries = [];
		foreach ($groups as $guests) {
			$first = $guests[0];
			$entries[] = [
				'kind' => 'visit',
				'start' => $first['start_at'],
				'end' => $first['end_at'] ?? $first['start_at'] + 3600,
				'allDay' => false,
				'private' => false,
				'title' => '',
				'organizer' => $first['host_uid'] ? ($this->userManager->get($first['host_uid'])?->getDisplayName() ?? $first['host_uid']) : '',
				'guests' => $this->guestLabels($guests, $guestMode),
				'guestCount' => count($guests),
				'arrived' => count(array_filter($guests, fn ($v) => $v['status'] === 'checked_in')),
				'arrivedAt' => $this->firstArrival($guests),
			];
		}
		return $entries;
	}

	/** Earliest check-in time of the guests who are in the building, or null when none arrived. */
	private function firstArrival(array $guests): ?int {
		$times = array_map(fn ($v) => (int)$v['checked_in_at'],
			array_filter($guests, fn ($v) => $v['status'] === 'checked_in' && !empty($v['checked_in_at'])));
		return $times === [] ? null : min($times);
	}

	private function guestLabels(array $guests, string $guestMode): array {
		return match ($guestMode) {
			'names' => array_map(fn ($v) => $v['guest_company'] !== '' ? $v['guest_name'] . ' (' . $v['guest_company'] . ')' : $v['guest_name'], $guests),
			'company' => array_values(array_unique(array_filter(array_map(fn ($v) => $v['guest_company'], $guests)))),
			default => [],
		};
	}

	/** @var array<string, string> */
	private array $nameCache = [];

	private function organizerName(array $organizer): string {
		$email = $organizer['email'];
		if ($email === '') {
			return '';
		}
		if (!isset($this->nameCache[$email])) {
			$users = $this->userManager->getByEmail($email);
			$this->nameCache[$email] = $users ? $users[0]->getDisplayName() : ($organizer['name'] ?: strstr($email, '@', true));
		}
		return $this->nameCache[$email];
	}
}
