<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Service;

/** A source of indoor readings for one sensor. */
interface ReadingProvider {
	/**
	 * @param array{id: string, name: string, points: array<string, string>} $sensor "points" holds the point
	 *   name per metric (temperature, humidity, co2, fan) at the supplier source; empty for the mock provider.
	 * @return array{temperature: ?float, humidity: ?float, co2: ?float, fan: ?bool, at: int}|null
	 *   null = the source does not answer. A single value may be null (sensor offline or stale).
	 *   "fan" is the exhaust fan running signal (null = unknown or not configured).
	 *   "at" is the Unix time the values were measured.
	 */
	public function fetch(array $sensor): ?array;
}
