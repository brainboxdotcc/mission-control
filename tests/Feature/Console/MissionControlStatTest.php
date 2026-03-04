<?php

namespace Tests\Feature\Console;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class MissionControlStatTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_status_summary(): void
    {
        $slot = VmSlot::query()->create([
            'slot_index' => 1,
            'display' => 1,
            'ws_port' => 5701,
            'bind_host' => '127.0.0.1',
            'in_use' => true,
            'current_lease_id' => null,
        ]);

        VmLease::query()->create([
            'id' => 'lease1',
            'vm_slot_id' => $slot->id,
            'token_hash' => 'x',
            'hard_deadline_at' => Carbon::now()->addMinute(),
            'idle_deadline_at' => Carbon::now()->addMinute(),
            'ended_at' => null,
        ]);

        $slot->current_lease_id = 'lease1';
        $slot->save();

        $this->artisan('app:stat')
            ->expectsOutput('Mission Control status')
            ->assertSuccessful();
    }

    public function test_command_outputs_json_when_requested(): void
    {
        VmSlot::query()->create([
            'slot_index' => 1,
            'display' => 1,
            'ws_port' => 5701,
            'bind_host' => '127.0.0.1',
            'in_use' => false,
            'current_lease_id' => null,
        ]);

        $this->artisan('app:stat', ['--json' => true])
            ->assertSuccessful();
    }
}
