<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertStatus(200);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/create')->assertStatus(200);
        $this->get('/pricing')->assertStatus(200);
        $this->get('/pricing?payment=paid')->assertStatus(200);
        $this->get('/pricing?payment=failed')->assertStatus(200);
        $this->get('/terms')->assertStatus(200);
        $this->get('/privacy')->assertStatus(200);
    }
}
