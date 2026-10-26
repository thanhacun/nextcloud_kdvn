<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * 0.3: visits created from Calendar.
 * - source: "manual" (Visitors page / tablet) or "calendar" (created from a calendar invitation)
 * - event_uid: the calendar event's UID, so later changes to the event update the visit
 */
class Version000300Date20261020000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('kdvnv_visits');
		if (!$table->hasColumn('source')) {
			$table->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'manual']);
		}
		if (!$table->hasColumn('event_uid')) {
			$table->addColumn('event_uid', Types::STRING, ['notnull' => false, 'length' => 255]);
		}
		if (!$table->hasIndex('kdvnv_visits_event')) {
			$table->addIndex(['event_uid'], 'kdvnv_visits_event');
		}
		return $schema;
	}
}
