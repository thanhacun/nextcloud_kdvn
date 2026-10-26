<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Controller;

use OCA\KdvnVisitors\AppInfo\Application;
use OCA\KdvnVisitors\Service\Texts;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

/** The "Visitors" page in the top bar, for every logged-in user. */
class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'visitors');
		Util::addStyle(Application::APP_ID, 'visitors');

		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return new TemplateResponse(Application::APP_ID, 'main', [
			'apiUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.visit_api.list'),
			'receptionUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.visit_api.reception'),
			'usersUrl' => $this->urlGenerator->linkToRoute('kdvn_visitors.visit_api.users'),
			'defaultLanguage' => Texts::normalize($this->config->getUserValue($uid, 'core', 'lang', 'en')),
		]);
	}
}
