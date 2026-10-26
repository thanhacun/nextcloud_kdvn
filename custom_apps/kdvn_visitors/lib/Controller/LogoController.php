<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Controller;

use OCA\KdvnVisitors\Service\Branding;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

/** Serves the uploaded logo to the tablet and the guest pass (both are public pages). */
class LogoController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private Branding $branding,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function show(): Response {
		try {
			[$content, $mime] = $this->branding->ownLogo();
		} catch (\Throwable) {
			return new DataDisplayResponse('', Http::STATUS_NOT_FOUND);
		}
		$response = new DataDisplayResponse($content, Http::STATUS_OK, ['Content-Type' => $mime]);
		$response->cacheFor(30 * 86400, true); // safe: the URL changes with every upload (?v=)
		return $response;
	}
}
