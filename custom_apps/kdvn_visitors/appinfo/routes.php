<?php

declare(strict_types=1);

return [
	'routes' => [
		// Staff page (logged in)
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'visit_api#list', 'url' => '/api/visits', 'verb' => 'GET'],
		['name' => 'visit_api#create', 'url' => '/api/visits', 'verb' => 'POST'],
		['name' => 'visit_api#link', 'url' => '/api/visits/{id}/link', 'verb' => 'GET'],
		['name' => 'visit_api#resend', 'url' => '/api/visits/{id}/resend', 'verb' => 'POST'],
		// Note: never name a URL parameter "action" – Nextcloud's router removes it before calling the controller.
		['name' => 'visit_api#perform', 'url' => '/api/visits/{id}/{operation}', 'verb' => 'POST',
			'requirements' => ['operation' => 'approve|decline|check-in|check-out|cancel']],
		['name' => 'visit_api#reception', 'url' => '/api/reception', 'verb' => 'GET'],
		['name' => 'visit_api#users', 'url' => '/api/users', 'verb' => 'GET'],

		// Guest pass (public, opened from the invitation email)
		['name' => 'pass#show', 'url' => '/pass/{token}', 'verb' => 'GET'],

		// Reception tablet (public, protected by the secret kiosk key in the URL)
		['name' => 'kiosk#show', 'url' => '/kiosk/{key}', 'verb' => 'GET'],
		['name' => 'kiosk#check_in', 'url' => '/kiosk/{key}/check-in', 'verb' => 'POST'],
		['name' => 'kiosk#check_out', 'url' => '/kiosk/{key}/check-out', 'verb' => 'POST'],
		['name' => 'kiosk#hosts', 'url' => '/kiosk/{key}/hosts', 'verb' => 'GET'],
		['name' => 'kiosk#walk_in', 'url' => '/kiosk/{key}/walk-in', 'verb' => 'POST'],

		// Room display for screens in front of the meeting rooms (public, secret display key)
		['name' => 'display#show', 'url' => '/display/{key}', 'verb' => 'GET'],
		['name' => 'display#data', 'url' => '/display/{key}/data', 'verb' => 'GET'],

		// Logo for the tablet and guest pass (public)
		['name' => 'logo#show', 'url' => '/logo', 'verb' => 'GET'],

		// Admin settings
		['name' => 'settings_api#save', 'url' => '/admin/settings', 'verb' => 'POST'],
		['name' => 'settings_api#new_kiosk_link', 'url' => '/admin/kiosk-link', 'verb' => 'POST'],
		['name' => 'settings_api#test_talk', 'url' => '/admin/test-talk', 'verb' => 'POST'],
		['name' => 'settings_api#test_reception', 'url' => '/admin/test-reception', 'verb' => 'POST'],
		['name' => 'settings_api#new_display_link', 'url' => '/admin/display-link', 'verb' => 'POST'],
		['name' => 'settings_api#upload_logo', 'url' => '/admin/logo', 'verb' => 'POST'],
		['name' => 'settings_api#remove_logo', 'url' => '/admin/logo', 'verb' => 'DELETE'],
	],
];
