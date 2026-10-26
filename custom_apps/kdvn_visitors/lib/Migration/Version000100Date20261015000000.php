<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Creates the visits table. Nextcloud runs this automatically when the app is
 * enabled (or upgraded) and remembers that it ran, so it never runs twice.
 */
class Version000100Date20261015000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('kdvnv_visits')) {
			return null;
		}

		$table = $schema->createTable('kdvnv_visits');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$table->addColumn('host_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('guest_name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('guest_company', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('guest_email', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('guest_phone', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('purpose', Types::STRING, ['notnull' => false, 'length' => 1000]);
		$table->addColumn('room_name', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('language', Types::STRING, ['notnull' => true, 'length' => 8, 'default' => 'en']);
		$table->addColumn('start_at', Types::BIGINT, ['notnull' => true]);   // unix timestamp
		$table->addColumn('end_at', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('token_hash', Types::STRING, ['notnull' => true, 'length' => 64]); // sha256 of the pass token, for lookups
		$table->addColumn('token_enc', Types::TEXT, ['notnull' => false]);                   // encrypted token, so hosts can copy the link again
		$table->addColumn('code', Types::STRING, ['notnull' => true, 'length' => 8]);         // short code typed at the kiosk
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'invited']);
		$table->addColumn('checked_in_at', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('checked_out_at', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['token_hash'], 'kdvnv_visits_tok');
		$table->addIndex(['code'], 'kdvnv_visits_code');
		$table->addIndex(['host_uid'], 'kdvnv_visits_host');
		$table->addIndex(['start_at'], 'kdvnv_visits_start');

		return $schema;
	}
}
