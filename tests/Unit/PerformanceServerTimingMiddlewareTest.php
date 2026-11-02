<?php

namespace Tests\Unit;

use App\Http\Middleware\PerformanceServerTimingMiddleware;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class PerformanceServerTimingMiddlewareTest extends TestCase
{
    public function test_it_adds_total_server_time_and_preserves_controller_timings(): void
    {
        $request = Request::create('/api/timeline/feed');
        $response = new Response('ok');
        $response->headers->set('Server-Timing', 'timeline-feed;dur=25.5');

        $result = (new PerformanceServerTimingMiddleware())->handle(
            $request,
            static fn () => $response
        );

        $this->assertMatchesRegularExpression(
            '/^timeline-feed;dur=25\.5, app;dur=[\d.]+$/',
            (string) $result->headers->get('Server-Timing')
        );
    }

    public function test_it_does_not_instrument_its_own_telemetry_endpoint(): void
    {
        $request = Request::create('/api/performance/events', 'POST');
        $response = new Response('', Response::HTTP_NO_CONTENT);

        $result = (new PerformanceServerTimingMiddleware())->handle(
            $request,
            static fn () => $response
        );

        $this->assertFalse($result->headers->has('Server-Timing'));
    }
}
