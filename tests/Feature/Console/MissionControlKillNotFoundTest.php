<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MissionControlKillNotFoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_returns_failure_when_lease_does_not_exist(): void
    {
        $this->artisan('app:kill', ['leaseId' => 999, '--y' => true])
            ->expectsOutput('Lease not found: 999')
            ->assertExitCode(1);
    }
}
