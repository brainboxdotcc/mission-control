<?php

namespace Tests\Feature\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReleaseSessionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function testLeaseIdIsRequired(): void
    {
        $this->postJson(route('api.session.release'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lease_id']);
    }

    public function testLeaseIdMustBeUuid(): void
    {
        $this->postJson(route('api.session.release'), [
            'lease_id' => 'invalid',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lease_id']);
    }

    public function testValidLeaseIdPassesValidation(): void
    {
        $response = $this->postJson(route('api.session.release'), [
            'lease_id' => (string) Str::uuid(),
        ]);

        $this->assertNotSame(422, $response->getStatusCode());
    }
}
