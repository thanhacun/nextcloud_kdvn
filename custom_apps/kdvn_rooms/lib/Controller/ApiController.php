<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\Controller;

use OCA\KdvnRooms\Service\Access;
use OCA\KdvnRooms\Service\ResourceStore;
use OCA\KdvnRooms\Service\RoomImporter;
use OCA\KdvnRooms\Service\SheetReader;
use OCA\KdvnRooms\Service\SheetWriter;
use OCA\KdvnRooms\Service\UserError;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
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
		private RoomImporter $importer,
		private SheetReader $reader,
		private SheetWriter $writer,
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

	/**
	 * Rooms from an Excel (.xlsx) or .csv file. apply=0 only checks the file and says what would happen (preview);
	 * apply=1 does it. Form fields: file, apply.
	 */
	#[NoAdminRequired]
	public function import(): DataResponse {
		return $this->run(function () {
			$file = $this->request->getUploadedFile('file');
			if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
				throw new UserError('Choose a file first (an upload may have failed, max 2 MB).');
			}
			$sheet = $this->reader->read($file['tmp_name'], (string)($file['name'] ?? ''));
			$apply = $this->request->getParam('apply') === '1';
			$result = $this->importer->run($sheet, $apply);
			if ($apply) {
				$this->logger->info('kdvn_rooms import: ' . $result['counts']['add'] . ' rooms added, ' . $result['counts']['skip'] . ' skipped, ' . $result['counts']['error'] . ' with errors');
			}
			return $result;
		});
	}

	/** An empty list with the right columns and three example rows, as a real Excel file. */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function importTemplate(): DataResponse|DataDownloadResponse {
		try {
			$this->access->require();
		} catch (UserError $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
		$bytes = $this->writer->build([
			['Building', 'Floor', 'Room', 'Room number', 'Seats', 'Type', 'Equipment', 'Who can book (groups)', 'Contact person', 'Room ID', 'Booking email', 'Address'],
			['Head office', '3rd floor', 'Meeting room A', '301', '8', 'Meeting room', 'video, tv, whiteboard', '', '', '', '', '12 Example Street'],
			['Head office', '3rd floor', 'Meeting room B', '302', '12', 'Meeting room', 'projector, whiteboard', 'Managers', '', '', '', ''],
			['Head office', '5th floor', 'Training room', '501', '30', 'Lecture hall', 'projector, phone', '', '', '', '', ''],
		]);
		return new DataDownloadResponse($bytes, 'meeting-rooms-template.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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
