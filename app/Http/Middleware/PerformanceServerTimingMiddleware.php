<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PerformanceServerTimingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = $next($request);

        if (! $request->is('api/performance/events')) {
            $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 3);
            $existing = trim((string) $response->headers->get('Server-Timing', ''));
            $timing = 'app;dur='.$durationMs;

            $response->headers->set(
                'Server-Timing',
                $existing === '' ? $timing : $existing.', '.$timing
            );
        }

        return $response;
    }
}
