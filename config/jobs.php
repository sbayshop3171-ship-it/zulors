<?php

return [
	'document_links' => [
		'posting_rules' => 'documents/job-board-rules.pdf',
		'jobs_board_guide' => 'documents/job-board-guide.pdf'
	],
	'job' => [
		'validation' => [
			'title' => [
				'min' => 12,
				'max' => 120
			],
			'desc' => [
				'min' => 120,
				'max' => 10000
			],
			'overview' => [
				'min' => 120,
				'max' => 2000
			],
			'address' => [
				'max' => 120
			],
			'image' => [
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
				'max' => '2048', // 2MB
				'image_width' => 900,
				'image_height' => 1200
			]
		]
	]
];
