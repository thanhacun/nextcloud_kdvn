<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\Service;

use OCP\Calendar\Room\IManager as IRoomManager;
use OCP\DB\Exception as DbException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Reads and writes the tables owned by calendar_resource_management, doing the
 * same thing its occ commands do, plus the checks a web form needs.
 *
 * Works with calendar_resource_management 0.10 - 0.12 (Nextcloud 30 - 33).
 */
class ResourceStore {
	private const BUILDINGS = 'calresources_buildings';
	private const FLOORS = 'calresources_stories';
	private const ROOMS = 'calresources_rooms';
	private const RESOURCES = 'calresources_resources';
	private const VEHICLES = 'calresources_vehicles';
	private const RESTRICTS = 'calresources_restricts';

	/** JSON key => column */
	private const ROOM_FEATURES = [
		'hasPhone' => 'has_phone',
		'hasVideoConferencing' => 'has_video_conferencing',
		'hasTv' => 'has_tv',
		'hasProjector' => 'has_projector',
		'hasWhiteboard' => 'has_whiteboard',
		'isWheelchairAccessible' => 'is_wheelchair_accessible',
	];

	/** Same rule as calendar_resource_management's UidValidationService */
	private const UID_INVALID_CHARS = '/[^a-zA-Z0-9 _.@\-\']/';

	private ?bool $hasRestrictedColumn = null;

	public function __construct(
		private IDBConnection $db,
		private IRoomManager $roomManager,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
	) {
	}

	// ---------------------------------------------------------------- read

