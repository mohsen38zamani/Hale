<?php

namespace App\Http\Controllers;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    use ApiResponse;

    public function check(): JsonResponse
    {
        $services = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ];

        $allHealthy = collect($services)->every(fn (array $service) => $service['status'] === 'up');

        $payload = [
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'services' => $services,
        ];

        return response()->json([
            'success' => $allHealthy,
            'data' => $payload,
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'up', 'message' => 'Database connection established'];
        } catch (Throwable $e) {
            return ['status' => 'down', 'message' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_check_ping';
            Cache::put($key, 'pong', 5);
            $value = Cache::get($key);

            return $value === 'pong'
                ? ['status' => 'up', 'message' => 'Cache read/write operational']
                : ['status' => 'down', 'message' => 'Cache returned unexpected value'];
        } catch (Throwable $e) {
            return ['status' => 'down', 'message' => $e->getMessage()];
        }
    }
}
