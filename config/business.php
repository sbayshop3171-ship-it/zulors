<?php

return [
	'links' => [
		//Replace with your own link to business account guide.
		
		'business_account_guide' => 'https://en.wikipedia.org/wiki/Accounting',
	],
	'validation' => [
		'name' => [
			'max' => 32
		],
		'description' => [
			'min' => 2,
			'max' => 255
		],
		'website' => [
			'max' => 255
		],
		'city' => [
			'max' => 32
		],
		'state' => [
			'max' => 32
		],
		'postal_code' => [
			'max' => 32
		],
		'tax_number' => [
			'max' => 120
		],
		'billing_address' => [
			'max' => 255	
		],
		'address_line1' => [
			'max' => 255
		],
		'address_line2' => [
			'max' => 255	
		]
	]
];