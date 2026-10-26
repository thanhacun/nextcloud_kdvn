<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\Defaults;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\IURLGenerator;

/**
 * The logo shown on the tablet and the visitor pass.
 * An uploaded logo (Administration settings > Visitors) wins; otherwise the Nextcloud Theming logo.
 * Stored in Nextcloud's app data folder, not in the app folder, so app updates don't remove it.
 */
class Branding {
	private const APP = 'kdvn_visitors';
	private const FOLDER = 'branding';
	private const FILE = 'logo';
	public const MAX_BYTES = 2 * 1024 * 1024;
	/** No SVG: an SVG can contain scripts, and this file is served from our own domain. */
	public const ALLOWED = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_WEBP => 'image/webp'];

	private IAppData $appData;

	public function __construct(
		IAppDataFactory $appDataFactory,
		private IAppConfig $config,
		private IURLGenerator $urlGenerator,
		private Defaults $theming,
	) {
		$this->appData = $appDataFactory->get(self::APP);
	}

	public function hasOwnLogo(): bool {
		return $this->config->getValueString(self::APP, 'logo_mime', '') !== '';
	}

	/** URL for <img src>, or '' when the logo is switched off. */
	public function logoUrl(bool $showLogo): string {
		if (!$showLogo) {
			return '';
		}
		if ($this->hasOwnLogo()) {
			// ?v= changes with every upload, so browsers never show a stale cached logo.
			return $this->urlGenerator->linkToRoute('kdvn_visitors.logo.show')
				. '?v=' . $this->config->getValueInt(self::APP, 'logo_version', 0);
		}
		return $this->theming->getLogo();
	}

	/** @return array{0: string, 1: string} [content, mime type] */
	public function ownLogo(): array {
		$mime = $this->config->getValueString(self::APP, 'logo_mime', '');
		if ($mime === '') {
			throw new NotFoundException();
		}
		return [$this->appData->getFolder(self::FOLDER)->getFile(self::FILE)->getContent(), $mime];
	}

	/** Validates and stores an uploaded image. Throws UserError with a readable message. */
	public function saveUpload(?array $upload): void {
		if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) {
			throw new UserError('No file was uploaded, or the upload failed.');
		}
		if (($upload['size'] ?? 0) > self::MAX_BYTES) {
			throw new UserError('The logo must be smaller than 2 MB.');
		}
		$info = @getimagesize($upload['tmp_name']); // reads the real image type, not the file name
		if ($info === false || !isset(self::ALLOWED[$info[2]])) {
			throw new UserError('Use a PNG, JPEG or WebP image. PNG with a transparent background looks best.');
		}
		$content = (string)file_get_contents($upload['tmp_name']);

		try {
			$folder = $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException) {
			$folder = $this->appData->newFolder(self::FOLDER);
		}
		if ($folder->fileExists(self::FILE)) {
			$folder->getFile(self::FILE)->putContent($content);
		} else {
			$folder->newFile(self::FILE, $content);
		}
		$this->config->setValueString(self::APP, 'logo_mime', self::ALLOWED[$info[2]]);
		$this->config->setValueInt(self::APP, 'logo_version', time());
	}

	public function removeOwnLogo(): void {
		try {
			$this->appData->getFolder(self::FOLDER)->getFile(self::FILE)->delete();
		} catch (NotFoundException) {
			// already gone
		}
		$this->config->setValueString(self::APP, 'logo_mime', '');
	}
}
