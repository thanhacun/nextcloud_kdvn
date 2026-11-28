<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\Service;

use OCP\IAppConfig;
use OCP\IGroupManager;

/**
 * Adds buildings, floors and rooms from a spreadsheet (see SheetReader). One row = one room. Buildings and floors
 * are looked up by name (ignoring case and accents) and created when missing. A room that already exists is skipped,
 * never changed: this is for adding a list, not for editing. Always preview (apply = false) before importing.
 */
class RoomImporter {
	/** Header words (lower case, accents removed) => field. English and Vietnamese, so either sheet works. */
	private const HEADERS = [
		'building' => 'building', 'toa nha' => 'building', 'office' => 'building', 'van phong' => 'building',
		'address' => 'address', 'dia chi' => 'address',
		'floor' => 'floor', 'tang' => 'floor',
		'room' => 'room', 'room name' => 'room', 'name' => 'room', 'phong' => 'room', 'phong hop' => 'room', 'ten phong' => 'room',
		'room number' => 'number', 'number' => 'number', 'no' => 'number', 'so phong' => 'number',
		'seats' => 'seats', 'capacity' => 'seats', 'so cho' => 'seats', 'suc chua' => 'seats',
		'type' => 'type', 'loai' => 'type',
		'equipment' => 'equipment', 'thiet bi' => 'equipment',
		'groups' => 'groups', 'who can book' => 'groups', 'group' => 'groups', 'nhom' => 'groups',
		'contact' => 'contact', 'contact person' => 'contact', 'nguoi phu trach' => 'contact',
		'room id' => 'uid', 'id' => 'uid',
		'email' => 'email', 'booking email' => 'email',
	];

	private const TYPES = [
		'meeting-room' => ['meeting room', 'meeting', 'meeting-room', 'phong hop'],
		'lecture-hall' => ['lecture hall', 'lecture', 'lecture-hall', 'hoi truong'],
		'other' => ['other', 'khac'],
	];

	/** word in the Equipment cell => ResourceStore feature key */
	private const EQUIPMENT = [
		'video' => 'hasVideoConferencing', 'conferenc' => 'hasVideoConferencing', 'zoom' => 'hasVideoConferencing', 'teams' => 'hasVideoConferencing',
		'projector' => 'hasProjector', 'may chieu' => 'hasProjector',
		'tv' => 'hasTv', 'television' => 'hasTv', 'man hinh' => 'hasTv', 'screen' => 'hasTv',
		'whiteboard' => 'hasWhiteboard', 'bang' => 'hasWhiteboard',
		'phone' => 'hasPhone', 'dien thoai' => 'hasPhone',
		'wheelchair' => 'isWheelchairAccessible', 'xe lan' => 'isWheelchairAccessible',
	];

