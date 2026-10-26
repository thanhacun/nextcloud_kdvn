<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\IUserManager;
use OCP\Mail\IMailer;

/** Builds and sends the invitation email with the visitor pass link. */
class GuestMailer {
	public function __construct(
		private IMailer $mailer,
		private IUserManager $userManager,
		private VisitFormatter $formatter,
		private AppSettings $settings,
	) {
	}

	/** @throws \Throwable when the mail server rejects the message */
	public function sendInvitation(array $visit, string $passUrl): void {
		$lang = Texts::normalize($visit['language']);
		$t = fn (string $key, array $params = []) => Texts::get($lang, $key, $params);
		$host = $this->userManager->get((string)$visit['host_uid']);
		$hostName = $host ? $host->getDisplayName() : $visit['host_uid'];
		$company = $this->settings->companyName();

		$when = $this->formatter->when($visit, $lang);

		$template = $this->mailer->createEMailTemplate('kdvn_visitors.invitation', ['visitId' => $visit['id']]);
		$template->setSubject($t('mail_subject', [
			'company' => $company,
			'date' => $this->formatter->date($visit, $lang),
		]));
		$template->addHeader();
		$template->addHeading($t('mail_heading', ['company' => $company]));
		$template->addBodyText($t('mail_intro', ['host' => $hostName]));
		$template->addBodyListItem($when, $t('when'));
		$template->addBodyListItem($hostName, $t('host'));
		if (!empty($visit['room_name'])) {
			$template->addBodyListItem($visit['room_name'], $t('room'));
		}
		if ($this->settings->officeAddress() !== '') {
			$template->addBodyListItem($this->settings->officeAddress(), $t('address'));
		}
		$template->addBodyButton($t('open_pass'), $passUrl);
		$template->addBodyText($t('code_line', ['code' => $visit['code']]));
		$template->addFooter('', $lang);

		$message = $this->mailer->createMessage();
		$message->setTo([$visit['guest_email'] => $visit['guest_name']]);
		if ($host && $host->getEMailAddress()) {
			$message->setReplyTo([$host->getEMailAddress() => $hostName]); // guest replies go to the host
		}
		$message->useTemplate($template);

		$failed = $this->mailer->send($message);
		if ($failed !== []) {
			throw new \RuntimeException('The mail server rejected: ' . implode(', ', $failed));
		}
	}
}
