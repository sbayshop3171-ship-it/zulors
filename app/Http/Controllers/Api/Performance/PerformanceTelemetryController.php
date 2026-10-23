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
        ]);

        $events = array_map(static function (array $event): array {
            return array_filter([
                'metric' => $event['metric'],
                'value' => round((float) $event['value'], 3),
                'variant' => $event['variant'] ?? null,
                'route' => $event['route'] ?? null,
                'category' => $event['category'] ?? null,
            ], static fn ($value) => $value !== null);
        }, $validated['events']);

        Log::channel('performance')->info('client_performance_batch', [
            'events' => $events,
        ]);

        return response()->noContent();
    }
}
