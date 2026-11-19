<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Service;

/** Fixed, slowly moving values (different per sensor) so the display can be tested without the supplier. */
class MockProvider implements ReadingProvider {
	public function fetch(array $sensor): ?array {
		$now = time();
		$seed = crc32($sensor['id']);
		$phase = ($seed % 628) / 100;
		$co2 = round(700.0 + ($seed % 150) + 350.0 * sin($now / 1200 + $phase));
		return [
			'temperature' => round(22.0 + ($seed % 5) + 2.0 * sin($now / 600 + $phase), 1),
			'humidity' => round(50.0 + ($seed % 7) + 6.0 * sin($now / 900 + $phase), 0),
			'co2' => $co2,
			'fan' => $co2 > 600.0,
			'at' => $now,
		];
	}
}
