<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\IAppConfig;
use OCP\Security\ICrypto;

/** One place for every setting the app stores (oc_appconfig, app "kdvn_visitors"). */
class AppSettings {
	private const APP = 'kdvn_visitors';

	public function __construct(
		private IAppConfig $config,
		private ICrypto $crypto,
	) {
	}

	public function companyName(): string {
		return $this->config->getValueString(self::APP, 'company_name', 'Kinden Vietnam');
	}

	public function officeAddress(): string {
		return $this->config->getValueString(self::APP, 'office_address', '');
	}

	public function talkUser(): string {
		return $this->config->getValueString(self::APP, 'talk_user', '');
	}

	public function talkPassword(): string {
		$encrypted = $this->config->getValueString(self::APP, 'talk_password', '');
		if ($encrypted === '') {
			return '';
		}
		try {
			return $this->crypto->decrypt($encrypted);
		} catch (\Throwable) {
			return '';
		}
	}

	public function hasTalkPassword(): bool {
		return $this->config->getValueString(self::APP, 'talk_password', '') !== '';
	}

	/** Optional override, e.g. "http://localhost" if calling the public URL from inside the container fails. */
	public function talkBaseUrl(): string {
		return rtrim($this->config->getValueString(self::APP, 'talk_base_url', ''), '/');
	}

	/** Group whose members act as reception. Empty = admins only. */
	public function receptionGroup(): string {
		return $this->config->getValueString(self::APP, 'reception_group', '');
	}

	public function retentionDays(): int {
		return max(7, $this->config->getValueInt(self::APP, 'retention_days', 90));
	}

	public function officeTimezone(): \DateTimeZone {
		try {
			return new \DateTimeZone($this->config->getValueString(self::APP, 'office_timezone', 'Asia/Ho_Chi_Minh'));
		} catch (\Throwable) {
			return new \DateTimeZone('Asia/Ho_Chi_Minh');
		}
	}

	public function officeTimezoneName(): string {
		return $this->officeTimezone()->getName();
	}

	/** Token of the "Reception desk" Talk conversation, created on first use. */
	public function receptionRoomToken(): string {
		return $this->config->getValueString(self::APP, 'reception_room_token', '');
	}

	public function setReceptionGroup(string $gid): void {
		if ($gid !== $this->receptionGroup()) {
			$this->setReceptionRoomToken(''); // a new group needs a new conversation
		}
		$this->config->setValueString(self::APP, 'reception_group', $gid);
	}

	public function setRetentionDays(int $days): void {
		$this->config->setValueInt(self::APP, 'retention_days', max(7, min(3650, $days)));
	}

	public function setOfficeTimezone(string $name): void {
		new \DateTimeZone($name); // throws if invalid
		$this->config->setValueString(self::APP, 'office_timezone', $name);
	}

	public function setReceptionRoomToken(string $token): void {
		$this->config->setValueString(self::APP, 'reception_room_token', $token);
	}

	// ---------------------------------------------- calendar sync and display

	/** off | room (a meeting room is booked) | all (any outside guest) */
	public function autoPassMode(): string {
		$mode = $this->config->getValueString(self::APP, 'auto_pass_mode', 'room');
		return in_array($mode, ['off', 'room', 'all'], true) ? $mode : 'room';
	}

	/** What the public room display shows about guests: company | names | count */
	public function displayGuestMode(): string {
		$mode = $this->config->getValueString(self::APP, 'display_guest_mode', 'company');
		return in_array($mode, ['company', 'names', 'count'], true) ? $mode : 'company';
	}

	/** What the display shows as the meeting title: title (private events hidden) | booked */
	public function displayTitleMode(): string {
		$mode = $this->config->getValueString(self::APP, 'display_title_mode', 'title');
		return in_array($mode, ['title', 'booked'], true) ? $mode : 'title';
	}

	public function setCalendarSettings(string $autoPass, string $guestMode, string $titleMode): void {
		if (!in_array($autoPass, ['off', 'room', 'all'], true) || !in_array($guestMode, ['company', 'names', 'count'], true)
			|| !in_array($titleMode, ['title', 'booked'], true)) {
			throw new UserError('Unknown option.');
		}
		$this->config->setValueString(self::APP, 'auto_pass_mode', $autoPass);
		$this->config->setValueString(self::APP, 'display_guest_mode', $guestMode);
		$this->config->setValueString(self::APP, 'display_title_mode', $titleMode);
	}

