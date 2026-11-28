<?php

declare(strict_types=1);

namespace OCA\KdvnDirectory\Service;

use OCA\KdvnDirectory\AppInfo\Application;
use OCP\IAppConfig;

/** Settings in oc_appconfig: which groups are listed, and which groups are denied the directory. */
class DirectorySettings {
	public function __construct(
		private IAppConfig $config,
	) {
	}

	/** @return list<array{id: string, label: string}> in the order the admin saved them */
	public function listedGroups(): array {
		$raw = json_decode($this->config->getValueString(Application::APP_ID, 'listed_groups', '[]'), true);
		$out = [];
		foreach (is_array($raw) ? $raw : [] as $row) {
			if (is_array($row) && is_string($row['id'] ?? null) && $row['id'] !== '') {
				$out[] = ['id' => $row['id'], 'label' => is_string($row['label'] ?? null) ? $row['label'] : ''];
			}
		}
		return $out;
	}

	/** @param list<array{id: string, label: string}> $groups */
	public function setListedGroups(array $groups): void {
		$seen = [];
		$clean = [];
		foreach ($groups as $row) {
			$id = is_string($row['id'] ?? null) ? $row['id'] : '';
			if ($id === '' || isset($seen[$id])) {
				continue;
			}
			$seen[$id] = true;
			$clean[] = ['id' => $id, 'label' => mb_substr(trim((string)($row['label'] ?? '')), 0, 80)];
		}
		$this->config->setValueString(Application::APP_ID, 'listed_groups', json_encode($clean, JSON_UNESCAPED_UNICODE));
	}

	/** Members of these groups cannot open the directory and are never listed in it. @return string[] */
	public function deniedGroups(): array {
		$raw = json_decode($this->config->getValueString(Application::APP_ID, 'denied_groups', '[]'), true);
		return is_array($raw) ? array_values(array_filter($raw, 'is_string')) : [];
	}

	public function setDeniedGroups(array $gids): void {
		$this->config->setValueString(Application::APP_ID, 'denied_groups', json_encode(array_values(array_unique(array_filter($gids, 'is_string')))));
	}
}
