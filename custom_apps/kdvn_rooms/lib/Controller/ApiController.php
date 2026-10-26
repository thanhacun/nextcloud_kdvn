<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\Controller;

use OCA\KdvnRooms\Service\ResourceStore;
use OCA\KdvnRooms\Service\UserError;
use OCA\KdvnRooms\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * JSON endpoints used by js/admin.js.
 *
 * Every method carries #[AuthorizedAdminSetting]: admins can always call it, and so can
 * members of a group the admin delegated the "Meeting rooms" settings page to.
 * CSRF protection stays on (the page sends the requesttoken header).
 */
class ApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ResourceStore $store,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function data(): DataResponse {
		return $this->run(fn () => $this->store->listAll());
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function createBuilding(): DataResponse {
		return $this->run(fn () => ['id' => $this->store->saveBuilding(null, $this->request->getParams())]);
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function updateBuilding(int $id): DataResponse {
		return $this->run(fn () => ['id' => $this->store->saveBuilding($id, $this->request->getParams())]);
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function deleteBuilding(int $id): DataResponse {
		return $this->run(function () use ($id) {
			$this->store->deleteBuilding($id);
			return ['ok' => true];
		});
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function createFloor(): DataResponse {
		return $this->run(fn () => ['id' => $this->store->saveFloor(null, $this->request->getParams())]);
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function updateFloor(int $id): DataResponse {
		return $this->run(fn () => ['id' => $this->store->saveFloor($id, $this->request->getParams())]);
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function deleteFloor(int $id): DataResponse {
		return $this->run(function () use ($id) {
			$this->store->deleteFloor($id);
			return ['ok' => true];
		});
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function createRoom(): DataResponse {
		return $this->run(fn () => ['id' => $this->store->saveRoom(null, $this->request->getParams())]);
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function updateRoom(int $id): DataResponse {
		return $this->run(fn () => ['id' => $this->store->saveRoom($id, $this->request->getParams())]);
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function deleteRoom(int $id): DataResponse {
		return $this->run(function () use ($id) {
			$this->store->deleteRoom($id);
			return ['ok' => true];
		});
	}

	/** Turns exceptions into JSON the page can show. */
	private function run(callable $action): DataResponse {
		try {
			return new DataResponse($action());
		} catch (UserError $e) {
			return new DataResponse(['message' => $e->getMessage()], $e->getCode() ?: Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('kdvn_rooms: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Something went wrong on the server. IT can find details in nextcloud.log.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
