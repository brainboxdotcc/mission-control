<?php

namespace Tests\Feature\Console;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class MissionControlKillTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_successfully_when_lease_is_already_ended(): void
    {
        $slot = VmSlot::query()->create([
            'slot_index' => 1,
            'display' => 1,
            'ws_port' => 5701,
            'bind_host' => '127.0.0.1',
            'in_use' => false,
            'current_lease_id' => null,
        ]);

        VmLease::query()->create([
            'id' => '1',
            'vm_slot_id' => $slot->id,
            'token_hash' => 'x',
            'hard_deadline_at' => Carbon::now(),
            'idle_deadline_at' => Carbon::now(),
            'ended_at' => Carbon::now(),
        ]);

        $this->artisan('app:kill', ['leaseId' => 1, '--y' => true])
            ->expectsOutput('Lease already ended: 1')
            ->assertSuccessful();
    }
}
