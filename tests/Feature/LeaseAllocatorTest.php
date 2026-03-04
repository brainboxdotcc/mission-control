<?php

namespace Tests\Feature;

use App\Models\VmSlot;
use App\Services\LeaseAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LeaseAllocatorTest extends TestCase
{
    use RefreshDatabase;

    public function testAllocateClaimsSlotAndCreatesLease(): void
    {
        config()->set('mission-control.limits.hard_seconds', 60);
        config()->set('mission-control.limits.idle_seconds', 10);

        $slot = VmSlot::query()->create([
            'slot_index' => 1,
            'in_use' => false,
            'current_lease_id' => null,
            'bind_host' => '127.0.0.1',
            'display' => 1,
            'ws_port' => 5701,
        ]);

        /** @var LeaseAllocator $allocator */
        $allocator = $this->app->make(LeaseAllocator::class);

        $result = $allocator->allocate();

        $this->assertSame($slot->id, $result['slot']->id);
        $this->assertSame(64, strlen($result['token']));

        $lease = $result['lease'];
        $this->assertSame($slot->id, $lease->vm_slot_id);

        $slot->refresh();
        $this->assertTrue((bool) $slot->in_use);
        $this->assertSame($lease->id, $slot->current_lease_id);
    }
}
