<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Notification;

use OCA\KdvnVisitors\Service\Texts;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/** Turns our stored notification ("guest_arrived" + parameters) into readable text. */
class Notifier implements INotifier {
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

	public function prepare(INotification $notification, string $languageCode): INotification {
		$keys = ['guest_arrived' => 'talk_arrived', 'walkin_waiting' => 'talk_walkin_host'];
		if ($notification->getApp() !== 'kdvn_visitors' || !isset($keys[$notification->getSubject()])) {
			throw new UnknownNotificationException();
		}
		$p = $notification->getSubjectParameters();
		$lang = Texts::normalize($languageCode);
		$subject = Texts::get($lang, $keys[$notification->getSubject()], ['guest' => $p['guest'] ?? '', 'company' => $p['company'] ?? '']);
		if (!empty($p['room'])) {
			$subject .= ' ' . Texts::get($lang, 'talk_room', ['room' => $p['room']]);
		}
		$notification->setParsedSubject($subject)
			->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('kdvn_visitors', 'app-dark.svg')))
			->setLink($this->urlGenerator->linkToRouteAbsolute('kdvn_visitors.page.index'));
		return $notification;
	}
}
