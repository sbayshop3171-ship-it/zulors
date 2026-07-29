<?php
// ‼️‼️‼️
// This file will never be overwritten by the update.
// You can add your disks here. No .env variables needed.

return [
	// Default “public” disk.
	// ▸ Files live in storage/app/public on *your* server (they are not uploaded to the cloud).
	// ▸ Want a different disk?  Add it in the list below and, if you like, delete this entry.
	// ▸ If you do delete it, make sure at least one other disk is configured.
	// ▸ Make sure that disk name is unique. E.g 's3_one', 's3_two', etc. 
	// ▸ Add name and description to make it more readable on the admin panel.

	'public' => [
		'name' => 'Public disk',
		'description' => 'Public disk is the default disk for the application.',
		'driver' => 'local',
		'root' => storage_path('app/public'),
		'url' => env('APP_URL').'/storage',
		'visibility' => 'public',
		'round_robin' => env('PUBLIC_DISK_ROUND_ROBIN', true),
		'permissions' => [
			'file' => [
				'public' => 0644,
				'private' => 0600,
			],
			'dir' => [
				'public' => 0755,
				'private' => 0700,
			],
		],
		'throw' => false,
	],

	// Cloudflare R2 temporary disk.
	// ▸ Use this for direct browser/mobile uploads only.
	// ▸ Keep this bucket private and add an R2 lifecycle rule to delete objects after 1-3 days.
	// ▸ It is intentionally excluded from round-robin final media storage.
	'r2_temp' => [
		'name' => 'Cloudflare R2 temporary uploads',
		'description' => 'Private temporary bucket for raw uploads waiting for media processing.',
		'enabled' => env('R2_TEMP_ENABLED', false),
		'round_robin' => false,
		'driver' => 's3',
		'key' => env('R2_ACCESS_KEY_ID'),
		'secret' => env('R2_SECRET_ACCESS_KEY'),
		'region' => env('R2_REGION', 'auto'),
		'bucket' => env('R2_TEMP_BUCKET'),
		'endpoint' => env('R2_ENDPOINT'),
		'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', true),
		'throw' => false,
	],

	// Cloudflare R2 final media disk.
	// ▸ Use this for optimized files only.
	// ▸ Set R2_FINAL_ROUND_ROBIN=true if post images/videos should use this disk automatically.
	// ▸ Set R2_PUBLIC_URL to your cached custom domain, for example https://media.example.com.
	'r2_final' => [
		'name' => 'Cloudflare R2 final media',
		'description' => 'Optimized public media served through a Cloudflare cached custom domain.',
		'enabled' => env('R2_FINAL_ENABLED', false),
		'round_robin' => env('R2_FINAL_ROUND_ROBIN', false),
		'driver' => 's3',
		'key' => env('R2_ACCESS_KEY_ID'),
		'secret' => env('R2_SECRET_ACCESS_KEY'),
		'region' => env('R2_REGION', 'auto'),
		'bucket' => env('R2_FINAL_BUCKET'),
		'url' => env('R2_PUBLIC_URL'),
		'endpoint' => env('R2_ENDPOINT'),
		'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', true),
		'throw' => false,
	],
	
	// You can add here file system disks as much as you want.
	// But make sure that disk name is unique. E.g 's3_one', 's3_two', etc. 
    // 's3' => [
	// 	'name' => 'Disk name',
	// 	'driver' => 's3',
	// 	'key' => '',
	// 	'secret' => '',
	// 	'region' => '',
	// 	'bucket' => '',
	// 	'url' => '',
	// 	'endpoint' => '',
	// 	'use_path_style_endpoint' => false,
	// 	'throw' => false,
	// ],
];
