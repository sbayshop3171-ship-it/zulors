<?php

namespace App\Http\Controllers\Api\Performance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PerformanceTelemetryController extends Controller
{
    private const METRICS = [
        'lcp',
        'cls',
        'inp',
        'route',
        'api_slow',
        'startup_shell',
        'startup_visual',
        'first_input',
        'media_first_frame',
        'media_play_request',
        'media_stall',
        'media_recovery',
        'media_fallback',
        'media_terminal_error',
        'media_rendition',
    ];

    private const CATEGORIES = [
        'bootstrap',
        'timeline',
        'explore',
        'profile',
        'messenger',
        'notifications',
        'stories',
        'media-publications',
        'uploads',
        'downloads',
        'api',
    ];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:20'],
            'events.*' => ['required', 'array'],
            'events.*.metric' => ['required', 'string', 'in:'.implode(',', self::METRICS)],
            'events.*.value' => ['required', 'numeric', 'min:0', 'max:600000'],
            'events.*.variant' => ['nullable', 'string', 'in:mobile,desktop'],
            'events.*.route' => ['nullable', 'string', 'regex:/^[a-z0-9_]{1,60}$/'],
            'events.*.category' => ['nullable', 'string', 'in:'.implode(',', self::CATEGORIES)],
            'events.*.android_api' => ['nullable', 'integer', 'min:24', 'max:99'],
            'events.*.low_ram' => ['nullable', 'boolean'],
            'events.*.device_memory' => ['nullable', 'string', 'in:1,2,4,8p,unknown'],
            'events.*.network' => ['nullable', 'string', 'in:offline,slow-2g,2g,3g,4g,unknown'],
            'events.*.server_ms' => ['nullable', 'numeric', 'min:0', 'max:600000'],
            'events.*.rendition' => ['nullable', 'string', 'in:hls,mp4,direct,240p,360p,480p,720p'],
        ]);

        $events = array_map(static function (array $event): array {
            return array_filter([
                'metric' => $event['metric'],
                'value' => round((float) $event['value'], 3),
                'variant' => $event['variant'] ?? null,
                'route' => $event['route'] ?? null,
                'category' => $event['category'] ?? null,
                'android_api' => isset($event['android_api']) ? (int) $event['android_api'] : null,
                'low_ram' => isset($event['low_ram']) ? (bool) $event['low_ram'] : null,
                'device_memory' => $event['device_memory'] ?? null,
                'network' => $event['network'] ?? null,
                'server_ms' => isset($event['server_ms']) ? round((float) $event['server_ms'], 3) : null,
                'rendition' => $event['rendition'] ?? null,
            ], static fn ($value) => $value !== null);
        }, $validated['events']);

        Log::channel('performance')->info('client_performance_batch', [
            'events' => $events,
        ]);

        return response()->noContent();
    }
}
