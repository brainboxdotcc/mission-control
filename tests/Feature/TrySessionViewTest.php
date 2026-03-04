<?php

namespace Tests\Feature;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TrySessionViewTest extends TestCase
{
    use RefreshDatabase;

    public function testSessionRendersWithWsPathAndSlot(): void
    {
        $slot = VmSlot::query()->create([
            'slot_index' => 7,
            'in_use' => true,
            'current_lease_id' => null,
            'bind_host' => '127.0.0.1',
            'display' => 7,
            'ws_port' => 5707,
        ]);

        $now = Carbon::now();

        $lease = new VmLease();
        $lease->id = (string) Str::uuid();
        $lease->vm_slot_id = $slot->id;
        $lease->token_hash = hash('sha256', 'x');
        $lease->started_at = $now;
        $lease->last_activity_at = $now;
        $lease->hard_deadline_at = $now->copy()->addSeconds(60);
        $lease->idle_deadline_at = $now->copy()->addSeconds(10);
        $lease->save();

        $this->get(route('try.session', ['lease' => $lease->id]))
            ->assertOk()
            ->assertViewIs('try.session')
            ->assertViewHas('slot', 7)
            ->assertViewHas('ws_path', '/vnc/7')
            ->assertViewHas('lease_id', $lease->id)
            ->assertViewHas('hard_deadline')
            ->assertViewHas('idle_deadline');
    }
}
