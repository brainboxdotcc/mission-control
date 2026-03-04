<?php

namespace Tests\Feature;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SessionApiTouchExtendsIdleTest extends TestCase
{
    use RefreshDatabase;

    public function testTouchExtendsIdleDeadlineOnInputMode(): void
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

        Carbon::setTestNow(Carbon::parse('2026-03-04 12:00:00'));

        $lease = new VmLease();
        $lease->id = (string) Str::uuid();
        $lease->vm_slot_id = $slot->id;
        $lease->token_hash = $tokenHash;
        $lease->started_at = Carbon::now();
        $lease->last_activity_at = Carbon::now();
        $lease->hard_deadline_at = Carbon::now()->copy()->addSeconds(60);
        $lease->idle_deadline_at = Carbon::now()->copy()->addSeconds(2);
        $lease->save();

        session()->put('lease_tokens', [$lease->id => $token]);

        $this->postJson(route('api.session.touch'), [
            'lease_id' => $lease->id,
            'mode' => 'input',
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $lease->refresh();

        $this->assertSame('2026-03-04 12:00:00', $lease->last_activity_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-04 12:00:10', $lease->idle_deadline_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }
}
