<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

/** An error whose message is safe to show on the page. */
class UserError extends \Exception {
	public function __construct(string $message, int $httpStatus = 400) {
		parent::__construct($message, $httpStatus);
	}
}
