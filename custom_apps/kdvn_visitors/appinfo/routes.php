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

		// Interactive (touch) room display: same data, plus details and quick booking (separate secret key)
		['name' => 'touch#show', 'url' => '/touch/{key}', 'verb' => 'GET'],
		['name' => 'touch#data', 'url' => '/touch/{key}/data', 'verb' => 'GET'],
		['name' => 'touch#face', 'url' => '/touch/{key}/face', 'verb' => 'POST'],
		['name' => 'touch#claim', 'url' => '/touch/{key}/claim', 'verb' => 'POST'],
		['name' => 'touch#claim_status', 'url' => '/touch/{key}/claim/{nonce}', 'verb' => 'GET'],
		['name' => 'touch#book', 'url' => '/touch/{key}/book', 'verb' => 'POST'],

		// Phone side of the touch screen's QR code (needs a Nextcloud login)
		['name' => 'confirm#show', 'url' => '/confirm/{nonce}', 'verb' => 'GET'],
		['name' => 'confirm#confirm', 'url' => '/confirm/{nonce}', 'verb' => 'POST'],

		// Logo for the tablet and guest pass (public)
		['name' => 'logo#show', 'url' => '/logo', 'verb' => 'GET'],

		// Admin settings
		['name' => 'settings_api#save', 'url' => '/admin/settings', 'verb' => 'POST'],
		['name' => 'settings_api#new_kiosk_link', 'url' => '/admin/kiosk-link', 'verb' => 'POST'],
		['name' => 'settings_api#test_talk', 'url' => '/admin/test-talk', 'verb' => 'POST'],
		['name' => 'settings_api#test_reception', 'url' => '/admin/test-reception', 'verb' => 'POST'],
		['name' => 'settings_api#new_display_link', 'url' => '/admin/display-link', 'verb' => 'POST'],
		['name' => 'settings_api#new_touch_link', 'url' => '/admin/touch-link', 'verb' => 'POST'],
		['name' => 'settings_api#save_face', 'url' => '/admin/face-settings', 'verb' => 'POST'],
		['name' => 'settings_api#test_face', 'url' => '/admin/test-face', 'verb' => 'POST'],
		['name' => 'settings_api#upload_logo', 'url' => '/admin/logo', 'verb' => 'POST'],
		['name' => 'settings_api#remove_logo', 'url' => '/admin/logo', 'verb' => 'DELETE'],
	],
];
