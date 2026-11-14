<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Service;

/** Fixed, slowly moving values so the display can be built and tested without the supplier. */
class MockProvider implements ReadingProvider {
	public function fetch(): ?array {
		$now = time();
		$wave = sin($now / 600);
		return [
			'temperature' => round(24.5 + 2.0 * $wave, 1),
			'humidity' => round(52.0 + 6.0 * sin($now / 900), 0),
			'co2' => round(780.0 + 350.0 * sin($now / 1200)),
			'at' => $now,
		];
	}
}
