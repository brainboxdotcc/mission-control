<?php

namespace Tests\Feature\Console;

use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MissionControlStatNoActiveSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_no_active_slots_when_none_in_use(): void
    {
        VmSlot::query()->create([
            'slot_index' => 1,
            'display' => 1,
            'ws_port' => 5701,
            'bind_host' => '127.0.0.1',
            'in_use' => false,
            'current_lease_id' => null,
        ]);

        $this->artisan('app:stat')
            ->expectsOutput('Mission Control status')
            ->expectsOutput('----------------------')
            ->expectsOutput('No active slots.')
            ->assertSuccessful();
    }
}
