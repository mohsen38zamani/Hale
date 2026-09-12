<?php

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'error' => null], $status);
    }

    protected function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'data' => null, 'error' => ['code' => $code, 'message' => $message]], $status);
    }

    protected function failure(string $code, string $message, int $status): JsonResponse
    {
        return $this->error($code, $message, $status);
    }
}
