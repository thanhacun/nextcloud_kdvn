<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Service;

use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

/** Reads the provider (60 s cache), turns values into tiles with a status word. */
class SensorService {
	private const CACHE_SECONDS = 60;

	private const UNITS = ['temperature' => '°C', 'humidity' => '%', 'co2' => 'ppm'];
	private const DECIMALS = ['temperature' => 1, 'humidity' => 0, 'co2' => 0];
	// Status words from the mockup: Comfortable / Normal / Good, Warning, Bad.
	private const GOOD_WORD = ['temperature' => 'Comfortable', 'humidity' => 'Normal', 'co2' => 'Good'];

	private ?ICache $cache = null;

	public function __construct(
		private AppSettings $settings,
		private MockProvider $mock,
		private ICacheFactory $cacheFactory,
		private LoggerInterface $logger,
	) {
		$this->cache = $cacheFactory->createDistributed('kdvn_sensors');
	}

	/** Pure function (easy to test): 'good', 'warning' or 'bad'. */
	public static function level(float $value, array $t): string {
		if ($value >= $t['okMin'] && $value <= $t['okMax']) {
			return 'good';
		}
		if ($value >= $t['warnMin'] && $value <= $t['warnMax']) {
			return 'warning';
		}
		return 'bad';
	}

	public function readings(): array {
		$raw = $this->raw();
		$stale = $raw === null || (time() - $raw['at']) > AppSettings::STALE_SECONDS;
		$thresholds = $this->settings->thresholds();
		$tiles = [];
		foreach (self::UNITS as $metric => $unit) {
			$value = (!$stale && isset($raw[$metric])) ? (float)$raw[$metric] : null;
			$tile = ['metric' => $metric, 'unit' => $unit, 'value' => null, 'level' => 'na', 'word' => 'NA'];
			if ($value !== null) {
				$level = self::level($value, $thresholds[$metric]);
				$tile['value'] = round($value, self::DECIMALS[$metric]);
				$tile['level'] = $level;
				$tile['word'] = $level === 'good' ? self::GOOD_WORD[$metric] : ($level === 'warning' ? 'Warning' : 'Bad');
			}
			$tiles[] = $tile;
		}
		return [
			'location' => $this->settings->location(),
			'source' => $raw === null ? 'none' : 'Indoor sensor',
			'measuredAt' => $raw['at'] ?? null,
			'tiles' => $tiles,
		];
	}

	private function raw(): ?array {
		$provider = $this->settings->provider();
		if ($provider === 'off') {
			return null;
		}
		$cached = $this->cache?->get('raw_' . $provider);
		if (is_array($cached)) {
			return $cached;
		}
		try {
			$raw = $this->mock->fetch(); // only 'mock' exists so far
		} catch (\Throwable $e) {
			$this->logger->error('kdvn_sensors provider: ' . $e->getMessage(), ['exception' => $e]);
			return null;
		}
		if ($raw !== null) {
			$this->cache?->set('raw_' . $provider, $raw, self::CACHE_SECONDS);
		}
		return $raw;
	}
}
