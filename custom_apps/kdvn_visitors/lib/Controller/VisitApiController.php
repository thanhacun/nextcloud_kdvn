<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Controller;

use OCA\KdvnVisitors\Service\AppSettings;
use OCA\KdvnVisitors\Service\RoomCatalog;
use OCA\KdvnVisitors\Service\UserError;
use OCA\KdvnVisitors\Service\VisitService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * JSON API for the Visitors page. Hosts see and change their own visits;
 * reception (admins + the reception group of the visit's office) can see and change that office's.
 * The permission checks live in VisitService.
 */
class VisitApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private VisitService $visits,
		private RoomCatalog $rooms,
		private AppSettings $settings,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function list(): DataResponse {
		return $this->run(fn () => [
			'visits' => $this->visits->listForHost($this->uid()),
			'rooms' => array_map(fn ($r) => [
				'label' => $r['label'],
				'bookable' => $r['email'] !== '' && filter_var($r['email'], FILTER_VALIDATE_EMAIL) !== false,
				'buildingId' => $r['buildingId'],
			], array_values(array_filter($this->rooms->rooms(), fn ($r) => $this->settings->hasReception($r['buildingId'])))),
			'offices' => $this->visits->offices(),
			'defaultOffice' => $this->visits->defaultOffice($this->uid()),
			'isReception' => $this->visits->isReception($this->uid()),
		]);
	}

	#[NoAdminRequired]
	public function reception(string $date = ''): DataResponse {
		return $this->run(fn () => $this->visits->listForReception($this->uid(), $date));
	}

	/** Staff search for "invite on behalf of". Reception only. */
	#[NoAdminRequired]
	public function users(string $q = ''): DataResponse {
		return $this->run(function () use ($q) {
			if (!$this->visits->isReception($this->uid())) {
				throw new UserError('Only reception can do this.', 403);
			}
			return ['users' => $this->visits->searchStaff($q, 8)];
		});
	}

	// Rate limit: stops a hijacked account from sending hundreds of emails.
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 3600)]
	public function create(): DataResponse {
		return $this->run(fn () => $this->visits->create($this->uid(), $this->request->getParams()));
	}

	#[NoAdminRequired]
	public function link(int $id): DataResponse {
		return $this->run(fn () => ['passUrl' => $this->visits->link($this->uid(), $id)]);
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 3600)]
	public function resend(int $id): DataResponse {
		return $this->run(fn () => ['emailError' => $this->visits->resend($this->uid(), $id)]);
	}

	/** approve | decline | check-in | check-out | cancel */
	#[NoAdminRequired]
	public function perform(int $id, string $operation): DataResponse {
		return $this->run(fn () => ['visit' => $this->visits->act($this->uid(), $id, $operation)]);
	}

	private function uid(): string {
		return $this->userSession->getUser()->getUID();
	}

	private function run(callable $action): DataResponse {
		try {
			return new DataResponse($action());
		} catch (UserError $e) {
			return new DataResponse(['message' => $e->getMessage()], $e->getCode() ?: Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('kdvn_visitors: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Something went wrong on the server. IT can find details in nextcloud.log.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
