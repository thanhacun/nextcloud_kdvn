<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

/**
 * The rules of a visit.
 *
 * Statuses:  invited  -> checked_in -> checked_out          (invited guests)
 *            waiting  -> checked_in -> checked_out          (walk-ins, after approval)
 *            waiting  -> declined
 *            invited  -> cancelled
 *
 * Who may do what: the host on their own visits; reception (admins + the reception group) on all.
 */
class VisitService {
	/** Check-in opens this long before the start time... */
	public const EARLY_SECONDS = 3 * 3600;
	/** ...and closes this long after the end time (or after the start time when there is no end). */
	public const LATE_AFTER_END = 2 * 3600;
	public const LATE_NO_END = 8 * 3600;

	/** No 0/O, 1/I/L: easy to read and type. */
	private const CODE_CHARS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

	public function __construct(
		private VisitStore $store,
		private GuestMailer $mailer,
		private Messenger $messenger,
		private AppSettings $settings,
		private IMailer $mailValidator,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private ISecureRandom $random,
		private ICrypto $crypto,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
		private IConfig $config,
		private RoomCatalog $rooms,
		private CalendarBooking $booking,
		private StaffDirectory $staff,
	) {
	}

	// ------------------------------------------------------------ roles

	public function isReception(string $uid): bool {
		if ($this->groupManager->isAdmin($uid)) {
			return true;
		}
		$group = $this->settings->receptionGroup();
		return $group !== '' && $this->groupManager->isInGroup($uid, $group);
	}

	// ------------------------------------------------------------ lists

	public function listForHost(string $hostUid): array {
		return array_map([$this, 'toView'], $this->store->listForHost($hostUid, time() - 30 * 86400));
	}

