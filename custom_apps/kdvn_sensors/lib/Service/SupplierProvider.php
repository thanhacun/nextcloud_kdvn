<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Service;

use OCP\Http\Client\IClientService;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

/**
 * Readings from the Azbil "BACnet Service" REST API (proposal of 2026-11-19, docs/plan-sensor-display.md section 7):
 *   GET <url>  with header X-API-Key  ->  {"timestamp": ..., "data": [{"point", "value", "unit", "status", "updated"}]}
 * One request returns every point; it is fetched once per PHP request and shared by all sensors.
 * All parsing is in parse(), so a change of the supplier's JSON is a change in one place.
 */
class SupplierProvider implements ReadingProvider {
	private const TIMEOUT = 5;
	private const FAILURE_PAUSE = 30;

	/** @var array<string, mixed>|false|null decoded response of this request (false = failed) */
	private $memo = null;
	private ?ICache $cache;

	public function __construct(
		private AppSettings $settings,
		private IClientService $clients,
		ICacheFactory $cacheFactory,
		private LoggerInterface $logger,
	) {
		$this->cache = $cacheFactory->createDistributed('kdvn_sensors');
	}

	public function fetch(array $sensor): ?array {
		$json = $this->load();
		if ($json === null) {
			return null;
		}
		return self::parse($json, $sensor['points'] ?? [], time(), $this->settings->supplierStaleSeconds());
	}

	/**
	 * Pure function (easy to test).
	 * A value is null when its point is missing, not "online", not numeric, or its "updated" time is older than
	 * $staleSeconds (a missing "updated" falls back to the response "timestamp").
	 * @param array<string, string> $points metric (temperature, humidity, co2, fan) => point name
	 * @return array{temperature: ?float, humidity: ?float, co2: ?float, fan: ?bool, at: int}
	 */
	public static function parse(array $json, array $points, int $now, int $staleSeconds): array {
		$byName = [];
		foreach ((array)($json['data'] ?? []) as $entry) {
			if (is_array($entry) && isset($entry['point']) && is_string($entry['point'])) {
				$byName[$entry['point']] = $entry;
			}
		}
		$responseTime = self::time($json['timestamp'] ?? null);
		$out = ['temperature' => null, 'humidity' => null, 'co2' => null, 'fan' => null, 'at' => $now];
		$newest = 0;
		foreach (AppSettings::POINT_KEYS as $key) {
			$entry = isset($points[$key]) ? ($byName[$points[$key]] ?? null) : null;
			if ($entry === null || strtolower((string)($entry['status'] ?? '')) !== 'online') {
				continue;
			}
			$at = self::time($entry['updated'] ?? null) ?? $responseTime;
			if ($at === null || ($now - $at) > $staleSeconds) {
				continue;
			}
			$value = $entry['value'] ?? null;
			if ($key === 'fan') {
				$out['fan'] = self::bool($value);
			} elseif (is_numeric($value)) {
				$out[$key] = (float)$value;
			} else {
				continue;
			}
			$newest = max($newest, $at);
		}
		if ($newest > 0) {
			$out['at'] = $newest;
		}
		return $out;
	}

	private static function time(mixed $value): ?int {
		if (!is_string($value) || $value === '') {
			return null;
		}
		$time = strtotime($value);
		return $time === false ? null : $time;
	}

	/** Fan signal: a number (0 = stopped) or a word (on, running, true). Anything else is unknown. */
	private static function bool(mixed $value): ?bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_numeric($value)) {
			return (float)$value > 0;
		}
		if (is_string($value)) {
			$word = strtolower(trim($value));
			if (in_array($word, ['on', 'run', 'running', 'true', 'active'], true)) {
				return true;
			}
			if (in_array($word, ['off', 'stop', 'stopped', 'false', 'inactive'], true)) {
				return false;
			}
		}
		return null;
	}

	/** @return array<string, mixed>|null null when the source is not set up, down, or answers badly */
	private function load(): ?array {
		if ($this->memo !== null) {
			return $this->memo === false ? null : $this->memo;
		}
		$this->memo = false;
		$url = $this->settings->supplierUrl();
		$key = $this->settings->supplierKey();
		if ($url === '' || $key === '') {
			return null;
		}
		if ($this->cache?->get('supplier_failed') !== null) {
			return null; // do not wait for a timeout on every display refresh while the source is down
		}
		try {
			$response = $this->clients->newClient()->get($url, [
				'headers' => ['X-API-Key' => $key, 'Accept' => 'application/json'],
				'timeout' => self::TIMEOUT,
				'connect_timeout' => 3,
			]);
			$json = json_decode((string)$response->getBody(), true);
			if (!is_array($json)) {
				throw new \RuntimeException('the answer is not JSON');
			}
		} catch (\Throwable $e) {
			// Never log the key. The status code tells a wrong key (401) from a network problem.
			$code = method_exists($e, 'getCode') ? (int)$e->getCode() : 0;
			$this->logger->warning('kdvn_sensors supplier: ' . get_class($e) . ' (code ' . $code . ') ' . ($code === 401 ? 'the API key was rejected' : $e->getMessage()));
			$this->cache?->set('supplier_failed', 1, self::FAILURE_PAUSE);
			return null;
		}
		$this->memo = $json;
		return $json;
	}
}
