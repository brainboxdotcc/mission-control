<?php

namespace Tests\Feature;

use App\Models\VmSlot;
use App\Services\VmLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class TryStartBusySlotsTest extends TestCase
{
    use RefreshDatabase;

    public function testStartReturns429WhenAllSlotsBusy(): void
    {
        VmSlot::query()->create([
            'slot_index' => 1,
            'in_use' => true,
            'current_lease_id' => 'dummy',
            'bind_host' => '127.0.0.1',
            'display' => 1,
            'ws_port' => 5701,
        ]);

        $launcher = Mockery::mock(VmLauncher::class);
        $launcher->shouldNotReceive('ensureDirsExist');
        $launcher->shouldNotReceive('createOverlay');
        $launcher->shouldNotReceive('startQemu');
        $this->app->instance(VmLauncher::class, $launcher);

        $this->post(route('try.start'))
            ->assertStatus(429);
    }
}