	/** Reception board for one day ("YYYY-MM-DD" in the office time zone). */
	public function listForReception(string $uid, string $date): array {
		$this->requireReception($uid);
		$tz = $this->settings->officeTimezone();
		$day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz) ?: new \DateTimeImmutable('today', $tz);
		$from = $day->getTimestamp();
		$to = $day->modify('+1 day')->getTimestamp();
		return [
			'date' => $day->format('Y-m-d'),
			'visits' => array_map([$this, 'toView'], $this->store->listForReception($from, $to)),
		];
	}

	/** Staff search for "invite on behalf of" (reception) and for the tablet's walk-in form. */
	public function searchStaff(string $query, int $limit, bool $masked = false): array {
		return $this->staff->search($query, $limit, $masked);
	}

	// --------------------------------------------------- invited guests

	/** @return array{visit: array, passUrl: string, emailError: ?string} */
	public function create(string $uid, array $in): array {
		$host = $uid;
		$onBehalf = trim((string)($in['hostUid'] ?? ''));
		if ($onBehalf !== '' && $onBehalf !== $uid) {
			$this->requireReception($uid);
			if (!$this->userManager->userExists($onBehalf)) {
				throw new UserError('The host was not found.');
			}
			$host = $onBehalf;
		}

		$visit = [
			'kind' => 'invited',
			'host_uid' => $host,
			'guest_name' => $this->text($in, 'guestName', 'Guest name', 255, true),
			'guest_company' => $this->text($in, 'guestCompany', 'Company', 255, true),
			'guest_email' => $this->email($in, true),
			'guest_phone' => $this->phone($in),
			'purpose' => $this->text($in, 'purpose', 'Purpose', 1000, false),
			'room_name' => $this->text($in, 'roomName', 'Room', 255, false),
			'language' => Texts::normalize($in['language'] ?? 'en'),
			'status' => 'invited',
			'created_at' => time(),
		];
		[$visit['start_at'], $visit['end_at']] = $this->times($in);

		if ($visit['room_name'] !== null) {
			$chosen = $this->roomByLabel($visit['room_name']);
			if ($chosen !== null && !$this->settings->hasReception($chosen['buildingId'])) {
				throw new UserError($chosen['building'] . ' has no visitor reception, so visitor passes are not used there.');
			}
		}

		// "Book the room in Calendar": done first, so a taken room stops the invitation before any email.
		$eventUid = null;
		if (!empty($in['bookRoom']) && $visit['room_name'] !== null) {
			$room = $this->roomByLabel($visit['room_name']);
			if ($room === null) {
				throw new UserError('That room no longer exists. Pick another one.');
			}
			$guestLine = $visit['guest_name'] . ' (' . $visit['guest_company'] . ')';
			$eventUid = $this->booking->book(
				$host,
				$room,
				$visit['start_at'],
				$visit['end_at'] ?? $visit['start_at'] + 3600,
				$visit['purpose'] ?? ('Guest visit: ' . $guestLine),
				"Guest: $guestLine\nPhone: {$visit['guest_phone']}\nEmail: {$visit['guest_email']}\n\nCreated from Visitors. The visitor pass was sent separately.",
			);
			$visit['event_uid'] = $eventUid;
		}

		$visit = $this->insertWithToken($visit);
		$passUrl = $this->passUrl($visit['_token']);
		$emailError = $this->trySendEmail($visit, $passUrl);
		try {
			$this->messenger->inviteCreated($visit, $passUrl, $uid);
		} catch (\Throwable $e) {
			$this->logger->warning('kdvn_visitors: Talk message to the inviter failed: ' . $e->getMessage(), ['exception' => $e]);
		}
		return ['visit' => $this->toView($visit), 'passUrl' => $passUrl, 'emailError' => $emailError, 'roomBooked' => $eventUid !== null];
	}

	private function roomByLabel(string $label): ?array {
		foreach ($this->rooms->rooms() as $room) {
			if ($room['label'] === $label) {
				return $room;
			}
		}
		return null;
	}

	/** The pass link of an existing visit, e.g. to send it by Zalo. */
	public function link(string $uid, int $id): string {
		$visit = $this->requireAccess($uid, $id);
		if ($visit['kind'] !== 'invited') {
			throw new UserError('Walk-in guests have no pass link.');
		}
		return $this->passUrl($this->tokenOf($visit));
	}

	/** Send the invitation email again (same link). @return string|null error */
	public function resend(string $uid, int $id): ?string {
		$visit = $this->requireAccess($uid, $id);
		if ($visit['kind'] !== 'invited' || $visit['status'] === 'cancelled' || empty($visit['guest_email'])) {
			throw new UserError('There is no invitation to send for this visit.');
		}
		return $this->trySendEmail($visit, $this->passUrl($this->tokenOf($visit)));
	}

	// ------------------------------------------------------------ calendar

	/** A visit for an outside guest of a calendar meeting. The email goes out later (SendInvitationJob). */
	public function createFromCalendar(string $hostUid, array $guest, array $event, ?string $roomName): int {
		$email = strtolower($guest['email']);
		$domain = substr(strrchr($email, '@') ?: '', 1);
		$name = $guest['name'] !== '' ? $guest['name'] : ucwords(str_replace(['.', '_', '-'], ' ', strstr($email, '@', true) ?: $email));
		$visit = $this->insertWithToken([
			'kind' => 'invited',
			'source' => 'calendar',
			'event_uid' => mb_substr($event['uid'], 0, 255),
			'host_uid' => $hostUid,
			'guest_name' => mb_substr($name, 0, 255),
			'guest_company' => mb_substr($domain, 0, 255), // best guess from the email; the calendar has no company field
			'guest_email' => $email,
			'guest_phone' => '',
			'purpose' => $event['summary'] !== '' ? mb_substr($event['summary'], 0, 1000) : null,
			'room_name' => $roomName,
			'language' => Texts::normalize($this->config->getUserValue($hostUid, 'core', 'lang', 'en')),
			'status' => 'invited',
			'start_at' => $event['start'],
			'end_at' => $event['end'],
			'created_at' => time(),
		]);
		return $visit['id'];
	}

	public function sendInvitationFor(int $id): void {
		$visit = $this->store->find($id);
		if ($visit === null || $visit['status'] !== 'invited' || empty($visit['guest_email']) || ($visit['end_at'] ?? $visit['start_at']) < time()) {
			return; // cancelled or moved into the past before cron ran
		}
		$this->trySendEmail($visit, $this->passUrl($this->tokenOf($visit)));
	}

	// ------------------------------------------------------- staff actions

	/** approve | decline | check-in | check-out | cancel */
	public function act(string $uid, int $id, string $action): array {
		$visit = $this->requireAccess($uid, $id);
		$now = time();

		switch ($action) {
			case 'approve':
			case 'decline':
				$this->requireStatus($visit, ['waiting'], 'This guest is not waiting for a decision any more.');
				$approved = $action === 'approve';
				$values = $approved
					? ['status' => 'checked_in', 'checked_in_at' => $now, 'decided_by' => $uid]
					: ['status' => 'declined', 'decided_by' => $uid];
				$this->store->update($id, $values);
				$this->messenger->decided($visit, $approved, $uid);
				break;

			case 'check-in': // reception or host checks in an invited guest by hand
				$this->requireStatus($visit, ['invited'], 'Only invited guests can be checked in here.');
				$this->store->update($id, ['status' => 'checked_in', 'checked_in_at' => $now]);
				$this->messenger->invitedArrived($visit, $uid);
				break;

			case 'check-out':
				$this->requireStatus($visit, ['checked_in'], 'This guest is not in the building.');
				$this->store->update($id, ['status' => 'checked_out', 'checked_out_at' => $now]);
				break;

			case 'cancel':
				$this->requireStatus($visit, ['invited'], 'Only visits that have not started can be cancelled.');
				$this->store->update($id, ['status' => 'cancelled']);
				break;

			default:
				throw new UserError('Unknown action.');
		}
		$view = $this->toView($this->store->find($id));
		// Nextcloud has no public API to delete a calendar event, so remind the host.
		$view['calendarNote'] = $action === 'cancel' && !empty($visit['event_uid']) && ($visit['source'] ?? 'manual') === 'manual'
			? 'The room is still booked in Calendar. Delete the event "' . ($visit['purpose'] ?? 'Guest visit') . '" there to free the room.'
			: '';
		return $view;
	}

	// --------------------------------------------------------------- tablet

	/**
	 * Check in from the tablet with the QR content (pass URL / token) or the typed code.
	 * @return array{ok: bool, error?: string, already?: bool, guestName?: string, hostName?: string, roomName?: string, startAt?: int}
	 */
	public function checkIn(string $input): array {
		$visit = $this->findByInput($input);
		if ($visit === null) {
			return ['ok' => false, 'error' => 'not_found'];
		}

		$problem = match ($visit['status']) {
			'cancelled' => 'cancelled',
			'declined' => 'declined',
			'checked_out' => 'expired',
			default => null,
		};
		if ($problem === null && $visit['status'] === 'invited') {
			$now = time();
			$closes = $visit['end_at'] !== null
				? $visit['end_at'] + self::LATE_AFTER_END
				: $visit['start_at'] + self::LATE_NO_END;
			if ($now < $visit['start_at'] - self::EARLY_SECONDS) {
				$problem = 'too_early';
			} elseif ($now > $closes) {
				$problem = 'expired';
			}
		}
		if ($problem !== null) {
			$this->messenger->problem($visit, $problem);
			return ['ok' => false, 'error' => $problem === 'declined' ? 'cancelled' : $problem, 'startAt' => $visit['start_at']];
		}

		$result = [
			'ok' => true,
			'already' => $visit['status'] !== 'invited',
			'guestName' => $visit['guest_name'],
			'hostName' => $this->hostName($visit),
			'roomName' => (string)($visit['room_name'] ?? ''),
		];
		if ($visit['status'] === 'invited') {
			$this->store->update($visit['id'], ['status' => 'checked_in', 'checked_in_at' => time()]);
			$this->messenger->invitedArrived($visit);
		}
		return $result;
	}

	/** @return array{ok: bool, error?: string, guestName?: string} */
	public function checkOut(string $input): array {
		$visit = $this->findByInput($input);
		if ($visit === null) {
			return ['ok' => false, 'error' => 'not_found'];
		}
		if ($visit['status'] === 'checked_out') {
			return ['ok' => false, 'error' => 'already_out'];
		}
		if ($visit['status'] !== 'checked_in') {
			return ['ok' => false, 'error' => 'not_checked_in'];
		}
		$this->store->update($visit['id'], ['status' => 'checked_out', 'checked_out_at' => time()]);
		return ['ok' => true, 'guestName' => $visit['guest_name']];
	}

	/** A guest without an appointment registers at the tablet. */
	public function walkIn(array $in): array {
		if (empty($in['consent'])) {
			throw new UserError('consent');
		}
		$hostUid = trim((string)($in['hostUid'] ?? ''));
		if ($hostUid !== '' && !$this->userManager->get($hostUid)?->isEnabled()) {
			$hostUid = '';
		}
		$now = time();
		$visit = $this->insertWithToken([
			'kind' => 'walk_in',
			'host_uid' => $hostUid !== '' ? $hostUid : null,
			'guest_name' => $this->text($in, 'guestName', 'Name', 255, true),
			'guest_company' => $this->text($in, 'guestCompany', 'Company', 255, true),
			'guest_email' => $this->email($in, false),
			'guest_phone' => $this->phone($in),
			'purpose' => $this->text($in, 'purpose', 'Purpose', 1000, false),
			'room_name' => null,
			'language' => Texts::normalize($in['language'] ?? 'vi'),
			'status' => 'waiting',
			'start_at' => $now,
			'end_at' => null,
			'consent_at' => $now,
			'created_at' => $now,
		]);
		$this->messenger->walkInArrived($visit);
		return ['ok' => true, 'hostName' => $visit['host_uid'] ? $this->hostName($visit) : ''];
	}

	// --------------------------------------------------------------- guests

	public function findByToken(string $token): ?array {
		if (!preg_match('/^[A-Za-z0-9]{20,64}$/', $token)) {
			return null;
		}
		return $this->store->findByTokenHash(hash('sha256', $token));
	}

	// -------------------------------------------------------------- helpers

	private function findByInput(string $input): ?array {
		$input = trim($input);
		if (preg_match('~/pass/([A-Za-z0-9]+)~', $input, $m)) {
			$input = $m[1]; // QR codes contain the full pass URL
		}
		$code = strtoupper((string)preg_replace('/[\s-]/', '', $input));
		return strlen($code) === 6 ? $this->store->findActiveByCode($code) : $this->findByToken($input);
	}

	private function insertWithToken(array $visit): array {
		$token = $this->random->generate(40, ISecureRandom::CHAR_ALPHANUMERIC);
		$visit['code'] = $this->newCode();
		$visit['token_hash'] = hash('sha256', $token);
		$visit['token_enc'] = $this->crypto->encrypt($token);
		$visit['id'] = $this->store->insert($visit);
		$visit += ['checked_in_at' => null, 'checked_out_at' => null, 'decided_by' => null];
		$visit['_token'] = $token;
		return $visit;
	}

	private function trySendEmail(array $visit, string $passUrl): ?string {
		try {
			$this->mailer->sendInvitation($visit, $passUrl);
			return null;
		} catch (\Throwable $e) {
			$this->logger->error('kdvn_visitors: invitation email failed: ' . $e->getMessage(), ['exception' => $e]);
			return 'The email could not be sent. Copy the pass link and send it another way.';
		}
	}

	private function toView(array $v): array {
		return [
			'id' => $v['id'],
			'kind' => $v['kind'] ?? 'invited',
			'source' => $v['source'] ?? 'manual',
			'roomBooked' => !empty($v['event_uid']),
			'hostUid' => (string)($v['host_uid'] ?? ''),
			'hostName' => $this->hostName($v),
			'guestName' => $v['guest_name'],
			'guestCompany' => $v['guest_company'],
			'guestEmail' => (string)($v['guest_email'] ?? ''),
			'guestPhone' => $v['guest_phone'],
			'purpose' => (string)($v['purpose'] ?? ''),
			'roomName' => (string)($v['room_name'] ?? ''),
			'language' => $v['language'],
			'startAt' => $v['start_at'],
			'endAt' => $v['end_at'],
			'code' => $v['code'],
			'status' => $v['status'],
			'checkedInAt' => $v['checked_in_at'] ?? null,
			'checkedOutAt' => $v['checked_out_at'] ?? null,
			'decidedBy' => !empty($v['decided_by']) && $v['decided_by'] !== 'auto'
				? ($this->userManager->get($v['decided_by'])?->getDisplayName() ?? $v['decided_by'])
				: '',
		];
	}

	private function hostName(array $visit): string {
		$uid = (string)($visit['host_uid'] ?? '');
		return $uid === '' ? '' : ($this->userManager->get($uid)?->getDisplayName() ?? $uid);
	}

	private function requireReception(string $uid): void {
		if (!$this->isReception($uid)) {
			throw new UserError('Only reception can do this.', 403);
		}
	}

	private function requireAccess(string $uid, int $id): array {
		$visit = $this->store->find($id);
		if ($visit === null || ($visit['host_uid'] !== $uid && !$this->isReception($uid))) {
			throw new UserError('Visit not found.', 404);
		}
		return $visit;
	}

	private function requireStatus(array $visit, array $allowed, string $message): void {
		if (!in_array($visit['status'], $allowed, true)) {
			throw new UserError($message, 409);
		}
	}

	private function tokenOf(array $visit): string {
		try {
			return $this->crypto->decrypt((string)$visit['token_enc']);
		} catch (\Throwable) {
			throw new UserError('The pass link for this visit cannot be recovered.', 500);
		}
	}

	private function passUrl(string $token): string {
		return $this->urlGenerator->linkToRouteAbsolute('kdvn_visitors.pass.show', ['token' => $token]);
	}

	private function newCode(): string {
		for ($i = 0; $i < 20; $i++) {
			$code = $this->random->generate(6, self::CODE_CHARS);
			if (!$this->store->codeInUse($code)) {
				return $code;
			}
		}
		throw new \RuntimeException('Could not find a free visitor code.');
	}

	/** @return array{0: int, 1: ?int} */
	private function times(array $in): array {
		$start = is_numeric($in['startAt'] ?? null) ? (int)$in['startAt'] : 0;
		$end = is_numeric($in['endAt'] ?? null) ? (int)$in['endAt'] : null;
		$now = time();
		if ($start < $now - 12 * 3600 || $start > $now + 366 * 86400) {
			throw new UserError('Pick a visit date from today up to one year ahead.');
		}
		if ($end !== null && ($end <= $start || $end > $start + 24 * 3600)) {
			throw new UserError('The end time must be after the start time, on the same day.');
		}
		return [$start, $end];
	}

	private function email(array $in, bool $required): ?string {
		$email = $this->text($in, 'guestEmail', 'Email', 255, $required);
		if ($email !== null && !$this->mailValidator->validateMailAddress($email)) {
			throw new UserError('The guest email address is not valid.');
		}
		return $email;
	}

	private function phone(array $in): string {
		$phone = (string)$this->text($in, 'guestPhone', 'Phone', 64, true);
		if (!preg_match('/^[0-9+()\-.\s]{6,}$/', $phone)) {
			throw new UserError('The phone number may only contain digits, spaces and + ( ) - .');
		}
		return $phone;
	}

	private function text(array $in, string $key, string $label, int $max, bool $required): ?string {
		$value = trim((string)($in[$key] ?? ''));
		if ($value === '') {
			if ($required) {
				throw new UserError("$label is required.");
			}
			return null;
		}
		if (mb_strlen($value) > $max) {
			throw new UserError("$label is too long (max $max characters).");
		}
		return $value;
	}
}
