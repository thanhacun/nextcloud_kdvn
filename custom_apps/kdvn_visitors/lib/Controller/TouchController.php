<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Controller;

use OCA\KdvnVisitors\AppInfo\Application;
use OCA\KdvnVisitors\Service\AppSettings;
use OCA\KdvnVisitors\Service\Branding;
use OCA\KdvnVisitors\Service\CalendarBooking;
use OCA\KdvnVisitors\Service\DisplayService;
use OCA\KdvnVisitors\Service\FaceRelay;
use OCA\KdvnVisitors\Service\RoomCatalog;
use OCA\KdvnVisitors\Service\TouchTicket;
use OCA\KdvnVisitors\Service\UserError;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Util;
use Psr\Log\LoggerInterface;

/**
 * Interactive room display for a touch screen next to the meeting rooms. Same page and data as the
 * read-only display (DisplayController), plus: tap a room for its details, and book a free room
 * for 15, 30 or 60 minutes starting now (a quick, unplanned meeting).
 *
 * Who is booking is proven, never typed: the person scans a QR code with their phone and confirms
 * while logged in to Nextcloud (ConfirmController). Any identity method ends in a TouchTicket
 * (signed, 60 s, single use) and book() accepts nothing else. A face check can later issue the same
 * ticket without a phone.
 *
 * Secret key in the URL, separate from the read-only display key (revoke one without the other).
 * The server checks the room again when booking, so a stale screen can never double-book.
 */
class TouchController extends Controller {
	/** Minutes a quick booking may last. */
	private const DURATIONS = [15, 30, 60];

