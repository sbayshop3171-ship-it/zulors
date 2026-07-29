<?php

return [
    'email' => env('ADMIN_EMAIL', null),
	'notifications' => [
		'user_banned' => true
	],
	'admin_locale' => env('ADMIN_LOCALE', 'en')
];

