<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;

/**
 * Who hears about what:
 *  - guest invited by hand     -> the person who invited (Talk, with the code and the pass link)
 *  - invited guest arrives     -> host (Talk; Nextcloud notification + Reception desk if Talk fails)
 *  - walk-in guest arrives     -> host (Talk, with a link to decide) + Reception desk
 *  - walk-in let in / declined -> Reception desk + host (if someone else decided)
 *  - problem at the tablet     -> Reception desk (at most once per visit and reason per 10 minutes)
 *
 * Host messages use the host's Nextcloud language; Reception desk messages use the
 * language of the Talk sender account (set it on that user's Personal settings).
 */
class Messenger {
	public function __construct(
		private TalkService $talk,
		private INotificationManager $notifications,
		private IConfig $config,
		private IUserManager $userManager,
		private IURLGenerator $urlGenerator,
		private VisitFormatter $formatter,
		private AppSettings $settings,
		private ICacheFactory $cacheFactory,
	) {
	}

	/**
	 * Right after a manual invitation: everything the inviter needs on a phone, including the code
	 * and the pass link (so they can forward it by Zalo). Talk only; failures never block the invitation.
	 */
	public function inviteCreated(array $visit, string $passUrl, string $inviter): void {
		$lang = $this->langOf($inviter);
		$lines = [
			$this->t($lang, 'talk_invited', $visit),
			Texts::get($lang, 'talk_when', ['when' => $this->formatter->when($visit, $lang)]),
		];
		if (!empty($visit['room_name'])) {
			$lines[] = Texts::get($lang, 'talk_room', ['room' => $visit['room_name']]);
		}
		if (!empty($visit['host_uid']) && $visit['host_uid'] !== $inviter) {
			$lines[] = Texts::get($lang, 'talk_host_line', ['host' => $this->nameOf($visit['host_uid'])]);
		}
		$lines[] = Texts::get($lang, 'talk_phone', ['phone' => $visit['guest_phone']]);
		$lines[] = Texts::get($lang, 'talk_email', ['email' => $visit['guest_email']]);
		if (!empty($visit['purpose'])) {
			$lines[] = Texts::get($lang, 'talk_purpose', ['purpose' => $visit['purpose']]);
		}
		$lines[] = Texts::get($lang, 'talk_code', ['code' => $visit['code']]);
		$lines[] = Texts::get($lang, 'talk_pass_link', ['link' => $passUrl]);
		$this->talk->send($inviter, implode("\n", $lines));
	}

	public function invitedArrived(array $visit, ?string $checkedInBy = null): void {
		$host = $visit['host_uid'];
		if ($host === null || $host === '') {
			return;
		}
		$lang = $this->langOf($host);
		$lines = [$this->t($lang, 'talk_arrived', $visit)];
		if (!empty($visit['room_name'])) {
			$lines[] = Texts::get($lang, 'talk_room', ['room' => $visit['room_name']]);
		}
		$lines[] = Texts::get($lang, 'talk_time', ['time' => $this->formatter->time($visit['start_at'], $host, $lang)]);
		if ($checkedInBy !== null && $checkedInBy !== $host) {
			$lines[] = $this->t($lang, 'talk_checked_in_by', $visit, ['who' => $this->nameOf($checkedInBy)]);
		}

		if ($this->talk->send($host, implode("\n", $lines)) !== null) {
			$this->bell($host, 'guest_arrived', $visit);
			$this->toReception('rc_host_unreachable', $visit);
		}
	}

	public function walkInArrived(array $visit): void {
		$host = $visit['host_uid'];
		if ($host !== null && $host !== '') {
			$lang = $this->langOf($host);
			$lines = [$this->t($lang, 'talk_walkin_host', $visit)];
			$lines[] = Texts::get($lang, 'talk_phone', ['phone' => $visit['guest_phone']]);
			if (!empty($visit['purpose'])) {
				$lines[] = Texts::get($lang, 'talk_purpose', ['purpose' => $visit['purpose']]);
			}
			$lines[] = Texts::get($lang, 'talk_walkin_action', ['link' => $this->urlGenerator->linkToRouteAbsolute('kdvn_visitors.page.index')]);
			if ($this->talk->send($host, implode("\n", $lines)) !== null) {
				$this->bell($host, 'walkin_waiting', $visit);
			}
		}
		$this->toReception($host ? 'rc_walkin' : 'rc_walkin_nohost', $visit, [], true);
	}

	public function decided(array $visit, bool $approved, string $decidedBy): void {
		$key = $approved ? 'talk_decided_in' : 'talk_decided_out';
		$who = ['who' => $this->nameOf($decidedBy)];
		$this->toReception($key, $visit, $who);
		$host = $visit['host_uid'];
		if ($host && $host !== $decidedBy) {
			$this->talk->send($host, $this->t($this->langOf($host), $key, $visit, $who));
		}
	}

	/** $reason: cancelled | too_early | expired | declined */
	public function problem(array $visit, string $reason): void {
		$cache = $this->cacheFactory->createDistributed('kdvn_visitors');
		$cacheKey = 'problem-' . $visit['id'] . '-' . $reason;
		if ($cache->get($cacheKey) !== null) {
			return; // the guest is probably scanning again and again; don't flood the desk
		}
		$cache->set($cacheKey, 1, 600);

		$lang = $this->receptionLang();
		$reasonText = Texts::get($lang, 'reason_' . $reason, [
			'time' => $this->formatter->time($visit['start_at'], (string)$visit['host_uid'], $lang),
		]);
		$this->toReception('rc_problem', $visit, ['reason' => $reasonText]);
	}

	// ------------------------------------------------------------ helpers

	private function toReception(string $key, array $visit, array $extra = [], bool $withDetails = false): void {
		if ($this->settings->receptionGroup() === '') {
			return;
		}
		$lang = $this->receptionLang();
		$lines = [$this->t($lang, $key, $visit, $extra)];
		if ($withDetails) {
			$lines[] = Texts::get($lang, 'talk_phone', ['phone' => $visit['guest_phone']]);
			if (!empty($visit['purpose'])) {
				$lines[] = Texts::get($lang, 'talk_purpose', ['purpose' => $visit['purpose']]);
			}
		}
		$this->talk->sendToReception(implode("\n", $lines));
	}

	private function t(string $lang, string $key, array $visit, array $extra = []): string {
		return Texts::get($lang, $key, $extra + [
			'guest' => $visit['guest_name'],
			'company' => $visit['guest_company'],
			'host' => $visit['host_uid'] ? $this->nameOf($visit['host_uid']) : Texts::get($lang, 'nobody'),
		]);
	}

	/** Backup notification in Nextcloud's bell (also pushed to the mobile app). */
	private function bell(string $uid, string $subject, array $visit): void {
		$notification = $this->notifications->createNotification();
		$notification->setApp('kdvn_visitors')
			->setUser($uid)
			->setDateTime(new \DateTime())
			->setObject('visit', (string)$visit['id'])
			->setSubject($subject, [
				'guest' => $visit['guest_name'],
				'company' => $visit['guest_company'],
				'room' => (string)($visit['room_name'] ?? ''),
			]);
		$this->notifications->notify($notification);
	}

	private function nameOf(string $uid): string {
		return $this->userManager->get($uid)?->getDisplayName() ?? $uid;
	}

	private function langOf(string $uid): string {
		return Texts::normalize($this->config->getUserValue($uid, 'core', 'lang', 'en'));
	}

	private function receptionLang(): string {
		$sender = $this->settings->talkUser();
		return $sender !== '' ? $this->langOf($sender) : 'en';
	}
}
