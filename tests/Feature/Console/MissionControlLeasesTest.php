<?php

namespace Tests\Feature\Console;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class MissionControlLeasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_lists_active_leases_by_default(): void
    {
        $slot1 = VmSlot::query()->create([
            'slot_index' => 1,
            'display' => 1,
            'ws_port' => 5701,
            'bind_host' => '127.0.0.1',
            'in_use' => false,
            'current_lease_id' => null,
        ]);

        $slot2 = VmSlot::query()->create([
            'slot_index' => 2,
            'display' => 2,
            'ws_port' => 5702,
            'bind_host' => '127.0.0.1',
            'in_use' => false,
            'current_lease_id' => null,
        ]);

        VmLease::query()->create([
            'id' => 'lease1',
            'vm_slot_id' => $slot1->id,
            'token_hash' => 'hash-lease1',
            'hard_deadline_at' => Carbon::now()->addMinute(),
            'idle_deadline_at' => Carbon::now()->addMinute(),
            'ended_at' => null,
        ]);

        VmLease::query()->create([
            'id' => 'lease2',
            'vm_slot_id' => $slot2->id,
            'token_hash' => 'hash-lease2',
            'hard_deadline_at' => Carbon::now(),
            'idle_deadline_at' => Carbon::now(),
            'ended_at' => Carbon::now(),
        ]);

        $this->artisan('app:leases')
            ->assertSuccessful();
    }

    public function test_command_returns_json_when_json_option_is_used(): void
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
            'id' => 'lease1',
            'vm_slot_id' => $slot->id,
            'token_hash' => 'hash-lease1',
            'hard_deadline_at' => Carbon::now()->addMinute(),
            'idle_deadline_at' => Carbon::now()->addMinute(),
            'ended_at' => null,
        ]);

        $this->artisan('app:leases', ['--json' => true])
            ->assertSuccessful();
    }
}
