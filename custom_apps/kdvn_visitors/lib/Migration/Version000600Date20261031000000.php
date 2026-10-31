<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Migration;

use Closure;
use OCA\KdvnVisitors\Service\RoomCatalog;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * 0.6: every visit belongs to an office (a building of Calendar Resource Management), so each
 * office's reception only sees and hears about its own guests.
 * - building_id: the office. NULL = unknown (visible to every reception, like before 0.6).
 * Existing visits with a room get their office from the room name.
 */
class Version000600Date20261031000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('kdvnv_visits');
		if (!$table->hasColumn('building_id')) {
			$table->addColumn('building_id', Types::BIGINT, ['notnull' => false]);
		}
		if (!$table->hasIndex('kdvnv_visits_bld')) {
			$table->addIndex(['building_id'], 'kdvnv_visits_bld');
		}
		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$buildingByLabel = [];
		foreach ((new RoomCatalog($this->db))->rooms() as $room) {
			if ($room['buildingId'] !== null) {
				$buildingByLabel[$room['label']] = $room['buildingId'];
			}
		}
		foreach ($buildingByLabel as $label => $buildingId) {
			$qb = $this->db->getQueryBuilder();
			$qb->update('kdvnv_visits')
				->set('building_id', $qb->createNamedParameter($buildingId, IQueryBuilder::PARAM_INT))
				->where($qb->expr()->isNull('building_id'))
				->andWhere($qb->expr()->eq('room_name', $qb->createNamedParameter($label)));
			$qb->executeStatement();
		}
	}
}
