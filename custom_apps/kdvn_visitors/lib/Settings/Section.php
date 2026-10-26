<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Settings;

use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Section implements IIconSection {
	public function __construct(
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return 'kdvn_visitors';
	}

	public function getName(): string {
		return 'Visitors';
	}

	public function getPriority(): int {
		return 76;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('kdvn_visitors', 'app-dark.svg');
	}
}
