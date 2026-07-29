<?php

return [
    'log-viewer' => [
        'preview_image' => 'assets/tools/log-viewer.jpg',
		'title' => 'Log Viewer',
		'description' => 'View and manage error, info and other logs for your application in one place with a beautiful and human-friendly interface.',
		'route_path' => config('log-viewer.route_path'),
    ],
	'horizon' => [
		'preview_image' => 'assets/tools/horizon.jpg',
		'title' => 'Horizon',
		'description' => 'Easily monitor key metrics of your queue system such as job throughput, runtime, and job failures running on your application.',
		'route_path' => config('horizon.path'),
	],
	'nightwatch' => [
		'preview_image' => 'assets/tools/nightwatch.jpg',
		'title' => 'Nightwatch',
		'description' => 'Nightwatch Deeply monitors your application\'s performance, identify issues, and shows insights in a powerful dashboard.',
		'route_path' => 'https://nightwatch.laravel.com',
		'is_external' => true,
	],
];