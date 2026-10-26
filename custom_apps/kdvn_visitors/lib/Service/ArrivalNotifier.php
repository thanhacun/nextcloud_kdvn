<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\IConfig;
use OCP\Notification\IManager as INotificationManager;

/** Tells the host their guest arrived: Talk message first, Nextcloud notification as backup. */
class ArrivalNotifier {
	public function __construct(
		private TalkService $talk,
		private INotificationManager $notifications,
		private IConfig $config,
		private VisitFormatter $formatter,
	) {
	}

	public function notifyHost(array $visit): void {
		$host = $visit['host_uid'];
		$lang = Texts::normalize($this->config->getUserValue($host, 'core', 'lang', 'en'));
		$time = $this->formatter->time($visit['start_at'], $host, $lang);

		$lines = [Texts::get($lang, 'talk_arrived', ['guest' => $visit['guest_name'], 'company' => $visit['guest_company']])];
		if (!empty($visit['room_name'])) {
			$lines[] = Texts::get($lang, 'talk_room', ['room' => $visit['room_name']]);
		}
		$lines[] = Texts::get($lang, 'talk_time', ['time' => $time]);

		$error = $this->talk->send($host, implode("\n", $lines));
		if ($error === null) {
			return;
		}

		// Backup: the bell in Nextcloud (and push to the mobile app).
		$notification = $this->notifications->createNotification();
		$notification->setApp('kdvn_visitors')
			->setUser($host)
			->setDateTime(new \DateTime())
			->setObject('visit', (string)$visit['id'])
			->setSubject('guest_arrived', [
				'guest' => $visit['guest_name'],
				'company' => $visit['guest_company'],
				'room' => (string)($visit['room_name'] ?? ''),
			]);
		$this->notifications->notify($notification);
	}
}
