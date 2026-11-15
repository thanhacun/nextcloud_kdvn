<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Service;

use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

/** Reads the provider per sensor (60 s cache), turns values into tiles with a status word. */
class SensorService {
	private const CACHE_SECONDS = 60;

	private const UNITS = ['temperature' => '°C', 'humidity' => '%', 'co2' => 'ppm'];
	private const DECIMALS = ['temperature' => 1, 'humidity' => 0, 'co2' => 0];
	// Status words from the mockup: Comfortable / Normal / Good, Warning, Bad.
	private const GOOD_WORD = ['temperature' => 'Comfortable', 'humidity' => 'Normal', 'co2' => 'Good'];

	private ?ICache $cache;

	public function __construct(
		private AppSettings $settings,
		private MockProvider $mock,
		private BuildingCatalog $buildings,
		ICacheFactory $cacheFactory,
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

	/**
	 * @param ?int $buildingId only the sensors of this building (null = every sensor)
	 * @param bool $roomDisplayOnly only sensors ticked "show on the room display"
	 * @return array{sensors: list<array>, generatedAt: int}
	 */
	public function readings(?int $buildingId = null, bool $roomDisplayOnly = false): array {
		$names = $this->buildings->names();
		$out = [];
		foreach ($this->settings->sensors() as $sensor) {
			if ($buildingId !== null && $sensor['buildingId'] !== $buildingId) {
				continue;
			}
			if ($roomDisplayOnly && !$sensor['roomDisplay']) {
				continue;
			}
			$raw = $this->raw($sensor);
			$stale = $raw === null || (time() - $raw['at']) > AppSettings::STALE_SECONDS;
			$thresholds = $this->settings->thresholdsFor($sensor);
			$tiles = [];
			foreach ($sensor['metrics'] as $metric) {
				$value = (!$stale && isset($raw[$metric])) ? (float)$raw[$metric] : null;
				$tile = ['metric' => $metric, 'unit' => self::UNITS[$metric], 'value' => null, 'level' => 'na', 'word' => 'NA'];
				if ($value !== null) {
					$level = self::level($value, $thresholds[$metric]);
					$tile['value'] = round($value, self::DECIMALS[$metric]);
					$tile['level'] = $level;
					$tile['word'] = $level === 'good' ? self::GOOD_WORD[$metric] : ($level === 'warning' ? 'Warning' : 'Bad');
				}
				$tiles[] = $tile;
			}
			$out[] = [
				'id' => $sensor['id'],
				'name' => $sensor['name'],
				'buildingId' => $sensor['buildingId'],
				'building' => $sensor['buildingId'] !== null ? ($names[$sensor['buildingId']] ?? '') : '',
				'source' => $raw === null ? 'none' : 'Indoor sensor',
				'measuredAt' => $raw['at'] ?? null,
				'tiles' => $tiles,
			];
		}
		return ['sensors' => $out, 'generatedAt' => time()];
	}

	private function raw(array $sensor): ?array {
		$provider = $this->settings->provider();
		if ($provider === 'off') {
			return null;
		}
		$cacheKey = 'raw_' . $provider . '_' . $sensor['id'] . '_' . md5($sensor['ref']);
		$cached = $this->cache?->get($cacheKey);
		if (is_array($cached)) {
			return $cached;
		}
		try {
			$raw = $this->mock->fetch($sensor); // only 'mock' exists so far
		} catch (\Throwable $e) {
			$this->logger->error('kdvn_sensors provider: ' . $e->getMessage(), ['exception' => $e]);
			return null;
		}
		if ($raw !== null) {
			$this->cache?->set($cacheKey, $raw, self::CACHE_SECONDS);
		}
		return $raw;
	}
}
