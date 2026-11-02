<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PerformanceTelemetryTest extends TestCase
{
    public function test_coarse_device_and_network_context_is_accepted_and_logged(): void
    {
        $logger = \Mockery::mock();
        $logger->shouldReceive('info')
            ->once()
            ->with('client_performance_batch', \Mockery::on(function (array $context): bool {
                $event = $context['events'][0] ?? [];

                return ($event['metric'] ?? null) === 'api_duration'
                    && ($event['android_api'] ?? null) === 31
                    && ($event['low_ram'] ?? null) === true
                    && ($event['device_memory'] ?? null) === '2'
                    && ($event['network'] ?? null) === '3g'
                    && ($event['server_ms'] ?? null) === 286.4;
            }));

        Log::shouldReceive('channel')->once()->with('performance')->andReturn($logger);

        $this->postJson('/api/performance/events', [
            'events' => [[
                'metric' => 'api_duration',
                'value' => 487.25,
                'variant' => 'mobile',
                'route' => 'home_index',
                'category' => 'timeline',
                'android_api' => 31,
                'low_ram' => true,
                'device_memory' => '2',
                'network' => '3g',
                'server_ms' => 286.4,
            ]],
        ])->assertNoContent();
    }

    public function test_unsupported_device_api_is_rejected(): void
    {
        $this->postJson('/api/performance/events', [
            'events' => [[
                'metric' => 'inp',
                'value' => 150,
                'variant' => 'mobile',
                'android_api' => 23,
            ]],
        ])->assertUnprocessable();
    }
}
