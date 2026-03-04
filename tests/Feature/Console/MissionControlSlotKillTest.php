<?php

namespace Tests\Feature\Console;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class MissionControlSlotKillTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_returns_failure_when_slot_does_not_exist(): void
    {
        $this->artisan('app:slotkill', ['slot' => 999, '--y' => true])
            ->expectsOutput('Slot not found: 999')
            ->assertExitCode(1);
    }

    public function test_command_returns_success_when_no_active_lease_exists(): void
    {
        $slot = $this->create_slot(1);

        $this->artisan('app:slotkill', ['slot' => $slot->id, '--y' => true])
            ->expectsOutput('No active lease found.')
            ->assertSuccessful();
    }

    public function test_command_terminates_active_lease_in_slot(): void
    {
        $slot = $this->create_slot(2);

        $lease = VmLease::query()->create([
            'id' => 'lease-slotkill',
            'vm_slot_id' => $slot->id,
            'token_hash' => 'hash-slotkill',
            'pid' => null,
            'overlay_path' => null,
            'started_at' => Carbon::now()->subMinute(),
            'last_activity_at' => Carbon::now()->subSecond(),
            'hard_deadline_at' => Carbon::now()->addMinute(),
            'idle_deadline_at' => Carbon::now()->addMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $slot->in_use = true;
        $slot->current_lease_id = $lease->id;
        $slot->save();

        $this->artisan('app:slotkill', [
            'slot' => $slot->id,
            '--y' => true,
            '--reason' => 'manual',
        ])->assertSuccessful();

        $lease->refresh();
        $slot->refresh();

        $this->assertNotNull($lease->ended_at);
        $this->assertSame('manual', $lease->end_reason);

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
