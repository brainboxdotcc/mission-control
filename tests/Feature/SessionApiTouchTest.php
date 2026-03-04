<?php

namespace Tests\Feature;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SessionApiTouchTest extends TestCase
{
    use RefreshDatabase;

    public function testTouchReturns403WhenTokenMissing(): void
    {
        config()->set('mission-control.limits.hard_seconds', 60);
        config()->set('mission-control.limits.idle_seconds', 10);

        $slot = VmSlot::query()->create([
            'slot_index' => 1,
            'in_use' => true,
            'current_lease_id' => null,
            'bind_host' => '127.0.0.1',
            'display' => 1,
            'ws_port' => 5701,
        ]);

        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $now = Carbon::now();

        $lease = new VmLease();
        $lease->id = (string) Str::uuid();
        $lease->vm_slot_id = $slot->id;
        $lease->token_hash = $tokenHash;
        $lease->started_at = $now;
        $lease->last_activity_at = $now;
        $lease->hard_deadline_at = $now->copy()->addSeconds(60);
        $lease->idle_deadline_at = $now->copy()->addSeconds(10);
        $lease->save();

        // Note: do NOT put lease token in session.

        $this->postJson(route('api.session.touch'), [
            'lease_id' => $lease->id,
            'mode' => 'input',
        ])->assertStatus(403);
    }
}
