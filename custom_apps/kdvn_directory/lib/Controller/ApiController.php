<?php

declare(strict_types=1);

namespace OCA\KdvnDirectory\Controller;

use OCA\KdvnDirectory\Service\DirectoryService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class ApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private DirectoryService $directory,
	) {
		parent::__construct($appName, $request);
	}

	/** Never drop the mayUse() check: this is the only place member lists leave the server. */
	#[NoAdminRequired]
	public function data(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null || !$this->directory->mayUse($user)) {
			return new DataResponse(['message' => 'Not allowed.'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($this->directory->build($user));
	}
}
