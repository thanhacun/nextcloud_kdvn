<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\Security\ISecureRandom;

/**
 * Proof, for the touch room display, that a verified person wants THIS room for THIS long.
 *
 * Whatever identified the person (the phone QR with Nextcloud login today, a face check later) ends
 * in issue(); the booking endpoint accepts nothing but verify()'d tickets, never a bare user name.
 * A ticket is HMAC-signed (key derived from the instance secret), valid 60 s and usable once.
 *
 * Also keeps the short-lived "claims" behind the QR code: a screen opens a claim for a room, a phone
 * (logged in) fills in who it is, the screen collects a ticket.
 */
class TouchTicket {
	public const TICKET_SECONDS = 60;
	public const CLAIM_SECONDS = 120;

	public function __construct(
		private IConfig $config,
		private ICacheFactory $cacheFactory,
		private ISecureRandom $random,
	) {
	}

	public function issue(string $uid, int $roomId, int $minutes, ?int $building): string {
		$payload = json_encode([
			'u' => $uid, 'r' => $roomId, 'm' => $minutes, 'b' => $building,
			'e' => time() + self::TICKET_SECONDS,
			'j' => $this->random->generate(16, ISecureRandom::CHAR_ALPHANUMERIC),
		], JSON_THROW_ON_ERROR);
		$body = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
		return $body . '.' . $this->sign($body);
	}

	/**
	 * @return array{uid: string, roomId: int, minutes: int, building: ?int}|null null when forged, expired or already used
	 */
	public function redeem(string $ticket): ?array {
		$parts = explode('.', $ticket);
		if (count($parts) !== 2 || !hash_equals($this->sign($parts[0]), $parts[1])) {
			return null;
		}
		$data = json_decode((string)base64_decode(strtr($parts[0], '-_', '+/')), true);
		if (!is_array($data) || !isset($data['u'], $data['r'], $data['m'], $data['e'], $data['j']) || $data['e'] < time()) {
			return null;
		}
		$cache = $this->cache();
		if ($cache->get('jti-' . $data['j']) !== null) {
			return null; // used before
		}
		$cache->set('jti-' . $data['j'], 1, self::TICKET_SECONDS + 5);
		return ['uid' => (string)$data['u'], 'roomId' => (int)$data['r'], 'minutes' => (int)$data['m'], 'building' => $data['b'] === null ? null : (int)$data['b']];
	}

	// ----------------------------------------------------------- claims behind the QR code

	/** A screen asks for a code to show for this room and duration. */
	public function openClaim(int $roomId, int $minutes, ?int $building): string {
		$nonce = $this->random->generate(24, ISecureRandom::CHAR_ALPHANUMERIC);
		$this->cache()->set('claim-' . $nonce, ['room' => $roomId, 'minutes' => $minutes, 'building' => $building, 'uid' => null], self::CLAIM_SECONDS);
		return $nonce;
	}

	/** @return array{room: int, minutes: int, building: ?int, uid: ?string}|null */
	public function claim(string $nonce): ?array {
		$claim = $this->cache()->get('claim-' . $nonce);
		return is_array($claim) ? $claim : null;
	}

	/** The logged-in phone says who it is. False when the claim is gone or already taken by someone. */
	public function fillClaim(string $nonce, string $uid): bool {
		$claim = $this->claim($nonce);
		if ($claim === null || ($claim['uid'] !== null && $claim['uid'] !== $uid)) {
			return false;
		}
		$claim['uid'] = $uid;
		$this->cache()->set('claim-' . $nonce, $claim, self::CLAIM_SECONDS);
		return true;
	}

	/** The screen collects the ticket once the phone has confirmed; the claim is then used up. */
	public function collect(string $nonce): ?string {
		$claim = $this->claim($nonce);
		if ($claim === null || $claim['uid'] === null) {
			return null;
		}
		$this->cache()->remove('claim-' . $nonce);
		return $this->issue($claim['uid'], $claim['room'], $claim['minutes'], $claim['building']);
	}

	private function sign(string $body): string {
		$key = hash('sha256', 'kdvn_visitors.touch|' . $this->config->getSystemValueString('secret', ''));
		return rtrim(strtr(base64_encode(hash_hmac('sha256', $body, $key, true)), '+/', '-_'), '=');
	}

	private function cache(): \OCP\ICache {
		return $this->cacheFactory->createDistributed('kdvn_visitors_touch');
	}
}
