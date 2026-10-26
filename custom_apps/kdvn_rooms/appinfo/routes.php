<?php

declare(strict_types=1);

// "api#create_building" calls ApiController::createBuilding()
return [
	'routes' => [
		['name' => 'api#data', 'url' => '/api/data', 'verb' => 'GET'],

		['name' => 'api#create_building', 'url' => '/api/buildings', 'verb' => 'POST'],
		['name' => 'api#update_building', 'url' => '/api/buildings/{id}', 'verb' => 'PUT'],
		['name' => 'api#delete_building', 'url' => '/api/buildings/{id}', 'verb' => 'DELETE'],

		['name' => 'api#create_floor', 'url' => '/api/floors', 'verb' => 'POST'],
		['name' => 'api#update_floor', 'url' => '/api/floors/{id}', 'verb' => 'PUT'],
		['name' => 'api#delete_floor', 'url' => '/api/floors/{id}', 'verb' => 'DELETE'],

		['name' => 'api#create_room', 'url' => '/api/rooms', 'verb' => 'POST'],
		['name' => 'api#update_room', 'url' => '/api/rooms/{id}', 'verb' => 'PUT'],
		['name' => 'api#delete_room', 'url' => '/api/rooms/{id}', 'verb' => 'DELETE'],
	],
];
