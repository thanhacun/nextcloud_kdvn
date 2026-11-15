<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Service;

use OCP\IAppConfig;

/** Every setting of the app (oc_appconfig, app "kdvn_sensors"). */
class AppSettings {
	private const APP = 'kdvn_sensors';
	public const METRICS = ['temperature', 'humidity', 'co2'];
	private const MAX_SENSORS = 30;

	/** Starting thresholds (typical comfort guidance; adjust with facilities). */
	public const DEFAULT_THRESHOLDS = [
		'temperature' => ['okMin' => 22.0, 'okMax' => 26.0, 'warnMin' => 20.0, 'warnMax' => 28.0],
		'humidity' => ['okMin' => 40.0, 'okMax' => 60.0, 'warnMin' => 30.0, 'warnMax' => 70.0],
		'co2' => ['okMin' => 0.0, 'okMax' => 800.0, 'warnMin' => 0.0, 'warnMax' => 1200.0],
	];

	/** After this many seconds without a fresh reading the tile shows NA. */
	public const STALE_SECONDS = 600;

	public function __construct(
		private IAppConfig $config,
	) {
	}

	/** 'off' or 'mock' (a 'supplier' provider follows once the API details are known). */
	public function provider(): string {
		$value = $this->config->getValueString(self::APP, 'provider', 'off');
		return in_array($value, ['off', 'mock'], true) ? $value : 'off';
	}

	public function setProvider(string $value): void {
		$this->config->setValueString(self::APP, 'provider', in_array($value, ['off', 'mock'], true) ? $value : 'off');
	}

	/**
	 * Default thresholds for every sensor (a sensor can override them).
	 * @return array<string, array{okMin: float, okMax: float, warnMin: float, warnMax: float}>
	 */
	public function thresholds(): array {
		$stored = json_decode($this->config->getValueString(self::APP, 'thresholds', ''), true);
		return self::mergeThresholds(self::DEFAULT_THRESHOLDS, is_array($stored) ? $stored : []);
	}

	public function setThresholds(array $in): void {
		$valid = self::cleanThresholds($in);
		$this->config->setValueString(self::APP, 'thresholds', json_encode($valid));
	}

	/**
	 * The configured sensors. With none configured one implicit sensor stands in, so the page
	 * deployed with 0.1.0 keeps working.
	 * @return list<array{id: string, name: string, buildingId: ?int, metrics: list<string>, ref: string, roomDisplay: bool, thresholds: array}>
	 */
	public function sensors(): array {
		$stored = json_decode($this->config->getValueString(self::APP, 'sensors', ''), true);
		$sensors = is_array($stored) ? self::cleanSensors($stored) : [];
		if ($sensors === []) {
			$name = trim($this->config->getValueString(self::APP, 'location', ''));
			$sensors = [[
				'id' => 'default', 'name' => $name !== '' ? $name : 'Office', 'buildingId' => null,
				'metrics' => self::METRICS, 'ref' => '', 'roomDisplay' => true, 'thresholds' => [],
			]];
		}
		return $sensors;
	}

	/** True when the admin saved a sensor list (the implicit sensor is not "configured"). */
	public function hasSensors(): bool {
		$stored = json_decode($this->config->getValueString(self::APP, 'sensors', ''), true);
		return is_array($stored) && self::cleanSensors($stored) !== [];
	}

	public function setSensors(array $in): void {
		$this->config->setValueString(self::APP, 'sensors', json_encode(self::cleanSensors($in)));
	}

	/** Thresholds of one sensor: defaults, then the global ones, then the sensor's own. */
	public function thresholdsFor(array $sensor): array {
		return self::mergeThresholds($this->thresholds(), $sensor['thresholds'] ?? []);
	}

	/** @return list<array> sanitized sensors (unknown fields dropped, ids generated, duplicates removed) */
	public static function cleanSensors(array $in): array {
		$out = [];
		$seen = [];
		foreach ($in as $row) {
			if (!is_array($row) || count($out) >= self::MAX_SENSORS) {
				continue;
			}
			$name = mb_substr(trim((string)($row['name'] ?? '')), 0, 60);
			if ($name === '') {
				continue;
			}
			$id = preg_replace('/[^a-z0-9]/', '', strtolower((string)($row['id'] ?? '')));
			if ($id === '' || isset($seen[$id])) {
				$id = bin2hex(random_bytes(4));
			}
			$seen[$id] = true;
			$metrics = array_values(array_intersect(self::METRICS, array_map('strval', (array)($row['metrics'] ?? []))));
			$building = $row['buildingId'] ?? null;
			$out[] = [
				'id' => $id,
				'name' => $name,
				'buildingId' => ($building === null || $building === '' || !is_numeric($building)) ? null : (int)$building,
				'metrics' => $metrics === [] ? self::METRICS : $metrics,
				'ref' => mb_substr(trim((string)($row['ref'] ?? '')), 0, 100),
				'roomDisplay' => !empty($row['roomDisplay']),
				'thresholds' => self::cleanThresholds((array)($row['thresholds'] ?? []), false),
			];
		}
		return $out;
	}

	/**
	 * Keep only metrics whose four numbers are present and nest (warning band wider than the good band).
	 * With $fillDefaults a bad or missing metric gets its default; without, it is left out (= "use default").
	 */
	public static function cleanThresholds(array $in, bool $fillDefaults = true): array {
		$out = [];
		foreach (self::DEFAULT_THRESHOLDS as $metric => $defaults) {
			$t = [];
			foreach (array_keys($defaults) as $field) {
				$value = $in[$metric][$field] ?? null;
				if ($value === null || $value === '' || !is_numeric($value)) {
					$t = null;
					break;
				}
				$t[$field] = (float)$value;
			}
			if ($t !== null && $t['warnMin'] <= $t['okMin'] && $t['okMin'] <= $t['okMax'] && $t['okMax'] <= $t['warnMax']) {
				$out[$metric] = $t;
			} elseif ($fillDefaults) {
				$out[$metric] = $defaults;
			}
		}
		return $out;
	}

	private static function mergeThresholds(array $base, array $override): array {
		foreach ($override as $metric => $t) {
			if (isset($base[$metric]) && is_array($t) && count($t) === 4) {
				$base[$metric] = $t;
			}
		}
		return $base;
	}

	public function displayKeyHash(): string {
		return $this->config->getValueString(self::APP, 'display_key_hash', '');
	}

	public function displayKeyCreatedAt(): int {
		return $this->config->getValueInt(self::APP, 'display_key_created_at', 0);
	}

	public function setDisplayKeyHash(string $hash): void {
		$this->config->setValueString(self::APP, 'display_key_hash', $hash, false, true);
		$this->config->setValueInt(self::APP, 'display_key_created_at', time());
	}
}
