<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Service;

/** A source of indoor readings. */
interface ReadingProvider {
	/**
	 * @return array{temperature: ?float, humidity: ?float, co2: ?float, at: int}|null
	 *   null = the source does not answer. A single value may be null (sensor offline).
	 *   "at" is the Unix time the values were measured.
	 */
	public function fetch(): ?array;
}