	/**
	 * Working days per building (ISO weekday numbers: 1 = Monday ... 7 = Sunday).
	 * Buildings without a setting use Monday–Friday.
	 * @return array<int, int[]>
	 */
	public function buildingWorkdays(): array {
		$raw = json_decode($this->config->getValueString(self::APP, 'building_workdays', '{}'), true);
		$out = [];
		foreach (is_array($raw) ? $raw : [] as $buildingId => $days) {
			$days = array_values(array_unique(array_filter(array_map('intval', (array)$days), fn ($d) => $d >= 1 && $d <= 7)));
			sort($days);
			if ($days !== []) {
				$out[(int)$buildingId] = $days;
			}
		}
		return $out;
	}

	public function workdaysFor(?int $buildingId): array {
		return $this->buildingWorkdays()[$buildingId ?? -1] ?? [1, 2, 3, 4, 5];
	}

	public function setBuildingWorkdays(array $in): void {
		$clean = [];
		foreach ($in as $buildingId => $days) {
			$days = array_values(array_unique(array_filter(array_map('intval', (array)$days), fn ($d) => $d >= 1 && $d <= 7)));
			if ($days === []) {
				throw new UserError('Every building needs at least one working day.');
			}
			sort($days);
			$clean[(int)$buildingId] = $days;
		}
		$this->config->setValueString(self::APP, 'building_workdays', json_encode($clean, JSON_FORCE_OBJECT));
	}

	/**
	 * Minutes after a visit's end time before a guest still "in the building" is checked out
	 * automatically. 0 = only overnight. Walk-ins and visits without an end time count as 2 hours long.
	 */
	public function autoCheckoutMinutes(): int {
		$minutes = $this->config->getValueInt(self::APP, 'auto_checkout_minutes', 60);
		return in_array($minutes, [0, 30, 60, 120, 240], true) ? $minutes : 60;
	}

	public function setAutoCheckoutMinutes(int $minutes): void {
		if (!in_array($minutes, [0, 30, 60, 120, 240], true)) {
			throw new UserError('Unknown check-out option.');
		}
		$this->config->setValueInt(self::APP, 'auto_checkout_minutes', $minutes);
	}

	/**
	 * Buildings (offices) that have a visitor reception. Visitor passes are only created for rooms
	 * in these buildings. null = not configured yet = every building (the behaviour before 0.4.5).
	 * @return int[]|null
	 */
	public function receptionBuildings(): ?array {
		$raw = $this->config->getValueString(self::APP, 'reception_buildings', '');
		if ($raw === '') {
			return null;
		}
		$ids = json_decode($raw, true);
		return is_array($ids) ? array_values(array_map('intval', $ids)) : null;
	}

	/** Rooms outside any building (no floor/building set) only count while nothing is configured. */
	public function hasReception(?int $buildingId): bool {
		$ids = $this->receptionBuildings();
		return $ids === null || ($buildingId !== null && in_array($buildingId, $ids, true));
	}

	public function setReceptionBuildings(array $ids): void {
		$this->config->setValueString(self::APP, 'reception_buildings', json_encode(array_values(array_unique(array_map('intval', $ids)))));
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

	// ------------------------------------------------ tablet / pass appearance

	public const DEFAULT_ACCENT = '#017101'; // Kinden green, from the logo

	/**
	 * Simple look-and-feel settings for the tablet and the guest pass.
	 * Empty texts mean "use the built-in text".
	 */
	public function appearance(): array {
		$get = fn (string $key, string $default = '') => $this->config->getValueString(self::APP, $key, $default);
		$texts = [];
		foreach (Texts::LANGUAGES as $lang) {
			$texts[$lang] = ['title' => $get('kiosk_title_' . $lang), 'message' => $get('kiosk_message_' . $lang)];
		}
		$accent = $get('accent_color', self::DEFAULT_ACCENT);
		return [
			'accent' => $accent,
			'accentText' => self::readableTextOn($accent),
			'showLogo' => $get('show_logo', '1') === '1',
			'walkIn' => $get('kiosk_walk_in', '1') === '1',
			'checkOut' => $get('kiosk_check_out', '1') === '1',
			'texts' => $texts,
		];
	}

	/** White text on dark colors, dark text on light colors (WCAG relative luminance). */
	public static function readableTextOn(string $hex): string {
		if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
			return '#ffffff';
		}
		$channel = function (string $h): float {
			$c = hexdec($h) / 255;
			return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
		};
		$luminance = 0.2126 * $channel($m[1]) + 0.7152 * $channel($m[2]) + 0.0722 * $channel($m[3]);
		// Contrast with white = 1.05 / (L + 0.05); pick white while it reaches 4.5:1.
		return (1.05 / ($luminance + 0.05)) >= 4.5 ? '#ffffff' : '#141614';
	}

