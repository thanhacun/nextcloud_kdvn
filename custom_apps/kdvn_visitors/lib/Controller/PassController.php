<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Controller;

use OCA\KdvnVisitors\AppInfo\Application;
use OCA\KdvnVisitors\Service\AppSettings;
use OCA\KdvnVisitors\Service\Branding;
use OCA\KdvnVisitors\Service\Texts;
use OCA\KdvnVisitors\Service\VisitFormatter;
use OCA\KdvnVisitors\Service\VisitService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Util;

/** The guest's visitor pass page (no login). */
class PassController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private VisitService $visits,
		private VisitFormatter $formatter,
		private AppSettings $settings,
		private IUserManager $userManager,
		private IURLGenerator $urlGenerator,
		private Branding $branding,
	) {
		parent::__construct($appName, $request);
	}

	// PublicPage: no login needed. BruteForceProtection: wrong tokens slow the sender down.
	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_pass')]
	#[AnonRateLimit(limit: 30, period: 60)]
	public function show(string $token): TemplateResponse {
		Util::addStyle(Application::APP_ID, 'pass');
		$visit = $this->visits->findByToken($token);

		if ($visit === null) {
			$response = new TemplateResponse(Application::APP_ID, 'pass', [
				'notFound' => true,
				'messages' => array_map(fn ($lang) => Texts::get($lang, 'pass_not_found'), Texts::LANGUAGES),
			], TemplateResponse::RENDER_AS_BASE, Http::STATUS_NOT_FOUND);
			$response->throttle();
			return $response;
		}

		Util::addScript(Application::APP_ID, 'qrcode-generator');
		Util::addScript(Application::APP_ID, 'pass');

		$lang = Texts::normalize($visit['language']);
		$hostName = $this->userManager->get((string)$visit['host_uid'])?->getDisplayName() ?? (string)$visit['host_uid'];

		$notice = '';
		if ($visit['status'] === 'cancelled') {
			$notice = Texts::get($lang, 'pass_cancelled', ['host' => $hostName]);
		} elseif ($visit['status'] === 'checked_out' && $visit['checked_out_at'] !== null) {
			$notice = Texts::get($lang, 'pass_checked_out', ['time' => $this->formatter->time($visit['checked_out_at'], (string)$visit['host_uid'], $lang)]);
		} elseif ($visit['status'] === 'checked_in' && $visit['checked_in_at'] !== null) {
			$notice = Texts::get($lang, 'pass_checked_in', ['time' => $this->formatter->time($visit['checked_in_at'], (string)$visit['host_uid'], $lang)]);
		}

		return new TemplateResponse(Application::APP_ID, 'pass', [
			'notFound' => false,
			'lang' => $lang,
			'labels' => array_combine(
				['pass_title', 'pass_show', 'pass_code', 'pass_brightness', 'when', 'host', 'room', 'address'],
				array_map(fn ($k) => Texts::get($lang, $k), ['pass_title', 'pass_show', 'pass_code', 'pass_brightness', 'when', 'host', 'room', 'address']),
			),
			'company' => $this->settings->companyName(),
			'address' => $this->settings->officeAddress(),
			'guestName' => $visit['guest_name'],
			'guestCompany' => $visit['guest_company'],
			'hostName' => $hostName,
			'roomName' => (string)($visit['room_name'] ?? ''),
			'when' => $this->formatter->when($visit, $lang),
			'code' => $visit['code'],
			'showQr' => in_array($visit['status'], ['invited', 'checked_in'], true),
			'notice' => $notice,
			'qrValue' => $this->urlGenerator->linkToRouteAbsolute('kdvn_visitors.pass.show', ['token' => $token]),
			'accent' => $this->settings->appearance()['accent'],
			'accentText' => $this->settings->appearance()['accentText'],
			'logoUrl' => $this->branding->logoUrl($this->settings->appearance()['showLogo']),
		], TemplateResponse::RENDER_AS_BASE);
	}
}
