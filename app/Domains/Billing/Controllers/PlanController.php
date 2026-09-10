<?php

namespace App\Domains\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(array_values(config('plans')));
    }
}
