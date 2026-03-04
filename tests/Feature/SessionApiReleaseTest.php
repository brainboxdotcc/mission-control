<?php

namespace Tests\Feature;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SessionApiReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function testReleaseEndsLeaseAndFreesSlotWithoutPid(): void
    {
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
        $lease->pid = null;
        $lease->overlay_path = null;
        $lease->save();

        $slot->current_lease_id = $lease->id;
        $slot->save();

        session()->put('lease_tokens', [$lease->id => $token]);

        $this->postJson(route('api.session.release'), [
            'lease_id' => $lease->id,
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $lease->refresh();
        $this->assertNotNull($lease->ended_at);
        $this->assertSame('manual', $lease->end_reason);

        $slot->refresh();
        $this->assertFalse((bool) $slot->in_use);
        $this->assertNull($slot->current_lease_id);
    }
}
