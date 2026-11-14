<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Service;

use OCP\IAppConfig;

/** Every setting of the app (oc_appconfig, app "kdvn_sensors"). */
class AppSettings {
	private const APP = 'kdvn_sensors';

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

	public function location(): string {
		return $this->config->getValueString(self::APP, 'location', '');
	}

	public function setLocation(string $value): void {
		$this->config->setValueString(self::APP, 'location', mb_substr(trim($value), 0, 100));
	}

	/** @return array<string, array{okMin: float, okMax: float, warnMin: float, warnMax: float}> */
	public function thresholds(): array {
		$stored = json_decode($this->config->getValueString(self::APP, 'thresholds', ''), true);
		$result = self::DEFAULT_THRESHOLDS;
		if (is_array($stored)) {
			foreach ($result as $metric => $defaults) {
				foreach (array_keys($defaults) as $field) {
					if (isset($stored[$metric][$field]) && is_numeric($stored[$metric][$field])) {
						$result[$metric][$field] = (float)$stored[$metric][$field];
					}
				}
			}
		}
		return $result;
	}

	public function setThresholds(array $in): void {
		$clean = [];
		foreach (self::DEFAULT_THRESHOLDS as $metric => $defaults) {
			foreach (array_keys($defaults) as $field) {
				$clean[$metric][$field] = isset($in[$metric][$field]) && is_numeric($in[$metric][$field])
					? (float)$in[$metric][$field] : $defaults[$field];
			}
			$t = $clean[$metric];
			// The warning band must contain the good band.
			if ($t['warnMin'] > $t['okMin'] || $t['okMin'] > $t['okMax'] || $t['okMax'] > $t['warnMax']) {
				$clean[$metric] = $defaults;
			}
		}
		$this->config->setValueString(self::APP, 'thresholds', json_encode($clean));
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
