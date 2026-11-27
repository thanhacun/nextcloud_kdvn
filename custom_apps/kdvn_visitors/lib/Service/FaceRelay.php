<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\Http\Client\IClientService;
use OCP\IUserManager;
use OCP\IUser;

/**
 * Asks the Odoo face service (kdvn_face_kiosk, POST /kdvn_face/ext/identify) who is in a picture.
 * Server to server only: the tablet never talks to Odoo and never sees the key. Nothing is stored here,
 * not the picture and not the answer. Needs the matching Odoo feature switched on and a key made there.
 *
 * Odoo answers {state: none|many|unknown|ambiguous|ok, login, name}; login and name only for "ok".
 * The Nextcloud user name is the Odoo login without the domain part (decided by Thanh, 2026-11-27).
 */
class FaceRelay {
	public const PATH = '/kdvn_face/ext/identify';
	public const MAX_BYTES = 300000;
	private const STATES = ['none', 'many', 'unknown', 'ambiguous', 'ok'];

	public function __construct(
		private AppSettings $settings,
		private IClientService $clientService,
		private IUserManager $userManager,
	) {
	}

	/**
	 * @return array{state: string, login?: string, name?: string}
	 * @throws \RuntimeException when Odoo cannot be reached or refuses us (wrong key, feature off, 503 engine down); code 429 = slow down
	 */
	public function identify(string $jpeg): array {
		$key = $this->settings->faceKey();
		if (!$this->settings->faceReady() || $key === '') {
			throw new \RuntimeException('Face recognition is not set up.');
		}
		$response = $this->clientService->newClient()->request('POST', $this->settings->faceUrl() . self::PATH, [
			'headers' => ['X-KDVN-Key' => $key, 'Accept' => 'application/json'],
			'multipart' => [['name' => 'image', 'contents' => $jpeg, 'filename' => 'scan.jpg', 'headers' => ['Content-Type' => 'image/jpeg']]],
			'timeout' => 6,
			'http_errors' => false,
			// Odoo is on the company network; Nextcloud blocks private addresses unless allowed per request (as for Talk).
			'nextcloud' => ['allow_local_address' => true],
		]);
		$status = $response->getStatusCode();
		if ($status === 403) {
			throw new \RuntimeException('Odoo refused the key, or face recognition is switched off in Odoo.');
		}
		if ($status === 429) {
			throw new \RuntimeException('Odoo says too many requests.', 429); // the screen just waits for the next frame
		}
		$body = $response->getBody();
		$data = json_decode(is_string($body) ? $body : (string)stream_get_contents($body), true);
		if ($status !== 200 || !is_array($data) || !in_array($data['state'] ?? null, self::STATES, true)) {
			throw new \RuntimeException('Odoo answered with an unexpected reply (HTTP ' . $status . ').');
		}
		return $data;
	}

	/** The Nextcloud account for an Odoo login ("an.nguyen@kinden.com.vn" -> "an.nguyen"), only when it exists and is enabled. */
	public function userForLogin(string $login): ?IUser {
		$uid = strstr($login, '@', true);
		$uid = trim($uid === false ? $login : $uid);
		if ($uid === '') {
			return null;
		}
		$user = $this->userManager->get($uid) ?? $this->userManager->get(mb_strtolower($uid));
		return $user !== null && $user->isEnabled() ? $user : null;
	}
}
