<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Controller;

use OCA\KdvnVisitors\AppInfo\Application;
use OCA\KdvnVisitors\Service\AppSettings;
use OCA\KdvnVisitors\Service\Branding;
use OCA\KdvnVisitors\Service\DisplayService;
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
use OCP\Util;
use Psr\Log\LoggerInterface;

/**
 * Public room display for a screen in front of the meeting rooms. No login: the URL contains a
 * secret display key (separate from the tablet key). ?building=ID picks the building,
 * ?lang=vi|en|ja the language, ?theme=dark|light the colors.
 */
class DisplayController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AppSettings $settings,
		private Branding $branding,
		private DisplayService $display,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_display')]
	public function show(string $key): Response {
		if (!$this->isValidKey($key)) {
			$response = new DataDisplayResponse('This display link is not valid. Ask IT for a new one.', Http::STATUS_FORBIDDEN, ['Content-Type' => 'text/plain; charset=utf-8']);
			$response->throttle();
			return $response;
		}
		Util::addScript(Application::APP_ID, 'display');
		Util::addStyle(Application::APP_ID, 'display');
		$look = $this->settings->appearance();
		return new TemplateResponse(Application::APP_ID, 'display', [
			'dataUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.display.data', ['key' => $key]),
			'company' => $this->settings->companyName(),
			'accent' => $look['accent'],
			'accentText' => $look['accentText'],
			'logoUrl' => $this->branding->logoUrl($look['showLogo']),
		], TemplateResponse::RENDER_AS_BASE);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_display')]
	#[AnonRateLimit(limit: 120, period: 60)]
	public function data(string $key, ?int $building = null, int $days = 5): DataResponse {
		if (!$this->isValidKey($key)) {
			$response = new DataResponse(['message' => 'not_paired'], Http::STATUS_FORBIDDEN);
			$response->throttle();
			return $response;
		}
		try {
			return new DataResponse($this->display->data($building, $days));
		} catch (\Throwable $e) {
			$this->logger->error('kdvn_visitors display: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function isValidKey(string $key): bool {
		$stored = $this->settings->displayKeyHash();
		return $stored !== '' && hash_equals($stored, hash('sha256', $key));
	}
}
