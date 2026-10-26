<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\IDBConnection;

/**
 * Buildings and rooms from Calendar Resource Management (managed with kdvn_rooms).
 * A room's email is what Calendar puts in an event as the room "attendee".
 */
class RoomCatalog {
	private ?array $cache = null;

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/** @return array<int, array{id: int, email: string, name: string, label: string, number: string, capacity: ?int, floor: string, buildingId: ?int, building: string}> */
	public function rooms(): array {
		if ($this->cache !== null) {
			return $this->cache;
		}
		if (!$this->db->tableExists('calresources_rooms')) {
			return $this->cache = [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('r.id', 'r.email', 'r.display_name AS room', 'r.room_number', 'r.capacity',
			's.display_name AS floor', 'b.id AS building_id', 'b.display_name AS building')
			->from('calresources_rooms', 'r')
			->leftJoin('r', 'calresources_stories', 's', $qb->expr()->eq('s.id', 'r.story_id'))
			->leftJoin('s', 'calresources_buildings', 'b', $qb->expr()->eq('b.id', 's.building_id'))
			->orderBy('b.display_name')
			->addOrderBy('r.display_name');
		$result = $qb->executeQuery();
		$rooms = [];
		while ($row = $result->fetch()) {
			$rooms[] = [
				'id' => (int)$row['id'],
				'email' => strtolower(trim((string)$row['email'])),
				'name' => (string)$row['room'],
				'label' => $row['building'] ? $row['building'] . ' – ' . $row['room'] : (string)$row['room'],
				'number' => (string)($row['room_number'] ?? ''),
				'capacity' => ($row['capacity'] === null || (int)$row['capacity'] === 0) ? null : (int)$row['capacity'],
				'floor' => (string)($row['floor'] ?? ''),
				'buildingId' => $row['building_id'] === null ? null : (int)$row['building_id'],
				'building' => (string)($row['building'] ?? ''),
			];
		}
		$result->closeCursor();
		return $this->cache = $rooms;
	}

	/** @return string[] room labels for the invite form */
	public function names(): array {
		return array_column($this->rooms(), 'label');
	}

	/** @return array<string, array> rooms keyed by lowercase email */
	public function byEmail(): array {
		$map = [];
		foreach ($this->rooms() as $room) {
			if ($room['email'] !== '') {
				$map[$room['email']] = $room;
			}
		}
		return $map;
	}

	/** @return array<int, array{id: int, name: string}> */
	public function buildings(): array {
		$seen = [];
		foreach ($this->rooms() as $room) {
			if ($room['buildingId'] !== null) {
				$seen[$room['buildingId']] = ['id' => $room['buildingId'], 'name' => $room['building']];
			}
		}
		return array_values($seen);
	}
}