	public function listAll(): array {
		if (!$this->db->tableExists(self::ROOMS)) {
			return ['ready' => false];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('entity_id', 'group_id')
			->from(self::RESTRICTS)
			->where($qb->expr()->eq('entity_type', $qb->createNamedParameter('room')));
		$groupsByRoom = [];
		foreach ($this->fetch($qb) as $row) {
			$groupsByRoom[(int)$row['entity_id']][] = (string)$row['group_id'];
		}

		$emailProblems = $this->roomEmailProblems();

		return [
			'ready' => true,
			'buildings' => array_map(fn (array $r) => [
				'id' => (int)$r['id'],
				'displayName' => (string)$r['display_name'],
				'address' => (string)($r['address'] ?? ''),
				'description' => (string)($r['description'] ?? ''),
				'isWheelchairAccessible' => $this->toBool($r['is_wheelchair_accessible'] ?? false),
			], $this->fetchTable(self::BUILDINGS, 'display_name')),
			'floors' => array_map(fn (array $r) => [
				'id' => (int)$r['id'],
				'buildingId' => (int)$r['building_id'],
				'displayName' => (string)$r['display_name'],
			], $this->fetchTable(self::FLOORS, 'id')),
			'rooms' => array_map(function (array $r) use ($groupsByRoom, $emailProblems) {
				$room = [
					'id' => (int)$r['id'],
					'floorId' => (int)$r['story_id'],
					'uid' => (string)$r['uid'],
					'displayName' => (string)$r['display_name'],
					'email' => (string)$r['email'],
					'roomType' => (string)$r['room_type'],
					'contactPersonUserId' => (string)($r['contact_person_user_id'] ?? ''),
					'capacity' => ($r['capacity'] === null || (int)$r['capacity'] === 0) ? null : (int)$r['capacity'],
					'roomNumber' => (string)($r['room_number'] ?? ''),
					'groups' => $groupsByRoom[(int)$r['id']] ?? [],
					'emailProblem' => $emailProblems[(int)$r['id']] ?? '',
				];
				foreach (self::ROOM_FEATURES as $key => $column) {
					$room[$key] = $this->toBool($r[$column] ?? false);
				}
				return $room;
			}, $this->fetchTable(self::ROOMS, 'display_name')),
			'groups' => array_map(fn ($g) => [
				'id' => $g->getGID(),
				'name' => $g->getDisplayName(),
			], $this->groupManager->search('')),
		];
	}

	// ----------------------------------------------------------- buildings

	public function saveBuilding(?int $id, array $in): int {
		if ($id !== null) {
			$this->requireRow(self::BUILDINGS, $id, 'Building');
		}
		$id = $this->write(self::BUILDINGS, $id, [
			'display_name' => $this->requiredText($in, 'displayName', 'Building name'),
			'address' => $this->optionalText($in, 'address', 4000),
			'description' => $this->optionalText($in, 'description', 4000),
			'is_wheelchair_accessible' => !empty($in['isWheelchairAccessible']),
		]);
		$this->refreshRooms();
		return $id;
	}

	public function deleteBuilding(int $id): void {
		$this->requireRow(self::BUILDINGS, $id, 'Building');
		// The backend app has no foreign keys, so deleting a parent would leave orphans.
		if ($this->count(self::FLOORS, 'building_id', $id) > 0) {
			throw new UserError('This building still has floors. Delete them first.', 409);
		}
		foreach ([self::RESOURCES => 'equipment', self::VEHICLES => 'vehicles'] as $table => $label) {
			if ($this->db->tableExists($table) && $this->count($table, 'building_id', $id) > 0) {
				throw new UserError("This building still has $label that were added with occ. Ask IT to remove them first.", 409);
			}
		}
		$this->deleteWhere(self::BUILDINGS, 'id', $id);
		$this->refreshRooms();
	}

	// -------------------------------------------------------------- floors

	public function saveFloor(?int $id, array $in): int {
		$values = ['display_name' => $this->requiredText($in, 'displayName', 'Floor name')];
		if ($id === null) {
			$buildingId = (int)($in['buildingId'] ?? 0);
			$this->requireRow(self::BUILDINGS, $buildingId, 'Building');
			$values['building_id'] = $buildingId;
		} else {
			$this->requireRow(self::FLOORS, $id, 'Floor');
		}
		$id = $this->write(self::FLOORS, $id, $values);
		$this->refreshRooms();
		return $id;
	}

	public function deleteFloor(int $id): void {
		$this->requireRow(self::FLOORS, $id, 'Floor');
		if ($this->count(self::ROOMS, 'story_id', $id) > 0) {
			throw new UserError('This floor still has rooms. Delete or move them first.', 409);
		}
		$this->deleteWhere(self::FLOORS, 'id', $id);
		$this->refreshRooms();
	}

	// --------------------------------------------------------------- rooms

	public function saveRoom(?int $id, array $in): int {
		// Must run before the transaction: on PostgreSQL a failed probe query
		// would abort the whole transaction.
		$supportsRestrictedFlag = $this->supportsRestrictedFlag();

		$values = [
			'display_name' => $this->requiredText($in, 'displayName', 'Room name'),
			'room_type' => $this->optionalText($in, 'roomType', 255) ?? 'meeting-room',
			'room_number' => $this->optionalText($in, 'roomNumber', 255),
			'capacity' => $this->capacity($in['capacity'] ?? null),
			'contact_person_user_id' => $this->contactPerson($in),
		];
		foreach (self::ROOM_FEATURES as $key => $column) {
			$values[$column] = !empty($in[$key]);
		}
		$groups = $this->groups($in['groups'] ?? []);
		if ($supportsRestrictedFlag) {
			$values['restricted'] = $groups !== [];
		}

		if (isset($in['floorId'])) {
			$floorId = (int)$in['floorId'];
			$this->requireRow(self::FLOORS, $floorId, 'Floor');
			$values['story_id'] = $floorId;
		}

		if ($id === null) {
			if (!isset($values['story_id'])) {
				throw new UserError('Pick a floor for the room.');
			}
			// uid and email identify the room in calendar events, so they are
			// only set when the room is created.
			$uid = $this->requiredText($in, 'uid', 'Room ID');
			if (preg_match(self::UID_INVALID_CHARS, $uid)) {
				throw new UserError("Room ID may only contain letters, numbers, spaces and _ . @ - '");
			}
			$email = $this->requiredText($in, 'email', 'Booking email');
			if ($this->count(self::ROOMS, 'uid', $uid) > 0) {
				throw new UserError("Another room already uses the ID \"$uid\".", 409);
			}
			$this->requireUsableEmail($email, null);
			$values += ['uid' => $uid, 'email' => $email];
		} else {
			$this->requireRow(self::ROOMS, $id, 'Room');
			// The email is normally fixed after creation (bookings refer to it). A room whose email
			// is missing, invalid or shared can't be booked at all, so there it may be corrected.
			$newEmail = trim((string)($in['email'] ?? ''));
			if ($newEmail !== '' && ($this->roomEmailProblems()[$id] ?? '') !== '') {
				$this->requireUsableEmail($newEmail, $id);
				$values['email'] = $newEmail;
			}
		}

		$this->db->beginTransaction();
		try {
			$id = $this->write(self::ROOMS, $id, $values);
			$this->replaceRoomGroups($id, $groups);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			if ($e instanceof DbException && $e->getReason() === DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw new UserError('Another room already uses this ID or email.', 409);
			}
			throw $e;
		}

		$this->refreshRooms();
		return $id;
	}

	public function deleteRoom(int $id): void {
		$this->requireRow(self::ROOMS, $id, 'Room');
		$this->db->beginTransaction();
		try {
			$this->replaceRoomGroups($id, []);
			$this->deleteWhere(self::ROOMS, 'id', $id);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		$this->refreshRooms();
	}

	// ------------------------------------------------------------- helpers

	/**
	 * Why a room can't be booked in Calendar, per room id: "missing", "invalid", "duplicate"
	 * (another room/equipment/vehicle has the same email, ignoring upper/lower case) or "user"
	 * (a Nextcloud user has this email, so Calendar finds the person instead of the room).
	 *
	 * @return array<int, string>
	 */
	private function roomEmailProblems(): array {
		$owners = []; // lowercase email => list of "table:id"
		foreach ([self::ROOMS, self::RESOURCES, self::VEHICLES] as $table) {
			if (!$this->db->tableExists($table)) {
				continue;
			}
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'email')->from($table);
			foreach ($this->fetch($qb) as $row) {
				$owners[strtolower(trim((string)$row['email']))][] = $table . ':' . $row['id'];
			}
		}

		$problems = [];
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'email')->from(self::ROOMS);
		foreach ($this->fetch($qb) as $row) {
			$email = trim((string)$row['email']);
			$key = strtolower($email);
			$problem = match (true) {
				$email === '' => 'missing',
				filter_var($email, FILTER_VALIDATE_EMAIL) === false => 'invalid',
				count($owners[$key] ?? []) > 1 => 'duplicate',
				$this->userManager->getByEmail($email) !== [] => 'user',
				default => '',
			};
			if ($problem !== '') {
				$problems[(int)$row['id']] = $problem;
			}
		}
		return $problems;
	}