	public function __construct(
		private ResourceStore $store,
		private IGroupManager $groupManager,
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @param string[][] $sheet rows from SheetReader, the first row being the column names
	 * @return array{rows: array<int, array{line: int, building: string, floor: string, room: string, status: string, message: string}>, counts: array{add: int, skip: int, error: int}, applied: bool}
	 * @throws UserError when the header row has no Building, Floor or Room column
	 */
	public function run(array $sheet, bool $apply): array {
		if ($sheet === []) {
			throw new UserError('The file is empty.');
		}
		$columns = $this->columns(array_shift($sheet));
		foreach (['building' => 'Building', 'floor' => 'Floor', 'room' => 'Room'] as $field => $label) {
			if (!isset($columns[$field])) {
				throw new UserError("The first row must name the columns, and one must be \"$label\". Download the template to see them.");
			}
		}

		$data = $this->store->listAll();
		if (empty($data['ready'])) {
			throw new UserError('The Calendar Resource Management app is not enabled.');
		}
		// Working copy of what exists, extended as the import creates things (also in a preview, so repeated
		// rows in the file are seen as duplicates of each other).
		$buildings = []; // key => id
		foreach ($data['buildings'] as $b) {
			$buildings[$this->key($b['displayName'])] = $b['id'];
		}
		$floors = []; // buildingKey|floorKey => id
		$buildingKeyById = array_flip($buildings);
		foreach ($data['floors'] as $f) {
			$floors[($buildingKeyById[$f['buildingId']] ?? '?') . '|' . $this->key($f['displayName'])] = $f['id'];
		}
		$uids = [];
		$emails = [];
		$namesOnFloor = [];
		$floorKeyById = [];
		foreach ($floors as $k => $id) {
			$floorKeyById[$id] = $k;
		}
		foreach ($data['rooms'] as $r) {
			$uids[strtolower($r['uid'])] = true;
			$emails[strtolower($r['email'])] = true;
			if (isset($floorKeyById[$r['floorId']])) {
				$namesOnFloor[$floorKeyById[$r['floorId']] . '|' . $this->key($r['displayName'])] = true;
			}
		}
		$groupIds = [];
		foreach ($data['groups'] as $g) {
			$groupIds[$this->key($g['id'])] = $g['id'];
			$groupIds[$this->key($g['name'])] = $g['id'];
		}
		$domain = $this->appConfig->getValueString('kdvn_rooms', 'email_domain', 'resources.invalid');

		$result = [];
		$counts = ['add' => 0, 'skip' => 0, 'error' => 0];
		$work = function () use (&$result, &$counts, $sheet, $columns, &$buildings, &$floors, &$uids, &$emails, &$namesOnFloor, $groupIds, $domain, $apply): void {
			foreach ($sheet as $i => $cells) {
				$line = $i + 2; // the header was line 1
				$get = fn (string $field) => trim((string)($cells[$columns[$field] ?? -1] ?? ''));
				$entry = ['line' => $line, 'building' => $get('building'), 'floor' => $get('floor'), 'room' => $get('room'), 'status' => 'add', 'message' => ''];
				try {
					foreach (['building' => 'Building', 'floor' => 'Floor', 'room' => 'Room'] as $field => $label) {
						if ($get($field) === '') {
							throw new UserError("$label is empty.");
						}
					}
					$bKey = $this->key($get('building'));
					$fKey = $bKey . '|' . $this->key($get('floor'));
					$rKey = $fKey . '|' . $this->key($get('room'));
					if (isset($namesOnFloor[$rKey])) {
						$entry['status'] = 'skip';
						$entry['message'] = 'Already exists on this floor.';
						$counts['skip']++;
						$result[] = $entry;
						continue;
					}
					$uid = $get('uid') !== '' ? $get('uid') : $this->slug($get('building') . ' ' . $get('room'));
					$email = $get('email') !== '' ? $get('email') : ($this->slug($uid) !== '' ? 'room-' . $this->slug($uid) . '@' . $domain : '');
					if ($uid === '') {
						throw new UserError('Cannot make a Room ID from this name. Add a "Room ID" column.');
					}
					if (isset($uids[strtolower($uid)])) {
						throw new UserError("Another room already uses the ID \"$uid\" (rename the room or fill the Room ID column).");
					}
					if (isset($emails[strtolower($email)])) {
						throw new UserError("Another room already uses the email \"$email\".");
					}
					$body = [
						'displayName' => $get('room'),
						'roomNumber' => $get('number'),
						'capacity' => $this->seats($get('seats')),
						'roomType' => $this->type($get('type')),
						'contactPersonUserId' => $get('contact'),
						'groups' => $this->groups($get('groups'), $groupIds),
						'uid' => $uid,
						'email' => $email,
					];
					foreach ($this->equipment($get('equipment')) as $feature) {
						$body[$feature] = true;
					}
					if ($apply) {
						if (!isset($buildings[$bKey])) {
							$buildings[$bKey] = $this->store->saveBuilding(null, ['displayName' => $get('building'), 'address' => $get('address')]);
						}
						if (!isset($floors[$fKey])) {
							$floors[$fKey] = $this->store->saveFloor(null, ['displayName' => $get('floor'), 'buildingId' => $buildings[$bKey]]);
						}
						$body['floorId'] = $floors[$fKey];
						$this->store->saveRoom(null, $body);
					} else {
						// Not created in a preview, but remembered so the same building/floor reads as new once.
						$entry['message'] = (isset($buildings[$bKey]) ? '' : 'New building. ') . (isset($floors[$fKey]) ? '' : 'New floor. ');
						$buildings[$bKey] ??= 0;
						$floors[$fKey] ??= 0;
						$entry['message'] = trim($entry['message']);
					}
					$namesOnFloor[$rKey] = true;
					$uids[strtolower($uid)] = true;
					$emails[strtolower($email)] = true;
					$counts['add']++;
				} catch (UserError $e) {
					$entry['status'] = 'error';
					$entry['message'] = $e->getMessage();
					$counts['error']++;
				}
				$result[] = $entry;
			}
		};
		if ($apply) {
			$this->store->batch($work); // refreshes Calendar's room cache once at the end
		} else {
			$work();
		}
		return ['rows' => $result, 'counts' => $counts, 'applied' => $apply];
	}

	/** @return array<string, int> field => column number */
	private function columns(array $header): array {
		$out = [];
		foreach ($header as $index => $name) {
			$field = self::HEADERS[$this->key((string)$name)] ?? null;
			if ($field !== null && !isset($out[$field])) {
				$out[$field] = $index;
			}
		}
		return $out;
	}

	private function seats(string $text): ?int {
		if ($text === '') {
			return null;
		}
		if (!ctype_digit($text) || (int)$text > 100000) {
			throw new UserError("Seats must be a whole number, not \"$text\".");
		}
		return (int)$text;
	}

	private function type(string $text): string {
		if ($text === '') {
			return 'meeting-room';
		}
		$key = $this->key($text);
		foreach (self::TYPES as $type => $names) {
			if (in_array($key, $names, true)) {
				return $type;
			}
		}
		throw new UserError("Unknown type \"$text\". Use Meeting room, Lecture hall or Other.");
	}

	/** @return string[] ResourceStore feature keys */
	private function equipment(string $text): array {
		$found = [];
		foreach (preg_split('/[,;\/|\n]+/', $text) ?: [] as $part) {
			$key = $this->key($part);
			if ($key === '') {
				continue;
			}
			$matched = false;
			foreach (self::EQUIPMENT as $word => $feature) {
				if (str_contains($key, $word)) {
					$found[$feature] = true;
					$matched = true;
					break;
				}
			}
			if (!$matched) {
				throw new UserError("Unknown equipment \"" . trim($part) . "\". Use: video, projector, tv, whiteboard, phone, wheelchair.");
			}
		}
		return array_keys($found);
	}

	/** @param array<string, string> $groupIds key => real group id */
	private function groups(string $text, array $groupIds): array {
		$out = [];
		foreach (preg_split('/[,;|\n]+/', $text) ?: [] as $part) {
			$key = $this->key($part);
			if ($key === '') {
				continue;
			}
			if (!isset($groupIds[$key])) {
				throw new UserError('There is no group "' . trim($part) . '". Use the group name or ID from Nextcloud.');
			}
			$out[] = $groupIds[$key];
		}
		return array_values(array_unique($out));
	}

	/** Lower case, no accents, single spaces: how names are compared. */
	private function key(string $text): string {
		$text = $this->ascii($text);
		return trim(preg_replace('/\s+/', ' ', strtolower($text)) ?? '');
	}

	/** "Phòng họp Đà Nẵng 2" -> "phong-hop-da-nang-2" (same as the room form in the browser) */
	private function slug(string $text): string {
		return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($this->ascii($text))) ?? '', '-');
	}

	private function ascii(string $text): string {
		if (class_exists(\Normalizer::class)) {
			$decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);
			if (is_string($decomposed)) {
				$text = $decomposed;
			}
		}
		$text = preg_replace('/[\x{0300}-\x{036f}]/u', '', $text) ?? $text;
		// Without PHP intl the letters stay precomposed: strip the Vietnamese ones by table.
		$from = 'àáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđ';
		$to = 'aaaaaaaaaaaaaaaaaeeeeeeeeeeeiiiiiooooooooooooooooouuuuuuuuuuuyyyyyd';
		$map = [];
		foreach (mb_str_split($from) as $i => $ch) {
			$map[$ch] = $to[$i];
			$map[mb_strtoupper($ch)] = strtoupper($to[$i]);
		}
		return strtr($text, $map);
	}
}
