<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\Controller;

use OCA\KdvnRooms\Service\Access;
use OCA\KdvnRooms\Service\ResourceStore;
use OCA\KdvnRooms\Service\UserError;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * JSON endpoints used by js/admin.js.
 *
 * Every method is #[NoAdminRequired] (any logged-in user reaches it) and run() then lets only
 * admins and the reception group through, see Service\Access.
 * CSRF protection stays on (the page sends the requesttoken header).
 */
class ApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ResourceStore $store,
		private Access $access,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function data(): DataResponse {
		return $this->run(fn () => $this->store->listAll());
	}

	#[NoAdminRequired]
	public function createBuilding(): DataResponse {
		return $this->run(fn () => ['id' => $this->store->saveBuilding(null, $this->request->getParams())]);
	}

	#[NoAdminRequired]
	public function updateBuilding(int $id): DataResponse {
		return $this->run(fn () => ['id' => $this->store->saveBuilding($id, $this->request->getParams())]);
	}

	#[NoAdminRequired]
	public function deleteBuilding(int $id): DataResponse {
		return $this->run(function () use ($id) {
			$this->store->deleteBuilding($id);
			return ['ok' => true];
		});
	}

	#[NoAdminRequired]
	public function createFloor(): DataResponse {
		return $this->run(fn () => ['id' => $this->store->saveFloor(null, $this->request->getParams())]);
	}

	#[NoAdminRequired]
	public function updateFloor(int $id): DataResponse {
		return $this->run(fn () => ['id' => $this->store->saveFloor($id, $this->request->getParams())]);
	}

	#[NoAdminRequired]
	public function deleteFloor(int $id): DataResponse {
		return $this->run(function () use ($id) {
			$this->store->deleteFloor($id);
			return ['ok' => true];
		});
	}

	#[NoAdminRequired]
	public function createRoom(): DataResponse {
		return $this->run(fn () => ['id' => $this->store->saveRoom(null, $this->request->getParams())]);
	}

	#[NoAdminRequired]
	public function updateRoom(int $id): DataResponse {
		return $this->run(fn () => ['id' => $this->store->saveRoom($id, $this->request->getParams())]);
	}

	#[NoAdminRequired]
	public function deleteRoom(int $id): DataResponse {
		return $this->run(function () use ($id) {
			$this->store->deleteRoom($id);
			return ['ok' => true];
		});
	}

	/** Turns exceptions into JSON the page can show. */
	private function run(callable $action): DataResponse {
		try {
			$this->access->require();
			return new DataResponse($action());
		} catch (UserError $e) {
			return new DataResponse(['message' => $e->getMessage()], $e->getCode() ?: Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('kdvn_rooms: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Something went wrong on the server. IT can find details in nextcloud.log.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
