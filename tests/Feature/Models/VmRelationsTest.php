<?php

namespace Tests\Feature\Models;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class VmRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vmslot_leases_relationship_returns_associated_leases(): void
    {
        $slot = VmSlot::query()->create([
            'slot_index' => 1,
            'display' => 1,
            'ws_port' => 5701,
            'bind_host' => '127.0.0.1',
            'in_use' => false,
            'current_lease_id' => null,
        ]);

        $lease1 = VmLease::query()->create([
            'id' => 'lease-rel-1',
            'vm_slot_id' => $slot->id,
            'token_hash' => 'hash-rel-1',
            'hard_deadline_at' => Carbon::now()->addMinute(),
            'idle_deadline_at' => Carbon::now()->addMinute(),
            'ended_at' => null,
        ]);

        $lease2 = VmLease::query()->create([
            'id' => 'lease-rel-2',
            'vm_slot_id' => $slot->id,
            'token_hash' => 'hash-rel-2',
            'hard_deadline_at' => Carbon::now()->addMinute(),
            'idle_deadline_at' => Carbon::now()->addMinute(),
            'ended_at' => null,
        ]);

        $slot->load('leases');

        $this->assertCount(2, $slot->leases);
        $this->assertTrue($slot->leases->contains($lease1));
        $this->assertTrue($slot->leases->contains($lease2));
    }

    public function test_vmlease_slot_relationship_returns_parent_slot(): void
    {
        $slot = VmSlot::query()->create([
            'slot_index' => 2,
            'display' => 2,
            'ws_port' => 5702,
            'bind_host' => '127.0.0.1',
            'in_use' => false,
            'current_lease_id' => null,
        ]);

        $lease = VmLease::query()->create([
            'id' => 'lease-rel-slot',
            'vm_slot_id' => $slot->id,
            'token_hash' => 'hash-rel-slot',
            'hard_deadline_at' => Carbon::now()->addMinute(),
            'idle_deadline_at' => Carbon::now()->addMinute(),
            'ended_at' => null,
        ]);

        $lease->load('slot');

        $this->assertNotNull($lease->slot);
        $this->assertSame($slot->id, $lease->slot->id);
    }

    public function test_vmlease_is_active_returns_true_only_when_not_ended(): void
    {
        $lease = new VmLease();
        $lease->ended_at = null;
        $this->assertTrue($lease->is_active());

        $lease->ended_at = Carbon::now();
        $this->assertFalse($lease->is_active());
    }
}
