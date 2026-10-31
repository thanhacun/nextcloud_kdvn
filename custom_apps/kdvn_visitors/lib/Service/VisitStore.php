<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** All SQL for the kdvnv_visits table. Rows are returned as plain arrays with typed values. */
class VisitStore {
	private const TABLE = 'kdvnv_visits';

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function insert(array $values): int {
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE);
		foreach ($values as $column => $value) {
			$qb->setValue($column, $qb->createNamedParameter($value, $this->type($value)));
		}
		$qb->executeStatement();
		return $qb->getLastInsertId();
	}

	public function update(int $id, array $values): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE);
		foreach ($values as $column => $value) {
			$qb->set($column, $qb->createNamedParameter($value, $this->type($value)));
		}
		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function find(int $id): ?array {
		return $this->findOneBy('id', $id);
	}

	public function findByTokenHash(string $hash): ?array {
		return $this->findOneBy('token_hash', $hash);
	}

	/** Active visits using this code, newest first (codes are only unique among active visits). */
	public function findActiveByCode(string $code): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('code', $qb->createNamedParameter($code)))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['invited', 'checked_in'], IQueryBuilder::PARAM_STR_ARRAY)))
			->orderBy('start_at', 'DESC')
			->setMaxResults(1);
		$rows = $this->fetch($qb);
		return $rows[0] ?? null;
	}

	public function codeInUse(string $code): bool {
		return $this->findActiveByCode($code) !== null;
	}

	/** A host's visits starting after $since, oldest first. */
	public function listForHost(string $hostUid, int $since): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('host_uid', $qb->createNamedParameter($hostUid)))
			->andWhere($qb->expr()->gte('start_at', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)))
			->orderBy('start_at', 'ASC')
			->setMaxResults(500);
		return $this->fetch($qb);
	}

	/** Visits linked to a calendar event, for one host. */
	public function listByEvent(string $eventUid, string $hostUid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('event_uid', $qb->createNamedParameter($eventUid)))
			->andWhere($qb->expr()->eq('host_uid', $qb->createNamedParameter($hostUid)));
		return $this->fetch($qb);
	}

	/** A visit the host created by hand for the same guest around the same time (to link instead of duplicating). */
	public function findManualMatch(string $hostUid, string $email, int $start): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('host_uid', $qb->createNamedParameter($hostUid)))
			->andWhere($qb->expr()->eq($qb->func()->lower('guest_email'), $qb->createNamedParameter(strtolower($email))))
			->andWhere($qb->expr()->isNull('event_uid'))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['invited', 'checked_in'], IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($qb->expr()->gte('start_at', $qb->createNamedParameter($start - 2 * 3600, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lte('start_at', $qb->createNamedParameter($start + 2 * 3600, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		return $this->fetch($qb)[0] ?? null;
	}

	/** Visits that may appear on the room display (not cancelled or declined). */
	public function listForDisplay(int $from, int $to): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->gte('start_at', $qb->createNamedParameter($from - 86400, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lt('start_at', $qb->createNamedParameter($to, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->notIn('status', $qb->createNamedParameter(['cancelled', 'declined'], IQueryBuilder::PARAM_STR_ARRAY)))
			->setMaxResults(5000);
		return $this->fetch($qb);
	}

	/** Reception board: visits starting in [from, to) plus everyone still waiting or in the building. */
	public function listForReception(int $from, int $to): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->andX(
				$qb->expr()->gte('start_at', $qb->createNamedParameter($from, IQueryBuilder::PARAM_INT)),
				$qb->expr()->lt('start_at', $qb->createNamedParameter($to, IQueryBuilder::PARAM_INT)),
			))
			->orWhere($qb->expr()->in('status', $qb->createNamedParameter(['waiting', 'checked_in'], IQueryBuilder::PARAM_STR_ARRAY)))
			->orderBy('start_at', 'ASC')
			->setMaxResults(1000);
		return $this->fetch($qb);
	}

	/** @return array[] visits with this status whose $column is before $before */
	public function listStale(string $status, string $column, int $before): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('status', $qb->createNamedParameter($status)))
			->andWhere($qb->expr()->lt($column, $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1000);
		return $this->fetch($qb);
	}

	/** Deletes visits that started before $before. Returns how many. */
	public function deleteStartedBefore(int $before): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::TABLE)
			->where($qb->expr()->lt('start_at', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	private function findOneBy(string $column, int|string $value): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value, $this->type($value))))
			->setMaxResults(1);
		$rows = $this->fetch($qb);
		return $rows[0] ?? null;
	}

	private function fetch(IQueryBuilder $qb): array {
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return array_map([$this, 'normalize'], $rows);
	}

	/** Databases return numbers as strings; make them ints again. */
	private function normalize(array $row): array {
		foreach (['id', 'start_at', 'created_at'] as $column) {
			$row[$column] = (int)$row[$column];
		}
		foreach (['end_at', 'checked_in_at', 'checked_out_at', 'consent_at', 'building_id'] as $column) {
			$row[$column] = ($row[$column] ?? null) === null ? null : (int)$row[$column];
		}
		$row['kind'] ??= 'invited';
		$row['source'] ??= 'manual';
		return $row;
	}

	private function type(mixed $value): int|string {
		return match (true) {
			$value === null => IQueryBuilder::PARAM_NULL,
			is_int($value) => IQueryBuilder::PARAM_INT,
			default => IQueryBuilder::PARAM_STR,
		};
	}
}
