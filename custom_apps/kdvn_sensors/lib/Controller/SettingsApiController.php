<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Controller;

use OCA\KdvnSensors\Service\AppSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;

/** Admin settings. No attributes = Nextcloud's default: admins only, CSRF checked. */
class SettingsApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AppSettings $settings,
		private ISecureRandom $random,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param ?string $supplierKey null or '' = keep the stored key (it is never sent to the browser);
	 *   the "Remove the key" box sends clearSupplierKey = true.
	 */
	public function save(string $provider = 'off', array $thresholds = [], array $sensors = [], string $supplierUrl = '', ?string $supplierKey = null, int $supplierStaleMinutes = 5, bool $clearSupplierKey = false): DataResponse {
		$this->settings->setProvider($provider);
		$this->settings->setSupplier($supplierUrl, $clearSupplierKey ? '' : (($supplierKey === null || trim($supplierKey) === '') ? null : $supplierKey), $supplierStaleMinutes);
		$this->settings->setThresholds($thresholds);
		$this->settings->setSensors($sensors);
		return new DataResponse(['ok' => true]);
	}

	public function newDisplayLink(): DataResponse {
		$key = $this->random->generate(48, ISecureRandom::CHAR_ALPHANUMERIC);
		$this->settings->setDisplayKeyHash(hash('sha256', $key));
		return new DataResponse(['url' => $this->urlGenerator->linkToRouteAbsolute('kdvn_sensors.display.show', ['key' => $key])]);
	}
}
