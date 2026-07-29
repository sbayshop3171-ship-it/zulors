<?php

use Illuminate\Support\Facades\Route;

Route::get('/app', function () {
	$locale = request()->get('locale', 'en');
	$localePath = base_path("lang/{$locale}/api/index.php");
	$fallbackLocalePath = base_path('lang/en/api/index.php');

	if(! file_exists($localePath)) {
		if(file_exists($fallbackLocalePath)) {
			return response()->json([
				'data' => require $fallbackLocalePath
			]);
		}

		return response()->json([
			'data' => []
		]);
	}

    return response()->json([
		'data' => require $localePath,
	]);
});