	private function requireUsableEmail(string $email, ?int $exceptRoomId): void {
		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			throw new UserError('Booking email is not a valid email address.');
		}
		foreach ([self::ROOMS, self::RESOURCES, self::VEHICLES] as $table) {
			if (!$this->db->tableExists($table)) {
				continue;
			}
			$qb = $this->db->getQueryBuilder();
			$qb->select('id')->from($table)
				->where($qb->expr()->eq($qb->func()->lower('email'), $qb->createNamedParameter(strtolower($email))));
			foreach ($this->fetch($qb) as $row) {
				if (!($table === self::ROOMS && (int)$row['id'] === $exceptRoomId)) {
					throw new UserError("Another room or resource already uses \"$email\".", 409);
				}
			}
		}
		if ($this->userManager->getByEmail($email) !== []) {
			throw new UserError("A Nextcloud user already has the email \"$email\". Use a different one for the room.", 409);
		}
	}

	/** Same as the occ commands: rebuild Nextcloud's room cache so Calendar sees changes now. */
	private function refreshRooms(): void {
		try {
			if (method_exists($this->roomManager, 'update')) {
				$this->roomManager->update();
			}
		} catch (\Throwable $e) {
			$this->logger->warning('kdvn_rooms: could not refresh room cache, changes show after the next background job', ['exception' => $e]);
		}
	}

	/** calendar_resource_management 0.12+ has a "restricted" column next to the restrictions table. */
	private function supportsRestrictedFlag(): bool {
		if ($this->hasRestrictedColumn === null) {
			try {
				$qb = $this->db->getQueryBuilder();
				$qb->select('restricted')->from(self::ROOMS)->setMaxResults(1);
				$qb->executeQuery()->closeCursor();
				$this->hasRestrictedColumn = true;
			} catch (DbException) {
				$this->hasRestrictedColumn = false;
			}
		}
		return $this->hasRestrictedColumn;
	}

	private function replaceRoomGroups(int $roomId, array $groups): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::RESTRICTS)
			->where($qb->expr()->eq('entity_type', $qb->createNamedParameter('room')))
			->andWhere($qb->expr()->eq('entity_id', $qb->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();

		foreach ($groups as $groupId) {
			$this->write(self::RESTRICTS, null, [
				'entity_type' => 'room',
				'entity_id' => $roomId,
				'group_id' => $groupId,
			]);
		}
	}

	private function groups(mixed $raw): array {
		if (!is_array($raw)) {
			throw new UserError('Groups must be a list.');
		}
		$groups = array_values(array_unique(array_map('strval', $raw)));
		foreach ($groups as $groupId) {
			if (!$this->groupManager->groupExists($groupId)) {
				throw new UserError("Group \"$groupId\" does not exist.");
			}
		}
		return $groups;
	}

	private function contactPerson(array $in): ?string {
		$userId = $this->optionalText($in, 'contactPersonUserId', 255);
		if ($userId !== null && !$this->userManager->userExists($userId)) {
			throw new UserError("There is no Nextcloud user with the ID \"$userId\".");
		}
		return $userId;
	}

	private function capacity(mixed $raw): ?int {
		if ($raw === null || $raw === '') {
			return null;
		}
		if (!is_numeric($raw) || (int)$raw < 0 || (int)$raw > 100000) {
			throw new UserError('Capacity must be a number of seats, 0 or more.');
		}
		return (int)$raw;
	}

	private function requiredText(array $in, string $key, string $label): string {
		$value = $this->optionalText($in, $key, 255);
		if ($value === null) {
			throw new UserError("$label is required.");
		}
		return $value;
	}

	private function optionalText(array $in, string $key, int $maxLength): ?string {
		$value = trim((string)($in[$key] ?? ''));
		if ($value === '') {
			return null;
		}
		if (mb_strlen($value) > $maxLength) {
			throw new UserError("\"$key\" is too long (max $maxLength characters).");
		}
		return $value;
	}

	private function requireRow(string $table, int $id, string $label): void {
		if ($this->count($table, 'id', $id) === 0) {
			throw new UserError("$label not found. It may have been deleted, reload the page.", 404);
		}
	}

	private function count(string $table, string $column, int|string $value): int {
		$qb = $this->db->getQueryBuilder();
		$type = is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR;
		$qb->select($qb->func()->count('*', 'n'))
			->from($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value, $type)));
		$result = $qb->executeQuery();
		$n = (int)$result->fetchOne();
		$result->closeCursor();
		return $n;
	}

	private function deleteWhere(string $table, string $column, int $value): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/** Insert when $id is null, otherwise update. Returns the row id. */
	private function write(string $table, ?int $id, array $values): int {
		$qb = $this->db->getQueryBuilder();
		if ($id === null) {
			$qb->insert($table);
			foreach ($values as $column => $value) {
				$qb->setValue($column, $qb->createNamedParameter($value, $this->paramType($value)));
			}
			$qb->executeStatement();
			return $qb->getLastInsertId();
		}

		$qb->update($table);
		foreach ($values as $column => $value) {
			$qb->set($column, $qb->createNamedParameter($value, $this->paramType($value)));
		}
		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
		return $id;
	}

	private function paramType(mixed $value): int|string {
		return match (true) {
			$value === null => IQueryBuilder::PARAM_NULL,
			is_bool($value) => IQueryBuilder::PARAM_BOOL,
			is_int($value) => IQueryBuilder::PARAM_INT,
			default => IQueryBuilder::PARAM_STR,
		};
	}

	private function fetchTable(string $table, string $orderBy): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($table)->orderBy($orderBy);
		return $this->fetch($qb);
	}

	private function fetch(IQueryBuilder $qb): array {
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	/** PostgreSQL returns true/false, MySQL "1"/"0", SQLite 1/0. */
	private function toBool(mixed $value): bool {
		return in_array($value, [true, 1, '1', 't', 'true'], true);
	}
}
