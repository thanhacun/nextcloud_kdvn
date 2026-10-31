<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCA\KdvnVisitors\BackgroundJob\SendInvitationJob;
use OCP\BackgroundJob\IJobList;
use OCP\IUserManager;

/**
 * Keeps visitor passes in step with Calendar.
 *
 * When an organizer saves a meeting that books a room (mode "room") or simply invites
 * outside guests (mode "all"), every outside guest gets a visit and a visitor pass email.
 * Later changes follow: new time or room -> the pass shows it; guest removed, guest declined
 * or meeting cancelled/deleted -> the pass is cancelled.
 *
 * Only rooms in buildings ticked as "Offices with visitor reception" count; a meeting in another
 * office's room never creates passes.
 *
 * Who counts as an outside guest: an individual attendee who is not a room, not the organizer,
 * not a Nextcloud user, and not on the organizer's own email domain (that is a colleague).
 *
 * Not handled yet: recurring meetings (no passes are created for series).
 *
 * Runs from CalendarSyncJob (cron), never while Calendar is saving: see CalendarListener.
 */
class CalendarSync {
	public function __construct(
		private EventParser $parser,
		private RoomCatalog $rooms,
		private VisitStore $store,
		private VisitService $visits,
		private AppSettings $settings,
		private IUserManager $userManager,
		private IJobList $jobList,
	) {
	}

	public function onSaved(string $principalUri, string $ics): void {
		$mode = $this->settings->autoPassMode();
		$owner = $this->ownerOf($principalUri);
		if ($mode === 'off' || $owner === null) {
			return;
		}
		$event = $this->parser->master($ics);
		if ($event === null || $event['uid'] === '' || !$this->isOrganizer($owner, $event)) {
			return; // attendees' copies of the meeting are ignored; only the organizer's copy counts
		}

		$existing = $this->store->listByEvent($event['uid'], $owner);
		if ($event['status'] === 'CANCELLED') {
			$this->cancelAll($existing);
			return;
		}
		if ($event['recurring'] || $event['end'] < time()) {
			return;
		}

		$roomsByEmail = $this->rooms->byEmail();
		$bookedRooms = array_values(array_filter($event['attendees'],
			fn ($a) => isset($roomsByEmail[$a['email']]) && $a['partstat'] !== 'DECLINED'));
		// Only offices with a visitor reception issue passes. A meeting in another office's room
		// creates none, even in mode "all" (the guest won't pass this reception).
		$receptionRooms = array_values(array_filter($bookedRooms,
			fn ($a) => $this->settings->hasReception($roomsByEmail[$a['email']]['buildingId'])));
		$roomName = $receptionRooms ? $roomsByEmail[$receptionRooms[0]['email']]['label'] : null;
		$buildingId = $receptionRooms ? $roomsByEmail[$receptionRooms[0]['email']]['buildingId'] : null; // the room's office
		$elsewhere = $bookedRooms !== [] && $receptionRooms === [];

		$guests = [];
		if (!$elsewhere && ($mode === 'all' || $receptionRooms !== [])) {
			foreach ($event['attendees'] as $attendee) {
				if ($this->isOutsideGuest($attendee, $event, $roomsByEmail)) {
					$guests[$attendee['email']] = $attendee;
				}
			}
		}

		// Existing passes: update, cancel, or bring back.
		foreach ($existing as $visit) {
			$email = strtolower((string)$visit['guest_email']);
			if (($visit['source'] ?? 'manual') !== 'calendar') {
				// Created in Visitors (e.g. "Book the room in Calendar"): the guest isn't an attendee of
				// that event, so only follow time and room changes; cancelling stays with the host.
				if ($visit['status'] === 'invited') {
					$this->store->update($visit['id'], array_diff_key($this->eventValues($event, $roomName ?? $visit['room_name'], $roomName !== null ? $buildingId : ($visit['building_id'] ?? null)), ['purpose' => 1]));
				}
				unset($guests[$email]);
				continue;
			}
			if (!isset($guests[$email])) {
				if ($visit['status'] === 'invited') {
					$this->store->update($visit['id'], ['status' => 'cancelled']);
				}
				continue;
			}
			$changes = $this->eventValues($event, $roomName, $buildingId);
			if ($visit['status'] === 'cancelled' && $visit['source'] === 'calendar') {
				$changes['status'] = 'invited'; // guest was added back to the meeting
			}
			if (in_array($visit['status'], ['invited', 'cancelled'], true)) {
				$this->store->update($visit['id'], $changes);
			}
			unset($guests[$email]);
		}

		// New guests.
		foreach ($guests as $email => $guest) {
			$manual = $this->store->findManualMatch($owner, $email, $event['start']);
			if ($manual !== null) {
				// The host already invited this guest by hand: link it, don't send a second pass.
				$this->store->update($manual['id'], ['event_uid' => $event['uid']] + $this->eventValues($event, $roomName ?? $manual['room_name'], $roomName !== null ? $buildingId : ($manual['building_id'] ?? null)));
				continue;
			}
			$visitId = $this->visits->createFromCalendar($owner, $guest, $event, $roomName, $buildingId);
			// The email is sent by the next cron run (within ~5 minutes), so saving the event stays fast.
			$this->jobList->add(SendInvitationJob::class, ['visitId' => $visitId]);
		}
	}

