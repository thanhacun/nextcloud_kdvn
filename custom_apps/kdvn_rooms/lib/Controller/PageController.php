<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\Controller;

use OCA\KdvnRooms\AppInfo\Application;
use OCA\KdvnRooms\Service\Access;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\Util;

/** The "Meeting rooms" page in the top bar, for admins and the reception group. */
class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private Access $access,
		private IAppConfig $appConfig,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		if (!$this->access->isAllowed()) {
			return new TemplateResponse(Application::APP_ID, 'denied', [], 'user', Http::STATUS_FORBIDDEN);
		}
		Util::addScript(Application::APP_ID, 'admin');
		Util::addStyle(Application::APP_ID, 'admin');

		return new TemplateResponse(Application::APP_ID, 'main', [
			'emailDomain' => $this->appConfig->getValueString(Application::APP_ID, 'email_domain', 'resources.invalid'),
		]);
	}
}
