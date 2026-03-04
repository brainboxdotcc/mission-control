<?php

namespace Tests\Feature\Services;

use App\Models\VmSlot;
use App\Services\LeaseAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class LeaseAllocatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocator_allocates_lowest_free_slot_and_marks_it_in_use(): void
    {
        config()->set('mission-control.limits.hard_seconds', 120);
        config()->set('mission-control.limits.idle_seconds', 30);

        $slot1 = $this->create_slot(1);
        $this->create_slot(2);

        $result = app(LeaseAllocator::class)->allocate();

        $this->assertArrayHasKey('lease', $result);
        $this->assertArrayHasKey('slot', $result);
        $this->assertArrayHasKey('token', $result);

        $this->assertSame($slot1->id, $result['slot']->id);

        $slot1->refresh();
        $this->assertTrue($slot1->in_use);
        $this->assertNotNull($slot1->current_lease_id);
    }

    public function test_allocator_aborts_when_no_free_slots_exist(): void
    {
        config()->set('mission-control.limits.hard_seconds', 120);
        config()->set('mission-control.limits.idle_seconds', 30);

        $slot = $this->create_slot(1);
        $slot->in_use = true;
        $slot->save();

        try {
            app(LeaseAllocator::class)->allocate();
            $this->fail('Expected allocator to abort when no free slots exist.');
        } catch (HttpException $e) {
            $this->assertSame(429, $e->getStatusCode());
        }
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
