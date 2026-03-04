<?php

namespace Tests\Feature\Traits;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class TerminatesSessionsCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_slotkill_deletes_overlay_file_and_marks_lease_ended(): void
    {
        $slot = VmSlot::query()->create([
            'slot_index' => 1,
            'display' => 1,
            'ws_port' => 5701,
            'bind_host' => '127.0.0.1',
            'in_use' => true,
            'current_lease_id' => null,
        ]);

        $overlay_path = tempnam(sys_get_temp_dir(), 'mc-overlay-');
        $this->assertIsString($overlay_path);
        file_put_contents($overlay_path, 'x');
        $this->assertFileExists($overlay_path);

        $lease = VmLease::query()->create([
            'id' => 'lease-overlay',
            'vm_slot_id' => $slot->id,
            'token_hash' => 'hash-overlay',
            'pid' => null,
            'overlay_path' => $overlay_path,
            'started_at' => Carbon::now()->subMinute(),
            'last_activity_at' => Carbon::now()->subSecond(),
            'hard_deadline_at' => Carbon::now()->addMinute(),
            'idle_deadline_at' => Carbon::now()->addMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

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

        $this->assertFileDoesNotExist($overlay_path);
    }

    public function test_reap_marks_process_missing_for_nonexistent_pid_without_overlay(): void
    {
        $slot = VmSlot::query()->create([
            'slot_index' => 2,
            'display' => 2,
            'ws_port' => 5702,
            'bind_host' => '127.0.0.1',
            'in_use' => true,
            'current_lease_id' => null,
        ]);

        $lease = VmLease::query()->create([
            'id' => 'lease-missing-pid',
            'vm_slot_id' => $slot->id,
            'token_hash' => 'hash-missing-pid-2',
            'pid' => 999999,
            'overlay_path' => null,
            'started_at' => Carbon::now()->subMinutes(5),
            'last_activity_at' => Carbon::now()->subMinute(),
            'hard_deadline_at' => Carbon::now()->addMinute(),
            'idle_deadline_at' => Carbon::now()->addMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

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
}
