<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\Http\Client\IClientService;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Sends Talk messages from the "Reception" account through Talk's public REST API
 * (the API the Talk mobile apps use), so we don't depend on Talk's internal PHP code.
 *
 *  - send():          one-to-one message to a user (the host)
 *  - sendToReception(): message in the "Reception desk" group conversation,
 *                     created on first use with the reception group as members
 */
class TalkService {
	private const ROOM_ONE_TO_ONE = 1;
	private const ROOM_GROUP = 2;

	public function __construct(
		private AppSettings $settings,
		private IClientService $clientService,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	public function isConfigured(): bool {
		return $this->settings->talkUser() !== '' && $this->settings->hasTalkPassword();
	}

	/** @return string|null null on success, otherwise a short error description */
	public function send(string $toUserId, string $message): ?string {
		if ($toUserId === $this->settings->talkUser()) {
			return 'The host is the Talk sender account itself.';
		}
		try {
			$room = $this->request('POST', '/ocs/v2.php/apps/spreed/api/v4/room',
				['roomType' => self::ROOM_ONE_TO_ONE, 'invite' => $toUserId]); // 200 = exists, 201 = created
			$token = $room['token'] ?? null;
			if (!is_string($token) || $token === '') {
				return 'Talk did not return a conversation.';
			}
			$this->postMessage($token, $message);
			return null;
		} catch (\Throwable $e) {
			$this->logger->warning('kdvn_visitors: Talk message to ' . $toUserId . ' failed: ' . $e->getMessage(), ['exception' => $e]);
			return 'Talk request failed: ' . $e->getMessage();
		}
	}

	/** @return string|null null on success, otherwise a short error description */
	public function sendToReception(string $message): ?string {
		$group = $this->settings->receptionGroup();
		if ($group === '') {
			return 'No reception group is set (Administration settings > Visitors).';
		}
		try {
			$token = $this->settings->receptionRoomToken();
			if ($token !== '') {
				try {
					$this->postMessage($token, $message);
					return null;
				} catch (\Throwable $e) {
					if ($e->getCode() !== 404) {
						throw $e;
					}
					// The conversation was deleted in Talk: make a new one below.
				}
			}
			$room = $this->request('POST', '/ocs/v2.php/apps/spreed/api/v4/room', [
				'roomType' => self::ROOM_GROUP,
				'invite' => $group,
				'source' => 'groups', // members of the group are added, also people who join it later
				'roomName' => 'Reception desk',
			]);
			$token = $room['token'] ?? '';
			if (!is_string($token) || $token === '') {
				return 'Talk did not return a conversation.';
			}
			$this->settings->setReceptionRoomToken($token);
			$this->postMessage($token, $message);
			return null;
		} catch (\Throwable $e) {
			$this->logger->warning('kdvn_visitors: Talk message to the reception desk failed: ' . $e->getMessage(), ['exception' => $e]);
			return 'Talk request failed: ' . $e->getMessage();
		}
	}

	private function postMessage(string $token, string $message): void {
		$this->request('POST', '/ocs/v2.php/apps/spreed/api/v1/chat/' . rawurlencode($token), ['message' => $message]);
	}

	/**
	 * Calls a Talk OCS endpoint as the reception user and returns ocs.data.
	 * Throws on HTTP errors; the exception code is the HTTP status (e.g. 404).
	 */
	private function request(string $method, string $path, array $json): array {
		$sender = $this->settings->talkUser();
		$password = $this->settings->talkPassword();
		if ($sender === '' || $password === '') {
			throw new \RuntimeException('The Talk sender account is not set up (Administration settings > Visitors).');
		}
		$base = $this->settings->talkBaseUrl() ?: rtrim($this->urlGenerator->getAbsoluteURL('/'), '/');
		$client = $this->clientService->newClient();
		$response = $client->request($method, $base . $path, [
			'auth' => [$sender, $password],
			'headers' => ['OCS-APIRequest' => 'true', 'Accept' => 'application/json'],
			'json' => $json,
			'timeout' => 10,
			// Our own server may resolve to a private IP; Nextcloud blocks those unless allowed per request.
			'nextcloud' => ['allow_local_address' => true],
		]);
		$body = $response->getBody();
		$data = json_decode(is_string($body) ? $body : (string)stream_get_contents($body), true);
		return is_array($data['ocs']['data'] ?? null) ? $data['ocs']['data'] : [];
	}
}
