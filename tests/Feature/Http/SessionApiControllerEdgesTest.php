<?php

namespace Tests\Feature\Http;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class SessionApiControllerEdgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_touch_returns_forbidden_when_session_token_missing(): void
    {
        $this->postJson('/api/session/touch', [
            'lease_id' => '00000000-0000-0000-0000-000000000000',
            'mode' => 'input',
        ])->assertStatus(403);
    }

    public function test_touch_returns_gone_when_lease_is_ended(): void
    {
        $slot = $this->create_slot(1);

        $lease = VmLease::query()->create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'vm_slot_id' => $slot->id,
            'token_hash' => hash('sha256', 'token-1'),
            'pid' => null,
            'overlay_path' => null,
            'started_at' => Carbon::now()->subMinute(),
            'last_activity_at' => Carbon::now()->subMinute(),
            'hard_deadline_at' => Carbon::now()->addMinute(),
            'idle_deadline_at' => Carbon::now()->addMinute(),
            'ended_at' => Carbon::now(),
            'end_reason' => 'manual',
        ]);

        session()->put('lease_tokens', [
            $lease->id => 'token-1',
        ]);

        $this->postJson('/api/session/touch', [
            'lease_id' => $lease->id,
            'mode' => 'input',
        ])->assertStatus(410);
    }

    public function test_touch_does_not_extend_idle_deadline_in_heartbeat_mode(): void
    {
        config()->set('mission-control.limits.idle_seconds', 30);
        config()->set('mission-control.limits.hard_seconds', 120);

        $slot = $this->create_slot(2);

        $now = Carbon::now();

        $lease = VmLease::query()->create([
            'id' => '22222222-2222-2222-2222-222222222222',
            'vm_slot_id' => $slot->id,
            'token_hash' => hash('sha256', 'token-2'),
            'pid' => null,
            'overlay_path' => null,
            'started_at' => $now->copy()->subMinute(),
            'last_activity_at' => $now->copy()->subMinute(),
            'hard_deadline_at' => $now->copy()->addMinutes(2),
            'idle_deadline_at' => $now->copy()->addSeconds(5),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        session()->put('lease_tokens', [
            $lease->id => 'token-2',
        ]);

        $before_idle = $lease->idle_deadline_at->getTimestamp();

        $this->postJson('/api/session/touch', [
            'lease_id' => $lease->id,
            'mode' => 'heartbeat',
        ])->assertOk();

        $lease->refresh();
        $after_idle = $lease->idle_deadline_at->getTimestamp();

        $this->assertSame($before_idle, $after_idle);
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
