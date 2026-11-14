<?php

declare(strict_types=1);

return [
	'routes' => [
		// Public display (secret key in the URL)
		['name' => 'display#show', 'url' => '/display/{key}', 'verb' => 'GET'],
		['name' => 'display#readings', 'url' => '/display/{key}/readings', 'verb' => 'GET'],

		// Admin settings
		['name' => 'settings_api#save', 'url' => '/admin/settings', 'verb' => 'POST'],
		['name' => 'settings_api#new_display_link', 'url' => '/admin/display-link', 'verb' => 'POST'],
	],
];
