<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\Service;

/** An error whose message is safe and useful to show in the web page. */
class UserError extends \Exception {
	public function __construct(string $message, int $httpStatus = 400) {
		parent::__construct($message, $httpStatus);
	}
}
