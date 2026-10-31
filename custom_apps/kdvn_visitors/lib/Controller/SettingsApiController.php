<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Controller;

use OCA\KdvnVisitors\Service\AppSettings;
use OCA\KdvnVisitors\Service\Branding;
use OCA\KdvnVisitors\Service\RoomCatalog;
use OCA\KdvnVisitors\Service\TalkService;
use OCA\KdvnVisitors\Service\UserError;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;

/**
 * Admin settings. No attributes = Nextcloud's default: admins only, CSRF checked.
 */
class SettingsApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AppSettings $settings,
		private Branding $branding,
		private RoomCatalog $rooms,
		private TalkService $talk,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
		private ISecureRandom $random,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	public function save(
		string $companyName = '',
		string $officeAddress = '',
		string $talkUser = '',
		string $talkPassword = '',
		string $receptionGroup = '',
		int $retentionDays = 90,
		string $officeTimezone = 'Asia/Ho_Chi_Minh',
		array $appearance = [],
		string $autoPassMode = 'room',
		string $displayGuestMode = 'company',
		string $displayTitleMode = 'title',
		array $buildingWorkdays = [],
		int $autoCheckoutMinutes = 60,
		?array $receptionBuildings = null,
		?string $purposes = null,
		string $hostNamePrivacy = 'full',
		?array $officeGroups = null,
		?array $officeStaffGroups = null,
		?array $staffHiddenGroups = null,
	): DataResponse {
		$companyName = trim($companyName);
		$talkUser = trim($talkUser);
		if ($receptionGroup !== '' && !$this->groupManager->groupExists($receptionGroup)) {
			return new DataResponse(['message' => "There is no group \"$receptionGroup\"."], Http::STATUS_BAD_REQUEST);
		}
		foreach (array_merge($officeGroups ?? [], $officeStaffGroups ?? [], $staffHiddenGroups ?? []) as $gid) {
			if ($gid !== '' && !$this->groupManager->groupExists((string)$gid)) {
				return new DataResponse(['message' => "There is no group \"$gid\"."], Http::STATUS_BAD_REQUEST);
			}
		}
		if (!in_array($officeTimezone, \DateTimeZone::listIdentifiers(), true)) {
			return new DataResponse(['message' => 'Unknown time zone. Use a name like Asia/Ho_Chi_Minh.'], Http::STATUS_BAD_REQUEST);
		}
		if ($companyName === '') {
			return new DataResponse(['message' => 'Company name is required.'], Http::STATUS_BAD_REQUEST);
		}
		if ($talkUser !== '' && !$this->userManager->userExists($talkUser)) {
			return new DataResponse(['message' => "There is no Nextcloud user \"$talkUser\"."], Http::STATUS_BAD_REQUEST);
		}
		$this->settings->setCompanyName(mb_substr($companyName, 0, 255));
		$this->settings->setOfficeAddress(mb_substr(trim($officeAddress), 0, 500));
		$this->settings->setTalkUser($talkUser);
		if ($talkPassword !== '') {
			$this->settings->setTalkPassword($talkPassword); // empty = keep the saved one
		}
		$this->settings->setReceptionGroup($receptionGroup);
		$this->settings->setRetentionDays($retentionDays);
		$this->settings->setOfficeTimezone($officeTimezone);
		try {
			$this->settings->setAppearance($appearance);
			$this->settings->setCalendarSettings($autoPassMode, $displayGuestMode, $displayTitleMode);
			$this->settings->setAutoCheckoutMinutes($autoCheckoutMinutes);
			$this->settings->setHostNamePrivacy($hostNamePrivacy);
			if ($purposes !== null) {
				$this->settings->setPurposesText($purposes);
			}
			if ($officeGroups !== null) {
				$this->settings->setOfficeGroups($officeGroups);
			}
			if ($officeStaffGroups !== null) {
				$this->settings->setOfficeStaffGroups($officeStaffGroups);
			}
			if ($staffHiddenGroups !== null) {
				$this->settings->setStaffHiddenGroups($staffHiddenGroups);
			}
			if ($receptionBuildings !== null) {
				$this->settings->setReceptionBuildings($receptionBuildings);
			}
			if ($buildingWorkdays !== []) {
				$this->settings->setBuildingWorkdays($buildingWorkdays);
			}
		} catch (UserError $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(['ok' => true, 'hasTalkPassword' => $this->settings->hasTalkPassword()]);
	}

	/**
	 * Creates a new tablet link for an office. The office's old link stops working. Only the hash is
	 * stored, so this is the only time it is shown.
	 */
	public function newKioskLink(int $buildingId = 0): DataResponse {
		$known = array_column($this->rooms->buildings(), 'id');
		if (!in_array($buildingId, $known, true)) {
			return new DataResponse(['message' => 'Unknown office.'], Http::STATUS_BAD_REQUEST);
		}
		$key = $this->random->generate(48, ISecureRandom::CHAR_ALPHANUMERIC);
		$this->settings->setKioskKeyHash($buildingId, hash('sha256', $key));
		return new DataResponse([
			'url' => $this->urlGenerator->linkToRouteAbsolute('kdvn_visitors.kiosk.show', ['key' => $key]),
		]);
	}

	/** New secret link for the room displays, with one ready-made link per building. Shown only once. */
	public function newDisplayLink(): DataResponse {
		$key = $this->random->generate(48, ISecureRandom::CHAR_ALPHANUMERIC);
		$this->settings->setDisplayKeyHash(hash('sha256', $key));
		$base = $this->urlGenerator->linkToRouteAbsolute('kdvn_visitors.display.show', ['key' => $key]);
		$links = [['name' => 'All buildings', 'url' => $base]];
		foreach ($this->rooms->buildings() as $building) {
			$links[] = ['name' => $building['name'], 'url' => $base . '?building=' . $building['id']];
		}
		return new DataResponse(['links' => $links]);
	}

	/** Upload form field "logo" (multipart). Admins only, CSRF checked. */
	public function uploadLogo(): DataResponse {
		try {
			$this->branding->saveUpload($this->request->getUploadedFile('logo'));
		} catch (UserError $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(['logoUrl' => $this->branding->logoUrl(true)]);
	}

	public function removeLogo(): DataResponse {
		$this->branding->removeOwnLogo();
		return new DataResponse(['logoUrl' => $this->branding->logoUrl(true)]);
	}

	/** Posts a test message in every "Reception desk" conversation (one per reception group; creates them if needed). */
	public function testReception(): DataResponse {
		$groups = $this->settings->allReceptionGroups();
		if ($groups === []) {
			return new DataResponse(['message' => 'No reception group is set.'], Http::STATUS_BAD_REQUEST);
		}
		foreach ($groups as $gid) {
			$error = $this->talk->sendToGroupDesk($gid, '✅ Test from the Visitors app: walk-in guests, arrivals and check-in problems of your office will appear in this conversation.');
			if ($error !== null) {
				return new DataResponse(['message' => "Group \"$gid\": $error"], Http::STATUS_BAD_GATEWAY);
			}
		}
		return new DataResponse(['ok' => true, 'groups' => count($groups)]);
	}

	/** Sends a test Talk message to the admin who clicks the button. */
	public function testTalk(): DataResponse {
		$me = $this->userSession->getUser()->getUID();
		$error = $this->talk->send($me, '✅ Test from the Visitors app: arrival messages will look like this.');
		return $error === null
			? new DataResponse(['ok' => true])
			: new DataResponse(['message' => $error], Http::STATUS_BAD_GATEWAY);
	}
}