	public function __construct(
		string $appName,
		IRequest $request,
		private AppSettings $settings,
		private Branding $branding,
		private DisplayService $display,
		private CalendarBooking $booking,
		private RoomCatalog $rooms,
		private TouchTicket $tickets,
		private FaceRelay $face,
		private IUserManager $userManager,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_touch')]
	public function show(string $key): Response {
		if (!$this->isValidKey($key)) {
			$response = new DataDisplayResponse('This link is not valid. Ask IT for a new one.', Http::STATUS_FORBIDDEN, ['Content-Type' => 'text/plain; charset=utf-8']);
			$response->throttle();
			return $response;
		}
		Util::addScript(Application::APP_ID, 'qrcode-generator');
		Util::addScript(Application::APP_ID, 'display');
		Util::addStyle(Application::APP_ID, 'display');
		$look = $this->settings->appearance();
		return new TemplateResponse(Application::APP_ID, 'display', [
			'dataUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.touch.data', ['key' => $key]),
			'claimUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.touch.claim', ['key' => $key]),
			'claimStatusUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.touch.claim_status', ['key' => $key, 'nonce' => 'NONCE']),
			'bookUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.touch.book', ['key' => $key]),
			// Only when face recognition is switched on and set up (Administration settings > Visitors).
			'faceUrl' => $this->settings->faceReady() ? $this->urlGenerator->linkToRoute('kdvn_visitors.touch.face', ['key' => $key]) : '',
			'company' => $this->settings->companyName(),
			'accent' => $look['accent'],
			'accentText' => $look['accentText'],
			'logoUrl' => $this->branding->logoUrl($look['showLogo']),
		], TemplateResponse::RENDER_AS_BASE);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_touch')]
	#[AnonRateLimit(limit: 120, period: 60)]
	public function data(string $key, ?int $building = null, int $days = 5): DataResponse {
		return $this->guarded($key, fn () => $this->display->data($building, $days));
	}

	/**
	 * One camera picture in, "who is it" out. The picture goes straight to Odoo (FaceRelay) and is not kept. For a known,
	 * enabled Nextcloud user the answer carries a TouchTicket, exactly like the QR way. Form fields: image (JPEG),
	 * roomId, minutes, optional building. state: none | many | unknown | ambiguous | ok, or error / off (the screen then
	 * offers the phone QR instead).
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_touch')]
	#[AnonRateLimit(limit: 200, period: 60)]
	public function face(string $key): DataResponse {
		return $this->guarded($key, function () {
			if (!$this->settings->faceReady()) {
				return ['state' => 'off'];
			}
			$room = $this->roomFor((int)$this->request->getParam('roomId', 0), $this->buildingParam());
			$minutes = (int)$this->request->getParam('minutes', 0);
			$file = $this->request->getUploadedFile('image');
			if ($room === null || !in_array($minutes, self::DURATIONS, true) || !is_array($file)
				|| ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > FaceRelay::MAX_BYTES || ($file['size'] ?? 0) < 100) {
				return ['state' => 'error'];
			}
			$jpeg = (string)file_get_contents($file['tmp_name']);
			if (!str_starts_with($jpeg, "\xFF\xD8\xFF")) {
				return ['state' => 'error']; // not a JPEG
			}
			try {
				$answer = $this->face->identify($jpeg);
			} catch (\Throwable $e) {
				if ($e->getCode() === 429) {
					return ['state' => 'wait']; // Odoo's rate limit: the screen keeps looking, one frame a second
				}
				// The reason (wrong key, Odoo down) is for the admin: log it, never the picture.
				$this->logger->warning('kdvn_visitors touch face: ' . $e->getMessage());
				return ['state' => 'error'];
			}
			unset($jpeg);
			if ($answer['state'] !== 'ok') {
				return ['state' => $answer['state']];
			}
			$user = $this->face->userForLogin((string)($answer['login'] ?? ''));
			if ($user === null) {
				return ['state' => 'unknown']; // an Odoo user without a Nextcloud account
			}
			return [
				'state' => 'ok',
				'name' => $user->getDisplayName(),
				'ticket' => $this->tickets->issue($user->getUID(), $room['id'], $minutes, $this->buildingParam()),
			];
		});
	}

	/**
	 * A screen asks for a QR code for this room and duration. The code opens the phone confirm page
	 * (ConfirmController, needs a Nextcloud login); poll claimStatus() for the result.
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_touch')]
	#[AnonRateLimit(limit: 20, period: 60)]
	public function claim(string $key): DataResponse {
		return $this->guarded($key, function () {
			$room = $this->roomFor((int)$this->request->getParam('roomId', 0), $this->buildingParam());
			$minutes = (int)$this->request->getParam('minutes', 0);
			if ($room === null || !in_array($minutes, self::DURATIONS, true)) {
				return ['ok' => false, 'error' => 'invalid'];
			}
			$nonce = $this->tickets->openClaim($room['id'], $minutes, $this->buildingParam());
			return [
				'ok' => true,
				'nonce' => $nonce,
				'url' => $this->urlGenerator->linkToRouteAbsolute('kdvn_visitors.confirm.show', ['nonce' => $nonce]),
				'seconds' => TouchTicket::CLAIM_SECONDS,
			];
		});
	}

	/** waiting | gone, or claimed with the signed ticket (given out once). */
	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_touch')]
	#[AnonRateLimit(limit: 90, period: 60)]
	public function claimStatus(string $key, string $nonce): DataResponse {
		return $this->guarded($key, function () use ($nonce) {
			$claim = $this->tickets->claim($nonce);
			if ($claim === null) {
				return ['state' => 'gone'];
			}
			if ($claim['uid'] === null) {
				return ['state' => 'waiting'];
			}
			$name = $this->userManager->get($claim['uid'])?->getDisplayName() ?? $claim['uid'];
			$ticket = $this->tickets->collect($nonce);
			return $ticket === null ? ['state' => 'gone'] : ['state' => 'claimed', 'ticket' => $ticket, 'name' => $name];
		});
	}

	/** Book the room named in a ticket, from now. The ticket says who, which room and how long. */
	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_touch')]
	#[AnonRateLimit(limit: 10, period: 60)]
	public function book(string $key): DataResponse {
		return $this->guarded($key, function () {
			$ticket = $this->tickets->redeem((string)$this->request->getParam('ticket', ''));
			if ($ticket === null || !in_array($ticket['minutes'], self::DURATIONS, true)) {
				return ['ok' => false, 'error' => 'ticket'];
			}
			// A screen only books rooms of its own office.
			$room = $this->roomFor($ticket['roomId'], $ticket['building']);
			if ($room === null || $this->userManager->get($ticket['uid']) === null) {
				return ['ok' => false, 'error' => 'invalid'];
			}
			$start = time();
			$end = $start + $ticket['minutes'] * 60;
			try {
				$this->booking->book($ticket['uid'], $room, $start, $end, 'Quick meeting',
					'Booked on the touch screen at the meeting rooms.');
			} catch (UserError $e) {
				// 409 = taken meanwhile: the screen reloads and shows the room as busy.
				$this->display->forgetCache();
				return ['ok' => false, 'error' => $e->getCode() === 409 ? 'taken' : 'rejected', 'message' => $e->getMessage()];
			}
			$this->display->forgetCache();
			// Audit trail without the key: who booked which room for how long.
			$this->logger->info('kdvn_visitors touch booking: room ' . $room['id'] . ' by ' . $ticket['uid'] . ' for ' . $ticket['minutes'] . ' min');
			return ['ok' => true, 'start' => $start, 'end' => $end];
		});
	}

	private function buildingParam(): ?int {
		$building = $this->request->getParam('building');
		return ($building === null || $building === '') ? null : (int)$building;
	}

	private function roomFor(int $roomId, ?int $building): ?array {
		foreach ($this->rooms->rooms() as $room) {
			if ($room['id'] === $roomId && ($building === null || $room['buildingId'] === $building)) {
				return $room;
			}
		}
		return null;
	}

	private function guarded(string $key, callable $action): DataResponse {
		if (!$this->isValidKey($key)) {
			$response = new DataResponse(['ok' => false, 'message' => 'not_paired'], Http::STATUS_FORBIDDEN);
			$response->throttle();
			return $response;
		}
		try {
			return new DataResponse($action());
		} catch (\Throwable $e) {
			$this->logger->error('kdvn_visitors touch: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['ok' => false, 'error' => 'network'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function isValidKey(string $key): bool {
		$stored = $this->settings->touchKeyHash();
		return $stored !== '' && hash_equals($stored, hash('sha256', $key));
	}
}
