<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Controller;

use OCA\KdvnVisitors\AppInfo\Application;
use OCA\KdvnVisitors\Service\AppSettings;
use OCA\KdvnVisitors\Service\Branding;
use OCA\KdvnVisitors\Service\StaffDirectory;
use OCA\KdvnVisitors\Service\UserError;
use OCA\KdvnVisitors\Service\VisitService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FeaturePolicy;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;
use Psr\Log\LoggerInterface;

/**
 * The reception tablet. It is not logged in to Nextcloud; instead its URL contains
 * a long secret key that an admin creates in the settings. Wrong keys are throttled.
 * The JSON endpoints skip CSRF tokens: the tablet has no session, the key is the protection.
 */
class KioskController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AppSettings $settings,
		private VisitService $visits,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
		private Branding $branding,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_kiosk')]
	public function show(string $key): Response {
		if ($this->settings->kioskOffice($key) === null) {
			$response = new DataDisplayResponse('This reception tablet link is not valid. Ask IT for a new one.', Http::STATUS_FORBIDDEN, ['Content-Type' => 'text/plain; charset=utf-8']);
			$response->throttle();
			return $response;
		}

		Util::addScript(Application::APP_ID, 'jsQR');
		Util::addScript(Application::APP_ID, 'kiosk');
		Util::addStyle(Application::APP_ID, 'kiosk');

		$url = fn (string $route) => $this->urlGenerator->linkToRoute('kdvn_visitors.kiosk.' . $route, ['key' => $key]);
		$response = new TemplateResponse(Application::APP_ID, 'kiosk', [
			'company' => $this->settings->companyName(),
			'retentionDays' => $this->settings->retentionDays(),
			'checkInUrl' => $url('check_in'),
			'checkOutUrl' => $url('check_out'),
			'hostsUrl' => $url('hosts'),
			'walkInUrl' => $url('walk_in'),
			'purposes' => $this->settings->purposes(),
			'maskHosts' => $this->settings->hostNamePrivacy() === 'masked',
			'appearance' => $this->settings->appearance() + [
				'logoUrl' => $this->branding->logoUrl($this->settings->appearance()['showLogo']),
			],
		], TemplateResponse::RENDER_AS_BASE);

		// Nextcloud blocks the camera by default ("camera 'none'"). The kiosk needs it.
		$policy = new FeaturePolicy();
		$policy->addAllowedCameraDomain("'self'");
		$policy->addAllowedFullScreenDomain("'self'");
		$response->setFeaturePolicy($policy);
		return $response;
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_kiosk')]
	#[AnonRateLimit(limit: 60, period: 60)]
	public function checkIn(string $key, string $value = ''): DataResponse {
		return $this->guarded($key, fn () => $this->visits->checkIn($value));
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_kiosk')]
	#[AnonRateLimit(limit: 60, period: 60)]
	public function checkOut(string $key, string $value = ''): DataResponse {
		return $this->guarded($key, fn () => $this->visits->checkOut($value));
	}

	/** Staff names for the walk-in form: at least 2 letters (3 when masked), at most 8 results, names only. */
	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_kiosk')]
	#[AnonRateLimit(limit: 120, period: 60)]
	public function hosts(string $key, string $q = ''): DataResponse {
		return $this->guarded($key, fn () => ['hosts' => $this->visits->searchStaff($q, 8, $this->settings->hostNamePrivacy() === 'masked')]);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'kdvn_visitors_kiosk')]
	#[AnonRateLimit(limit: 20, period: 60)]
	public function walkIn(string $key): DataResponse {
		return $this->guarded($key, function () use ($key) {
			try {
				$office = $this->settings->kioskOffice($key); // this tablet's office
				$result = $this->visits->walkIn($this->request->getParams(), $office['building'] ?? null);
				if (!empty($result['hostName']) && $this->settings->hostNamePrivacy() === 'masked') {
					$result['hostName'] = StaffDirectory::mask($result['hostName']);
				}
				return $result;
			} catch (UserError $e) {
				return ['ok' => false, 'error' => $e->getMessage() === 'consent' ? 'consent' : 'invalid', 'message' => $e->getMessage()];
			}
		});
	}

	private function guarded(string $key, callable $action): DataResponse {
		if ($this->settings->kioskOffice($key) === null) {
			$response = new DataResponse(['ok' => false, 'error' => 'not_paired'], Http::STATUS_FORBIDDEN);
			$response->throttle();
			return $response;
		}
		try {
			return new DataResponse($action());
		} catch (\Throwable $e) {
			$this->logger->error('kdvn_visitors kiosk: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['ok' => false, 'error' => 'network'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
