<?php

declare(strict_types=1);

namespace OCA\KdvnSensors\Settings;

use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Section implements IIconSection {
	public function __construct(
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return 'kdvn_sensors';
	}

	public function getName(): string {
		return 'Office sensors';
	}

	public function getPriority(): int {
		return 77;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('kdvn_sensors', 'app-dark.svg');
	}
}
