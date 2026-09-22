<?php

namespace Tests\Feature\Generations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class QueueJobResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_failing_event_logs_critical_alert(): void
    {
        Log::shouldReceive('critical')
            ->once()
            ->with('Queue job failed permanently', \Mockery::on(function (array $context): bool {
                return $context['connection'] === 'sync'
                    && $context['queue'] === 'generations'
                    && str_contains($context['exception'], 'AI model timeout simulation');
            }));

        $syncJob = new class($this->app, '{"job":"DummyHandler"}', 'sync', 'generations') extends SyncJob
        {
            public function resolveName(): string
            {
                return 'App\\Domains\\Generations\\Jobs\\ProcessGeneration';
            }

            public function getQueue(): string
            {
                return 'generations';
            }
        };

        $event = new JobFailed('sync', $syncJob, new RuntimeException('AI model timeout simulation'));

        event($event);
    }
}
