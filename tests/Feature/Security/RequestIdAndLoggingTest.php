<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Str;
use Tests\TestCase;

class RequestIdAndLoggingTest extends TestCase
{
    public function test_api_responses_contain_request_id_header(): void
    {
        $response = $this->getJson('/api/plans');

        $response->assertOk();
        $this->assertTrue($response->headers->has('X-Request-ID'));
        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-ID')));
    }

    public function test_custom_valid_request_id_uuid_is_preserved(): void
    {
        $customUuid = (string) Str::uuid();

        $response = $this->withHeaders([
            'X-Request-ID' => $customUuid,
        ])->getJson('/api/plans');

        $response->assertOk();
        $this->assertSame($customUuid, $response->headers->get('X-Request-ID'));
    }

    public function test_invalid_non_uuid_request_id_is_replaced_with_fresh_uuid(): void
    {
        $invalidId = 'malicious-or-invalid-id<script>';

        $response = $this->withHeaders([
            'X-Request-ID' => $invalidId,
        ])->getJson('/api/plans');

        $response->assertOk();
        $this->assertNotSame($invalidId, $response->headers->get('X-Request-ID'));
        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-ID')));
    }
}