	public function setAppearance(array $in): void {
		$accent = (string)($in['accent'] ?? self::DEFAULT_ACCENT);
		if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
			throw new UserError('The accent color must look like #017101.');
		}
		$set = fn (string $key, string $value) => $this->config->setValueString(self::APP, $key, $value);
		$set('accent_color', strtolower($accent));
		$set('show_logo', !empty($in['showLogo']) ? '1' : '0');
		$set('kiosk_walk_in', !empty($in['walkIn']) ? '1' : '0');
		$set('kiosk_check_out', !empty($in['checkOut']) ? '1' : '0');
		foreach (Texts::LANGUAGES as $lang) {
			$text = $in['texts'][$lang] ?? [];
			$set('kiosk_title_' . $lang, mb_substr(trim((string)($text['title'] ?? '')), 0, 120));
			$set('kiosk_message_' . $lang, mb_substr(trim((string)($text['message'] ?? '')), 0, 300));
		}
	}

	// ------------------------------------------------ purpose of visit, host privacy

	public const DEFAULT_PURPOSES = "Meeting | Họp | 会議\nSales | Bán hàng | 営業\nInterview | Phỏng vấn | 面接\nDelivery | Giao hàng | 配達";

	/** The list exactly as typed in the settings: one purpose per line, "English | Tiếng Việt | 日本語". */
	public function purposesText(): string {
		return $this->config->getValueString(self::APP, 'purpose_options', self::DEFAULT_PURPOSES);
	}

	/**
	 * Purposes for the drop-down. The English label is what gets stored with the visit, so reception
	 * reads one language whatever language the guest used.
	 * @return list<array{value: string, labels: array<string, string>}>
	 */
	public function purposes(): array {
		$out = [];
		foreach (preg_split('/\R/u', $this->purposesText()) ?: [] as $line) {
			$parts = array_map('trim', explode('|', $line));
			if ($parts[0] === '') {
				continue;
			}
			$out[] = ['value' => $parts[0], 'labels' => [
				'en' => $parts[0],
				'vi' => ($parts[1] ?? '') !== '' ? $parts[1] : $parts[0],
				'ja' => ($parts[2] ?? '') !== '' ? $parts[2] : $parts[0],
			]];
		}
		return $out;
	}

	public function setPurposesText(string $text): void {
		$lines = array_filter(array_map(fn ($l) => mb_substr(trim($l), 0, 120), preg_split('/\R/u', $text) ?: []), fn ($l) => $l !== '' && !str_starts_with($l, '|'));
		if (count($lines) > 20) {
			throw new UserError('Use at most 20 purposes of visit.');
		}
		$this->config->setValueString(self::APP, 'purpose_options', implode("\n", $lines));
	}

	/** full = show staff names on the tablet; masked = "N*** V*** A***" (guest still picks by recognising it). */
	public function hostNamePrivacy(): string {
		return $this->config->getValueString(self::APP, 'host_name_privacy', 'full') === 'masked' ? 'masked' : 'full';
	}

	public function setHostNamePrivacy(string $mode): void {
		if (!in_array($mode, ['full', 'masked'], true)) {
			throw new UserError('Unknown privacy option.');
		}
		$this->config->setValueString(self::APP, 'host_name_privacy', $mode);
	}

	public function kioskKeyHash(): string {
		return $this->config->getValueString(self::APP, 'kiosk_key_hash', '');
	}

	public function kioskKeyCreatedAt(): int {
		return $this->config->getValueInt(self::APP, 'kiosk_key_created_at', 0);
	}

	public function setCompanyName(string $value): void {
		$this->config->setValueString(self::APP, 'company_name', $value);
	}

	public function setOfficeAddress(string $value): void {
		$this->config->setValueString(self::APP, 'office_address', $value);
	}

	public function setTalkUser(string $value): void {
		$this->config->setValueString(self::APP, 'talk_user', $value);
	}

	public function setTalkPassword(string $plain): void {
		// Encrypted with the instance secret, and marked sensitive so it is hidden in occ/config listings.
		$this->config->setValueString(self::APP, 'talk_password', $this->crypto->encrypt($plain), false, true);
	}

	public function setKioskKeyHash(string $hash): void {
		$this->config->setValueString(self::APP, 'kiosk_key_hash', $hash, false, true);
		$this->config->setValueInt(self::APP, 'kiosk_key_created_at', time());
	}
}
