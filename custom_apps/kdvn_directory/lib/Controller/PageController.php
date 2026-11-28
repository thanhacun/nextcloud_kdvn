<?php

declare(strict_types=1);

namespace OCA\KdvnDirectory\Controller;

use OCA\KdvnDirectory\AppInfo\Application;
use OCA\KdvnDirectory\Service\DirectoryService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

/** The "Directory" page in the top bar. */
class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private DirectoryService $directory,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		if (!$this->directory->mayUse($this->userSession->getUser())) {
			return new TemplateResponse(Application::APP_ID, 'denied', [], 'user', Http::STATUS_FORBIDDEN);
		}
		Util::addScript(Application::APP_ID, 'directory');
		Util::addStyle(Application::APP_ID, 'directory');
		return new TemplateResponse(Application::APP_ID, 'main', [
			'apiUrl' => $this->urlGenerator->linkToRoute('kdvn_directory.api.data'),
		]);
	}
}
