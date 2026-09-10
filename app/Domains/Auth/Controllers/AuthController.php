<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Auth\Requests\RegisterRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        return $this->success($this->tokenPayload($user, $request->string('device_name')->value() ?: 'pwa'), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();
        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return $this->error('INVALID_CREDENTIALS', 'ایمیل یا رمز عبور نادرست است.', 422);
        }

        return $this->success($this->tokenPayload($user, $request->string('device_name')->value() ?: 'pwa'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(['message' => 'با موفقیت خارج شدید.']);
    }

    private function tokenPayload(User $user, string $deviceName): array
    {
        return ['user' => $user->only(['id', 'name', 'email', 'credits_balance', 'plan_key']), 'token' => $user->createToken($deviceName)->plainTextToken];
    }
}
