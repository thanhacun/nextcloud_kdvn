<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Controller;

use OCA\KdvnSensors\AppInfo\Application;
use OCA\KdvnSensors\Service\AppSettings;
use OCA\KdvnSensors\Service\SensorService;
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
 * Public sensor display. No login: the URL contains a secret key (hashed in appconfig).
 * ?building=ID shows only that office's sensors; ?lang=vi|en|ja, ?theme=dark|light are read by the page script.
 */
class DisplayController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AppSettings $settings,
		private SensorService $sensors,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_sensors_display')]
	public function show(string $key): Response {
		if (!$this->isValidKey($key)) {
			$response = new DataDisplayResponse('This display link is not valid. Ask IT for a new one.', Http::STATUS_FORBIDDEN, ['Content-Type' => 'text/plain; charset=utf-8']);
			$response->throttle();
			return $response;
		}
		Util::addScript(Application::APP_ID, 'display');
		Util::addStyle(Application::APP_ID, 'display');
		return new TemplateResponse(Application::APP_ID, 'display', [
			'dataUrl' => $this->urlGenerator->linkToRoute('kdvn_sensors.display.readings', ['key' => $key]),
		], TemplateResponse::RENDER_AS_BASE);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_sensors_display')]
	#[AnonRateLimit(limit: 120, period: 60)]
	public function readings(string $key, ?int $building = null): DataResponse {
		if (!$this->isValidKey($key)) {
			$response = new DataResponse(['message' => 'not_paired'], Http::STATUS_FORBIDDEN);
			$response->throttle();
			return $response;
		}
		try {
			return new DataResponse($this->sensors->readings($building));
		} catch (\Throwable $e) {
			$this->logger->error('kdvn_sensors display: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function isValidKey(string $key): bool {
		$stored = $this->settings->displayKeyHash();
		return $stored !== '' && hash_equals($stored, hash('sha256', $key));
	}
}
