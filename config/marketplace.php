<?php

return [
	'document_links' => [
		'trade_rules' => 'documents/trading-rules.pdf',
		'trade_guide' => 'documents/trading-guide.pdf'
	],
	'product' => [
		'validation' => [
			'title' => [
				'min' => 12,
				'max' => 120
			],
			'desc' => [
				'min' => 120,
				'max' => 10000
			],
			'stock_quantity' => [
				'min' => 0,
				'max' => 1000
			],
			'price' => [
				'min' => 0.10,
				'max' => 100000
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
				'max' => '2048' // 2MB
			]
		],
		'default_preview' => 'assets/products/default-preview.png',
		'image_width' => 1080,
		'image_height' => 1080
	]
];