	public function onDeleted(string $principalUri, string $ics): void {
		$event = $this->parser->master($ics);
		if ($event !== null) {
			$this->onDeletedUid($principalUri, $event['uid']);
		}
	}

	/** The event was deleted or trashed: cancel the passes it created (only the organizer's ones). */
	public function onDeletedUid(string $principalUri, string $uid): void {
		$owner = $this->ownerOf($principalUri);
		if ($owner === null || $uid === '') {
			return;
		}
		$this->cancelAll($this->store->listByEvent($uid, $owner));
	}

	// -------------------------------------------------------------- helpers

	private function eventValues(array $event, ?string $roomName, ?int $buildingId): array {
		return [
			'building_id' => $buildingId,
			'start_at' => $event['start'],
			'end_at' => $event['end'],
			'room_name' => $roomName,
			'purpose' => $event['summary'] !== '' ? mb_substr($event['summary'], 0, 1000) : null,
		];
	}

	private function cancelAll(array $visits): void {
		foreach ($visits as $visit) {
			// Only passes this sync created; visits made in Visitors are cancelled by their host.
			if ($visit['status'] === 'invited' && ($visit['source'] ?? 'manual') === 'calendar') {
				$this->store->update($visit['id'], ['status' => 'cancelled']);
			}
		}
	}

	/** "principals/users/thanh" -> "thanh"; rooms' and resources' calendars -> null */
	private function ownerOf(string $principalUri): ?string {
		return str_starts_with($principalUri, 'principals/users/') ? substr($principalUri, strlen('principals/users/')) : null;
	}

	private function isOrganizer(string $owner, array $event): bool {
		$organizer = $event['organizer']['email'];
		if ($organizer === '') {
			return false; // no organizer = nobody was invited
		}
		$user = $this->userManager->get($owner);
		if ($user !== null && strtolower((string)$user->getEMailAddress()) === $organizer) {
			return true;
		}
		foreach ($this->userManager->getByEmail($organizer) as $candidate) {
			if ($candidate->getUID() === $owner) {
				return true;
			}
		}
		return false;
	}

	private function isOutsideGuest(array $attendee, array $event, array $roomsByEmail): bool {
		$email = $attendee['email'];
		if (!in_array($attendee['type'], ['INDIVIDUAL', 'UNKNOWN', ''], true)
			|| $attendee['partstat'] === 'DECLINED'
			|| isset($roomsByEmail[$email])
			|| $email === $event['organizer']['email']
			|| !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return false;
		}
		$organizerDomain = substr(strrchr($event['organizer']['email'], '@') ?: '', 1);
		if ($organizerDomain !== '' && str_ends_with($email, '@' . $organizerDomain)) {
			return false; // same company domain = colleague
		}
		return $this->userManager->getByEmail($email) === [];
	}
}
