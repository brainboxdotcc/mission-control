<?php

namespace Tests\Feature\Console;

use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MissionControlSlotsSetTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_missing_slots_up_to_requested_count(): void
    {
        VmSlot::query()->create([
            'slot_index' => 1,
            'display' => 1,
            'ws_port' => 5701,
            'bind_host' => '127.0.0.1',
            'in_use' => false,
            'current_lease_id' => null,
        ]);

        $this->artisan('app:slots:set', ['count' => 3])
            ->expectsOutput('Ensured slots 1..3. Created 2 new slot(s).')
            ->assertSuccessful();

        $this->assertSame(3, VmSlot::query()->count());
    }

    public function test_command_refuses_to_reduce_existing_slot_count(): void
    {
        VmSlot::query()->create([
            'slot_index' => 1,
            'display' => 1,
            'ws_port' => 5701,
            'bind_host' => '127.0.0.1',
            'in_use' => false,
            'current_lease_id' => null,
        ]);

        VmSlot::query()->create([
            'slot_index' => 2,
            'display' => 2,
            'ws_port' => 5702,
            'bind_host' => '127.0.0.1',
            'in_use' => false,
            'current_lease_id' => null,
        ]);

        $this->artisan('app:slots:set', ['count' => 1])
            ->expectsOutput('Refusing to shrink slots from 2 to 1.')
            ->assertExitCode(1);
    }
}
