<?php

namespace App\Domains\Generations\Jobs;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Gateway\AiGateway;
use App\Domains\Generations\Models\Generation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public function __construct(public readonly int $generationId) {}

    public function handle(AiGateway $gateway): void
    {
        $generation = Generation::query()->with('creativeProject')->findOrFail($this->generationId);
        $attempt = $generation->jobs()->create(['queue_job_id' => $this->job?->getJobId(), 'attempt' => $this->attempts(), 'status' => 'processing', 'started_at' => now()]);
        $startedAt = hrtime(true);
        $generation->update(['status' => 'processing', 'error_message' => null]);

        try {
            $project = $generation->creativeProject;
            $response = $gateway->generate(new GenerationInput($generation->type, $project->prompt, $generation->metadata['aspect_ratio'], $project->video_duration_seconds));
            $result = $response['result'];
            $path = "generations/{$generation->user_id}/{$generation->id}.{$result->extension}";
            Storage::disk(config('ai.output_disk'))->put($path, $result->contents);
            $media = $generation->user->mediaAssets()->create(['disk' => config('ai.output_disk'), 'path' => $path, 'mime' => $result->mime, 'size' => strlen($result->contents)]);
            $elapsed = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            $generation->usageLogs()->create(['provider' => $response['provider'], 'model' => $result->model, 'input_tokens' => $result->inputTokens, 'output_tokens' => $result->outputTokens, 'cost_usd' => $result->costUsd, 'metadata' => $result->metadata]);
            $generation->update(['status' => 'completed', 'provider' => $response['provider'], 'model' => $result->model, 'cost_usd' => $result->costUsd, 'processing_time_ms' => $elapsed, 'output_media_id' => $media->id]);
            $attempt->update(['status' => 'completed', 'finished_at' => now()]);
        } catch (Throwable $exception) {
            $attempt->update(['status' => 'failed', 'error_message' => $exception->getMessage(), 'finished_at' => now()]);
            $generation->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
