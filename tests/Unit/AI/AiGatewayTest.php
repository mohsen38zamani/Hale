<?php

namespace Tests\Unit\AI;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Gateway\AiGateway;
use Tests\TestCase;

class AiGatewayTest extends TestCase
{
    public function test_it_routes_generation_without_exposing_provider_to_business_logic(): void
    {
        $response = app(AiGateway::class)->generate(new GenerationInput('image', 'product photo', '1:1'));

        $this->assertSame('local', $response['provider']);
        $this->assertSame('local-preview-v1', $response['result']->model);
        $this->assertSame(0.0, $response['result']->costUsd);
    }

    public function test_fake_provider_returns_content_matching_its_declared_format(): void
    {
        $result = app(AiGateway::class)->generate(new GenerationInput('image', 'product photo', '1:1'))['result'];

        $this->assertSame('image/png', $result->mime);
        $this->assertSame('png', $result->extension);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $result->contents);
    }
}
