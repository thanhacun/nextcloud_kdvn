<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * 0.2: walk-in guests and reception decisions.
 * - kind: "invited" or "walk_in"
 * - decided_by: who let a walk-in in (or declined); "auto" for the night job
 * - consent_at: when a walk-in ticked the consent box
 * - host_uid and guest_email become optional (walk-ins may not know either)
 */
class Version000200Date20261016000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('kdvnv_visits');

		if (!$table->hasColumn('kind')) {
			$table->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'invited']);
		}
		if (!$table->hasColumn('decided_by')) {
			$table->addColumn('decided_by', Types::STRING, ['notnull' => false, 'length' => 64]);
		}
		if (!$table->hasColumn('consent_at')) {
			$table->addColumn('consent_at', Types::BIGINT, ['notnull' => false]);
		}
		$table->getColumn('host_uid')->setNotnull(false);
		$table->getColumn('guest_email')->setNotnull(false);
		if (!$table->hasIndex('kdvnv_visits_status')) {
			$table->addIndex(['status'], 'kdvnv_visits_status');
		}
		return $schema;
	}
}
