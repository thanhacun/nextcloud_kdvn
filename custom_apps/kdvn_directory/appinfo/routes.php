<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'api#data', 'url' => '/api/data', 'verb' => 'GET'],
		['name' => 'settings_api#save', 'url' => '/api/settings', 'verb' => 'POST'],
	],
];
