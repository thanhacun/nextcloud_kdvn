<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Controller;

use OCA\KdvnVisitors\AppInfo\Application;
use OCA\KdvnVisitors\Service\RoomCatalog;
use OCA\KdvnVisitors\Service\Texts;
use OCA\KdvnVisitors\Service\TouchTicket;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

/**
 * The phone's side of the touch screen's QR code. A normal logged-in page (Nextcloud login, SSO included):
 * "Book Mekong for 30 minutes as <you>?" and a Confirm button. Confirming tells the screen who you are
 * (TouchTicket claim); the screen then books. Nobody can confirm as someone else: the user comes from
 * the session, never from the request.
 */
class ConfirmController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private TouchTicket $tickets,
		private RoomCatalog $rooms,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(string $nonce): TemplateResponse {
		Util::addScript(Application::APP_ID, 'confirm');
		Util::addStyle(Application::APP_ID, 'confirm');
		$user = $this->userSession->getUser();
		$claim = $this->tickets->claim($nonce);
		$room = null;
		foreach ($this->rooms->rooms() as $candidate) {
			if ($claim !== null && $candidate['id'] === $claim['room']) {
				$room = $candidate;
			}
		}
		return new TemplateResponse(Application::APP_ID, 'confirm', [
			'valid' => $claim !== null && $room !== null,
			'taken' => $claim !== null && $claim['uid'] !== null && $claim['uid'] !== $user?->getUID(),
			'room' => $room['label'] ?? '',
			'minutes' => $claim['minutes'] ?? 0,
			'name' => $user?->getDisplayName() ?? '',
			'confirmUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.confirm.confirm', ['nonce' => $nonce]),
			'lang' => Texts::normalize($this->config->getUserValue($user?->getUID() ?? '', 'core', 'lang', 'en')),
		]);
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	public function confirm(string $nonce): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['ok' => false, 'error' => 'login'], Http::STATUS_UNAUTHORIZED);
		}
		// Say it now, on the phone, instead of failing later on the screen: the booking is made in this person's calendar.
		if (trim((string)$user->getEMailAddress()) === '') {
			return new DataResponse(['ok' => false, 'error' => 'no_email']);
		}
		return new DataResponse(['ok' => $this->tickets->fillClaim($nonce, $user->getUID()), 'error' => 'gone']);
	}
}
