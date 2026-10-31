<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IGroupManager;
use OCP\IUserManager;

/**
 * Staff search for "Who are you visiting?" (tablet) and "invite on behalf of" (reception).
 *
 * Names in the user database mix Vietnamese and unaccented spellings, so the search compares
 * accent-free, lower-case text: "nguyen van a" finds "Nguyễn Văn A" and the other way round.
 * The whole list (about 500 names) is searched in PHP and kept in the cache for two minutes.
 */
class StaffDirectory {
	private const CACHE_KEY = 'staff_v2_';
	private const CACHE_SECONDS = 120;

	/** Lower-case Vietnamese letters with their plain form (the fallback when PHP has no intl). */
	private const ACCENTS = [
		'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
		'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
		'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
		'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
		'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
		'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
		'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
		'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
		'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
		'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
		'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
		'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
		'đ' => 'd',
	];

	private ?ICache $cache = null;
	private ?\Transliterator $transliterator = null;

	public function __construct(
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private ICacheFactory $cacheFactory,
		private AppSettings $settings,
	) {
		if ($this->cacheFactory->isAvailable()) {
			$this->cache = $this->cacheFactory->createDistributed('kdvn_visitors');
		}
		if (class_exists(\Transliterator::class)) {
			$this->transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()') ?: null;
		}
	}

	/**
	 * @return list<array{id: string, name: string}> best matches first; names are masked when $masked is true
	 */
	public function search(string $query, int $limit, bool $masked = false): array {
		$tokens = array_values(array_filter(preg_split('/\s+/', $this->fold($query)) ?: []));
		$minLength = $masked ? 3 : 2; // masked mode: harder to list everyone by typing short guesses
		if ($tokens === [] || mb_strlen(implode('', $tokens)) < $minLength) {
			return [];
		}

		$scored = [];
		foreach ($this->all() as $person) {
			$score = $this->score($person['folded'], $tokens);
			if ($score !== null) {
				$scored[] = [$score, $person];
			}
		}
		usort($scored, fn ($a, $b) => ($a[0] <=> $b[0]) ?: strcmp($a[1]['folded'], $b[1]['folded']));

		$found = [];
		foreach (array_slice($scored, 0, $limit) as [, $person]) {
			$found[] = ['id' => $person['id'], 'name' => $masked ? self::mask($person['name']) : $person['name']];
		}
		return $found;
	}

	/** "Nguyễn Văn A" -> "N*** V*** A***": the guest recognises the name, strangers learn little. */
	public static function mask(string $name): string {
		$words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
		return implode(' ', array_map(fn ($w) => mb_substr($w, 0, 1) . '***', $words));
	}

	/** Lower-case, accent-free text. đ/Đ become d. */
	public function fold(string $text): string {
		$text = trim($text);
		if ($this->transliterator !== null) {
			$folded = $this->transliterator->transliterate($text);
			if (is_string($folded)) {
				return $folded;
			}
		}
		// Fallback without the intl extension: Vietnamese letters (and đ) by table.
		return strtr(mb_strtolower($text), self::ACCENTS);
	}

	/** Every token must appear. Lower score = better: name starts with it, then a word starts with it, then anywhere. */
	private function score(string $folded, array $tokens): ?int {
		$score = 0;
		foreach ($tokens as $i => $token) {
			$at = strpos($folded, $token);
			if ($at === false) {
				return null;
			}
			if ($at === 0) {
				$score += 0;
			} elseif (strpos($folded, ' ' . $token) !== false) {
				$score += 1 + $i;
			} else {
				$score += 10;
			}
		}
		return $score;
	}

	/**
	 * Everyone who can be picked as a host, except the Talk sender account (a bot) and members of the
	 * groups ticked under "Hide from the staff search" (other service accounts, shared mailboxes...).
	 * @return list<array{id: string, name: string, folded: string}>
	 */
	private function all(): array {
		$hiddenGroups = $this->settings->staffHiddenGroups();
		$sender = $this->settings->talkUser();
		// The cache key changes with the settings, so a change applies at once.
		$cacheKey = self::CACHE_KEY . md5(json_encode([$hiddenGroups, $sender]));
		$cached = $this->cache?->get($cacheKey);
		if (is_array($cached)) {
			return $cached;
		}
		$hidden = $sender !== '' ? [$sender => true] : [];
		foreach ($hiddenGroups as $gid) {
			foreach ($this->groupManager->get($gid)?->searchUsers('') ?? [] as $user) {
				$hidden[$user->getUID()] = true;
			}
		}
		$people = [];
		foreach ($this->userManager->searchDisplayName('') as $user) {
			if (!$user->isEnabled() || isset($hidden[$user->getUID()])) {
				continue;
			}
			$name = $user->getDisplayName();
			$people[] = ['id' => $user->getUID(), 'name' => $name, 'folded' => $this->fold($name)];
		}
		$this->cache?->set($cacheKey, $people, self::CACHE_SECONDS);
		return $people;
	}
}
