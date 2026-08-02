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
		'firebase' => [
			'project_id' => env('FIREBASE_PROJECT_ID'),
			'credentials' => env('FIREBASE_CREDENTIALS', 'storage/app/private/firebase/service-account.json'),
			'timeout' => (int) env('FIREBASE_HTTP_TIMEOUT', 10),
		],
	],
];
