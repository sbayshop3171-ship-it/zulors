<?php

return [
	'name' => env('ADS_NAME', 'ZulorsAds'),
	'document_links' => [
		'advertising_guide' => 'documents/advertising-guide.pdf',
		'advertising_rules' => 'documents/advertising-rules.pdf'
	],
	'ad' => [
		'validation' => [
			'title' => [
				'min' => 12,
				'max' => 120
			],
			'content' => [
				'min' => 62,
				'max' => 2000
			],
			'cta_text' => [
				'min' => 4,
				'max' => 32
			],
			'total_budget' => [
				'min' => env('ADS_MIN_BUDGET', 5),
				'max' => env('ADS_MAX_BUDGET', 10000)
			],
			'target_url' => [
				'max' => 320,
			],
			'creative' => [
				'mimes' => join(',', [
					'jpeg',
					'png',
					'jpg',
					'webp',
					'gif'
				]),
				'mimetypes' => join(',', [
					'image/jpeg',
					'image/webp',
					'image/png',
					'image/jpg',
					'image/gif'
				]),
				'max' => '2048' // 2MB
			],
			'video' => [
				'mimes' => join(',', [
					'mp4',
					'webm',
					'mov'
				]),
				'mimetypes' => join(',', [
					'video/mp4',
					'video/webm',
					'video/quicktime'
				]),
				'max' => env('ADS_VIDEO_MAX_KB', 51200),
				'max_duration_seconds' => env('ADS_VIDEO_MAX_DURATION_SECONDS', 30)
			]
		],
		'default_preview' => 'assets/ads/default-preview.png',
		'image_width' => 800,
		'image_height' => 500
	],
	'default_approval' => env('ADS_DEFAULT_APPROVAL', true),
	'price_per_view' => env('ADS_PRICE_PER_VIEW', 0.01),
	'price_per_view_limits' => [
		'min' => env('ADS_MIN_PRICE_PER_VIEW', 0.01),
		'max' => env('ADS_MAX_PRICE_PER_VIEW', 1)
	],

	// Add refresh interval in minutes.
	'refresh_interval' => env('ADS_AD_REFRESH_INTERVAL', 30),

	// Add charge interval in minutes.
	'charge_interval' => env('ADS_CHARGE_INTERVAL', 10),

	'targeting' => [
		'frequency_cap' => env('ADS_FREQUENCY_CAP', 3),
		'topics_limit' => env('ADS_TARGET_TOPICS_LIMIT', 10),
		'topics_max_length' => env('ADS_TARGET_TOPICS_MAX_LENGTH', 320)
	],
	'personalization' => [
		'enabled' => env('ADS_PERSONALIZATION_ENABLED', true),
		'ranking_enabled' => env('ADS_RANKING_ENABLED', false),
		'holdout_percent' => env('ADS_RANKING_HOLDOUT_PERCENT', 10),
		'daily_viewable_cap' => env('ADS_DAILY_VIEWABLE_CAP', 2),
		'weekly_viewable_cap' => env('ADS_WEEKLY_VIEWABLE_CAP', 4),
		'no_action_cooldown_days' => env('ADS_NO_ACTION_COOLDOWN_DAYS', 7),
		'post_click_cooldown_hours' => env('ADS_POST_CLICK_COOLDOWN_HOURS', 24),
		'post_click_reminder_cap' => env('ADS_POST_CLICK_REMINDER_CAP', 2),
		'event_retention_days' => env('ADS_EVENT_RETENTION_DAYS', 90),
		'consent_version' => '2026-10-v1',
	]
];
