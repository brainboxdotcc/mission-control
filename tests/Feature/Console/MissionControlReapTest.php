<?php

namespace Tests\Feature\Console;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class MissionControlReapTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_expires_lease_past_hard_deadline(): void
    {
        $slot = $this->create_slot(1);

        $lease = VmLease::query()->create([
            'id' => 'lease-hard-expired',
            'vm_slot_id' => $slot->id,
            'token_hash' => 'hash-hard-expired',
            'pid' => null,
            'overlay_path' => null,
            'started_at' => Carbon::now()->subMinutes(10),
            'last_activity_at' => Carbon::now()->subMinutes(10),
            'hard_deadline_at' => Carbon::now()->subMinute(),
            'idle_deadline_at' => Carbon::now()->addMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $slot->in_use = true;
        $slot->current_lease_id = $lease->id;
        $slot->save();

        $this->artisan('app:reap')
            ->assertSuccessful();

        $lease->refresh();
        $slot->refresh();

        $this->assertNotNull($lease->ended_at);
        $this->assertSame('expired', $lease->end_reason);

        $this->assertFalse($slot->in_use);
        $this->assertNull($slot->current_lease_id);
    }

    public function test_command_expires_lease_past_idle_deadline(): void
    {
        $slot = $this->create_slot(2);

        $lease = VmLease::query()->create([
            'id' => 'lease-idle-expired',
            'vm_slot_id' => $slot->id,
            'token_hash' => 'hash-idle-expired',
            'pid' => null,
            'overlay_path' => null,
            'started_at' => Carbon::now()->subMinutes(10),
            'last_activity_at' => Carbon::now()->subMinutes(10),
            'hard_deadline_at' => Carbon::now()->addMinute(),
            'idle_deadline_at' => Carbon::now()->subMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $slot->in_use = true;
        $slot->current_lease_id = $lease->id;
        $slot->save();

        $this->artisan('app:reap')
            ->assertSuccessful();

        $lease->refresh();
        $slot->refresh();

        $this->assertNotNull($lease->ended_at);
        $this->assertSame('expired', $lease->end_reason);

        $this->assertFalse($slot->in_use);
        $this->assertNull($slot->current_lease_id);
    }

    public function test_command_marks_lease_process_missing_when_pid_does_not_exist(): void
    {
        $slot = $this->create_slot(3);

        $lease = VmLease::query()->create([
            'id' => 'lease-missing-pid',
            'vm_slot_id' => $slot->id,
            'token_hash' => 'hash-missing-pid',
            'pid' => 999999,
            'overlay_path' => null,
            'started_at' => Carbon::now()->subMinutes(5),
            'last_activity_at' => Carbon::now()->subMinute(),
            'hard_deadline_at' => Carbon::now()->addMinute(),
            'idle_deadline_at' => Carbon::now()->addMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $slot->in_use = true;
        $slot->current_lease_id = $lease->id;
        $slot->save();

        $this->artisan('app:reap')
            ->assertSuccessful();

        $lease->refresh();
        $slot->refresh();

        $this->assertNotNull($lease->ended_at);
        $this->assertSame('process_missing', $lease->end_reason);

        $this->assertFalse($slot->in_use);
        $this->assertNull($slot->current_lease_id);
    }

    private function create_slot(int $slot_index): VmSlot
    {
        return VmSlot::query()->create([
            'slot_index' => $slot_index,
            'display' => $slot_index,
            'ws_port' => 5700 + $slot_index,
            'bind_host' => '127.0.0.1',
            'in_use' => false,
            'current_lease_id' => null,
        ]);
    }
}
