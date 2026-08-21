<?php

use Illuminate\Support\Facades\Route;

Route::get('/app', function () {
    $startedAt = microtime(true);
	$locale = request()->get('locale', 'en');
	$localePath = base_path("lang/{$locale}/api/index.php");
	$fallbackLocalePath = base_path('lang/en/api/index.php');
    $effectivePath = file_exists($localePath)
        ? $localePath
        : (file_exists($fallbackLocalePath) ? $fallbackLocalePath : null);
    $cacheHit = $effectivePath === $localePath && file_exists($localePath);
    $payload = $effectivePath ? require $effectivePath : [];
    $etag = $effectivePath ? '"' . md5_file($effectivePath) . '"' : '"empty-translations"';
    $lastModified = $effectivePath ? gmdate('D, d M Y H:i:s', filemtime($effectivePath)) . ' GMT' : null;
    $ifNoneMatch = trim((string) request()->header('If-None-Match'));
    $durationMs = round((microtime(true) - $startedAt) * 1000, 1);

    if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
        return response('', 304)->withHeaders(array_filter([
            'Cache-Control' => 'public, max-age=300, stale-while-revalidate=86400',
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
            'Server-Timing' => "translations;dur={$durationMs}",
            'X-Zulors-Translations-Cache' => $cacheHit ? 'hit' : 'fallback'
        ]));
    }

    return response()->json([
		'data' => $payload
	])->withHeaders(array_filter([
        'Cache-Control' => 'public, max-age=300, stale-while-revalidate=86400',
        'ETag' => $etag,
        'Last-Modified' => $lastModified,
        'Server-Timing' => "translations;dur={$durationMs}",
        'X-Zulors-Translations-Cache' => $cacheHit ? 'hit' : 'fallback'
    ]));
});
