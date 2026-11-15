<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Service;

use OCP\IDBConnection;

/**
 * Buildings (offices) as defined in Calendar Resource Management. Read only: the calresources_*
 * tables belong to calendar_resource_management and their schema is never changed here.
 */
class BuildingCatalog {
	private ?array $cache = null;

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/** @return array<int, string> building id => display name */
	public function names(): array {
		if ($this->cache !== null) {
			return $this->cache;
		}
		if (!$this->db->tableExists('calresources_buildings')) {
			return $this->cache = [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'display_name')->from('calresources_buildings')->orderBy('display_name');
		$result = $qb->executeQuery();
		$names = [];
		while ($row = $result->fetch()) {
			$names[(int)$row['id']] = (string)$row['display_name'];
		}
		$result->closeCursor();
		return $this->cache = $names;
	}
}
