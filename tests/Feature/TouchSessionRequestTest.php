<?php

namespace Tests\Feature\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TouchSessionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function testLeaseIdIsRequired(): void
    {
        $this->postJson(route('api.session.touch'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lease_id']);
    }

    public function testLeaseIdMustBeUuid(): void
    {
        $this->postJson(route('api.session.touch'), [
            'lease_id' => 'not-a-uuid',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lease_id']);
    }

    public function testModeMustBeValid(): void
    {
        $this->postJson(route('api.session.touch'), [
            'lease_id' => (string) Str::uuid(),
            'mode' => 'invalid',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mode']);
    }

    public function testModeInputPassesValidation(): void
    {
        $response = $this->postJson(route('api.session.touch'), [
            'lease_id' => (string) Str::uuid(),
            'mode' => 'input',
        ]);

        $this->assertNotSame(422, $response->getStatusCode());
    }

    public function testModeHeartbeatPassesValidation(): void
    {
        $response = $this->postJson(route('api.session.touch'), [
            'lease_id' => (string) Str::uuid(),
            'mode' => 'heartbeat',
        ]);

        $this->assertNotSame(422, $response->getStatusCode());
    }

    public function testModeCanBeOmitted(): void
    {
        $response = $this->postJson(route('api.session.touch'), [
            'lease_id' => (string) Str::uuid(),
        ]);

        $this->assertNotSame(422, $response->getStatusCode());
    }
}
