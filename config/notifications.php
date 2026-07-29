<?php

return [
	'sounds' => [
		'notification_received' => 'assets/sounds/notifications/notification-received.mp3',
		'ui_feedback' => 'assets/sounds/notifications/ui-feedback.mp3'
	],
	'email' => [
		'enabled' => env('NOTIFICATIONS_EMAIL_ENABLED', false),
	],
	'broadcast' => [
		'enabled' => env('NOTIFICATIONS_BROADCAST_ENABLED', false),
	],
	'push' => [
		'enabled' => env('NOTIFICATIONS_PUSH_ENABLED', false),
	],
];
